<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a person's stored account defaults into personal blueprints, once.
 * For each language they use, the blueprint holds what their new projects
 * got from the old defaults: their default blueprint with their tools, sets,
 * packages, Git, hooks, agents, editor files, scaffold, Quarto, and
 * publishing settings. A language whose defaults match Framework's keeps
 * Framework's blueprint. The old values stay where they were.
 */
class DefaultsConversion
{
    /**
     * @return array{skipped: bool, languages: array<string, string>} what each language's default became
     */
    public static function convert(User $user, bool $force = false, bool $dryRun = false): array
    {
        $saved = $user->blueprint_defaults ?? [];
        if (isset($saved['converted_at']) && ! $force) {
            return ['skipped' => true, 'languages' => []];
        }

        $legacy = LegacyDefaults::for($user);
        $languages = $user->projects()->withTrashed()->distinct()->pluck('language')->filter()->all();
        $languages = array_values(array_intersect(Project::LANGUAGES, [...$languages, $legacy['language'] ?? ProjectDefaults::LANGUAGE]));
        $result = [];

        foreach ($languages as $language) {
            if (isset($saved['blueprints'][$language])) {
                $result[$language] = 'kept';

                continue;
            }

            [$blueprint, $created] = self::blueprintFor($user, $legacy, $language, $dryRun);
            if ($created || ! $blueprint->isSystem() || $blueprint->key !== ProjectDefaults::BLUEPRINT) {
                $saved['blueprints'][$language] = $blueprint->id;
            }
            $result[$language] = $created ? "own blueprint {$blueprint->name}" : $blueprint->handle;
        }

        if (! isset($saved['language']) && $legacy['language']) {
            $saved['language'] = $legacy['language'];
        }
        if (! $dryRun) {
            $saved['converted_at'] = now()->toIso8601String();
            $user->forceFill(['blueprint_defaults' => $saved])->save();
        }

        return ['skipped' => false, 'languages' => $result];
    }

    /**
     * Guided setup's answers, saved as the old defaults were, become the
     * language's default blueprint: Framework's when they match it, else
     * the person's own "My defaults" blueprint, made or updated.
     */
    public static function applyGuidedSetup(User $user, string $language): Blueprint
    {
        [$blueprint] = self::blueprintFor($user, LegacyDefaults::for($user), $language);
        ProjectDefaults::save($user, $language, [$language => $blueprint]);

        return $blueprint;
    }

    /**
     * The blueprint that gives a language's projects what these defaults
     * gave them: the default blueprint itself when nothing else differs,
     * else the person's "My defaults" blueprint for the language, made or
     * given a new version. A dry run makes nothing.
     *
     * @param  array<string, mixed>  $legacy  from LegacyDefaults
     * @return array{0: Blueprint, 1: bool} the blueprint, and whether it holds the person's own defaults
     */
    public static function blueprintFor(User $user, array $legacy, string $language, bool $dryRun = false): array
    {
        $mine = self::compose($user, $legacy, $language);
        $plain = self::compose($user, LegacyDefaults::untouched($legacy), $language);
        if (self::fingerprint($mine) === self::fingerprint($plain)) {
            return [$mine['base']->blueprint, false];
        }

        $name = 'My defaults ('.Project::LANGUAGE_NAMES[$language].')';
        $existing = Blueprint::where('user_id', $user->id)->where('name', $name)->first();
        if ($dryRun) {
            return [$existing ?? new Blueprint(['name' => $name]), true];
        }

        $blueprint = DB::transaction(function () use ($user, $language, $name, $mine, $existing) {
            $blueprint = $existing ?? Blueprint::create([
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $name, $user->id),
                'name' => $name,
                'description' => 'Your default settings for new '.Project::LANGUAGE_NAMES[$language].' projects.',
                'language' => $language,
                'visibility' => 'private',
                'is_system' => false,
            ]);
            $blueprint->publish($mine['structure'], $mine['pieces'], $mine['layer'], 'Made from your default settings', $mine['compatibility']);

            return $blueprint;
        });

        return [$blueprint->fresh(), true];
    }

    /**
     * What a new project in one language got from these defaults, as a
     * blueprint version's parts: the old resolution, step by step.
     *
     * @param  array<string, mixed>  $legacy  from LegacyDefaults
     * @return array{base: BlueprintVersion, structure: FacetVersion, pieces: list<int>, layer: array<string, mixed>, compatibility: array<string, mixed>}
     */
    public static function compose(User $user, array $legacy, string $language): array
    {
        $base = self::baseBlueprint($user, $legacy['blueprint'], $language);
        $own = $base->blueprint->user_id !== null;
        $baseLayer = $base->layer ?? [];
        $compatibility = $base->compatibility();
        $supported = array_values(array_filter($compatibility['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS, fn (string $w) => ProjectDefaults::workflowFits($w, $language)));
        $preferred = $legacy['workflows'][$language] ?? null;
        $workflow = $preferred && in_array($preferred, $supported, true) ? $preferred : ProjectDefaults::workflowOf($base, $language);
        $settings = $legacy['settings'];
        $choices = ['name' => 'project', 'blueprint' => $base->blueprint->handle, 'language' => $language, 'workflow' => $workflow];

        $choices['settings'] = ['git' => $settings['git'], 'ai' => $settings['ai'] && ! in_array('none', $base->agent_defaults ?? [], true)];
        if ($settings['git'] && $language !== 'stata' && ! ($own && isset($baseLayer['git']['hooks']))) {
            $choices['settings']['hooks'] = $settings['hooks'];
        }
        if ($language === 'r' && $legacy['rmarkdown'] && $workflow === 'quarto' && ! isset($baseLayer['scaffold']['notebook_format'])) {
            $choices['notebook_format'] = 'rmarkdown';
        }

        // First without sets, for the skill sets Framework's blueprints add with these settings
        $plain = (new Resolver($user, true, []))->resolve($choices);
        $handles = fn (string $kind) => collect($plain['pieces'])->filter(fn (FacetVersion $v) => $v->facet->kind === $kind)->map(fn (FacetVersion $v) => $v->facet->handle)->values()->all();
        $sets = ['package_sets' => [...$handles('package_set'), ...($own ? [] : $legacy['package_sets'])], 'skill_sets' => [...$handles('skill_set'), ...($own ? [] : $legacy['skill_sets'])]];
        foreach ($sets as $field => $list) {
            $choices[$field] = array_values(array_unique($list));
        }
        $tools = $own || $language === 'stata' ? [] : self::tools($plain['pieces'], $legacy['tools'], $language, $workflow, $compatibility['requires_tools']);

        try {
            $resolved = (new Resolver($user, true, []))->resolve($choices + ($tools ? ['tools' => $tools] : []));
        } catch (ValidationException) {
            // Tools that can't go together are left as the blueprint has them
            $resolved = (new Resolver($user, true, []))->resolve($choices);
        }

        $layer = self::mergeLayers($resolved['layers']['blueprint'], $resolved['layers']['choices']);
        $layer = self::mergeLayers($layer, self::extraLayer($legacy, $language, $resolved['pieces'], $own ? $baseLayer : []));

        $agents = $own && $base->agent_defaults ? ProjectDefaults::agentDefaults($base) : match (true) {
            ! $settings['ai'] => ['none'],
            default => [...$settings['agents'], ...($legacy['ai_other'] || ! $settings['agents'] ? ['other'] : [])],
        };

        return [
            'base' => $base,
            'structure' => $resolved['structure'],
            'pieces' => array_map(fn (FacetVersion $v) => $v->id, $resolved['pieces']),
            'layer' => $layer,
            'compatibility' => array_filter([
                'languages' => [$language],
                'workflows' => [$workflow],
                'default_workflow' => [$language => $workflow],
                'requires_tools' => $language === 'stata' ? [] : $compatibility['requires_tools'],
                'requires' => $compatibility['requires'],
                'agent_defaults' => $agents,
                'ide' => $language === 'stata' ? null : ($settings['ide'] === 'positron' ? 'vscode' : $settings['ide']),
            ], fn ($value) => $value !== null && $value !== []),
        ];
    }

    /** The default blueprint the old defaults gave a language, or the standard project. */
    private static function baseBlueprint(User $user, string $handle, string $language): BlueprintVersion
    {
        $blueprint = Blueprint::visibleTo($user)->with('latestVersion.blueprint', 'latestVersion.base.facet', 'latestVersion.facetVersions.facet')->get()
            ->first(fn (Blueprint $b) => $b->handle === $handle || ($b->isSystem() && "framework/{$b->key}" === $handle));
        if (! $blueprint || ! ProjectDefaults::fits($blueprint, $language)) {
            $blueprint = Blueprint::whereNull('user_id')->where('key', ProjectDefaults::BLUEPRINT)->with('latestVersion.blueprint', 'latestVersion.base.facet', 'latestVersion.facetVersions.facet')->firstOrFail();
        }

        return $blueprint->latestVersion;
    }

    /**
     * The tools the old defaults switched on or off, as project choices. A
     * tool the workflow or blueprint needs stays on.
     *
     * @param  list<FacetVersion>  $pieces
     * @param  array<string, bool>  $tools
     * @param  list<string>  $required
     * @return array<string, bool>
     */
    private static function tools(array $pieces, array $tools, string $language, string $workflow, array $required): array
    {
        $on = collect($pieces)->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool')->map(fn (FacetVersion $v) => $v->facet->key)->all();
        $needed = [...$required, ...(collect($pieces)->first(fn (FacetVersion $v) => $v->facet->kind === 'workflow')?->facet->requires ?? [])];
        $out = [];
        foreach ($tools as $key => $wanted) {
            $tool = Facet::whereNull('user_id')->where('kind', 'tool')->where('key', $key)->with('latestVersion.facet')->first();
            if (! $tool?->latestVersion || ! ProjectLayers::fits($tool->latestVersion, $language) || $wanted === in_array($key, $on, true)) {
                continue;
            }
            if (! $wanted && in_array($key, $needed, true)) {
                continue;
            }
            $out[$key] = (bool) $wanted;
        }

        return $out;
    }

    /**
     * The old defaults' own settings: scaffold and Quarto values apart from
     * Framework's, the publishing destination, and the package list.
     *
     * @param  list<FacetVersion>  $pieces
     * @param  array<string, mixed>  $ownLayer  the person's own default blueprint's layer, whose packages win
     * @return array<string, mixed>
     */
    private static function extraLayer(array $legacy, string $language, array $pieces, array $ownLayer): array
    {
        $settings = $legacy['settings'];
        $layer = [];
        if (in_array($legacy['publish_destination'], ['cloud', 'github'], true)) {
            $layer['publish'] = ['default' => $legacy['publish_destination'], 'destinations' => [$legacy['publish_destination'] => ['driver' => $legacy['publish_destination']]]];
        }
        if ($language !== 'stata') {
            $keys = $language === 'r' ? array_keys(ProjectDefaults::SCAFFOLD) : ['seed_on_scaffold', 'seed', 'source_all_functions'];
            foreach ($keys as $key) {
                if ($settings['scaffold'][$key] !== ProjectDefaults::SCAFFOLD[$key]) {
                    $layer['scaffold'][$key] = $settings['scaffold'][$key];
                }
            }
            foreach (ProjectDefaults::QUARTO as $format => $values) {
                foreach ($values as $key => $value) {
                    if ($settings['quarto'][$format][$key] !== $value) {
                        $layer['quarto'][$format][$key] = $settings['quarto'][$format][$key];
                    }
                }
            }
        }

        $rows = $legacy['packages'][$language] ?? null;
        if ($rows !== null && $rows !== ProjectDefaults::packages(ProjectDefaults::DEFAULT_PACKAGES)[$language]) {
            $entries = ProjectDefaults::packageLayer($rows);
            if ($language === 'r') {
                $fromPieces = collect($pieces)->flatMap(fn (FacetVersion $v) => array_keys($v->layer['packages']['default_packages'] ?? []))->all();
                foreach (array_column(ProjectDefaults::DEFAULT_PACKAGES['r'], 'name') as $name) {
                    if (! isset($entries[$name]) && ! in_array($name, $fromPieces, true)) {
                        $entries[$name] = null;
                    }
                }
            }
            $entries = array_diff_key($entries, $ownLayer['packages']['default_packages'] ?? []);
            if ($entries) {
                $layer['packages']['default_packages'] = $entries;
            }
        }

        return $layer;
    }

    /**
     * Two layers as one: maps merge by key and a later value wins. A skill
     * or package entry is replaced whole, and a null stays, so it still
     * takes out what a piece brings.
     */
    private static function mergeLayers(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (in_array($key, ['skills'], true) && is_array($value)) {
                $base[$key] = array_replace($base[$key] ?? [], $value);
            } elseif ($key === 'packages' && is_array($value) && isset($value['default_packages'])) {
                $base[$key] = array_replace($base[$key] ?? [], $value, ['default_packages' => array_replace($base[$key]['default_packages'] ?? [], $value['default_packages'])]);
            } elseif (is_array($value) && ! array_is_list($value) && is_array($base[$key] ?? null)) {
                $base[$key] = self::mergeLayers($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /** A composition's content, for comparing two. */
    private static function fingerprint(array $composition): string
    {
        $sort = function ($value) use (&$sort) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };
        $pieces = $composition['pieces'];
        sort($pieces);

        return json_encode($sort([$composition['structure']->id, $pieces, $composition['layer'], $composition['compatibility']]));
    }
}
