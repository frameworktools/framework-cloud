<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * What a new project from a blueprint would get, resolved the way project
 * creation resolves it, for one language and workflow. Read-only.
 */
class DefaultsPreview
{
    /** Entries replaced whole when a later layer sets them, as in the layer merge R, Python, and fw share. */
    private const ATOMIC = ['files', 'skills', 'extra_directories', 'packages/default_packages'];

    /**
     * A Framework blueprint with the workflow guided setup's form has for
     * the language, when the blueprint takes it.
     *
     * @param  array<string, mixed>  $values  guided setup's form values
     * @return array<string, mixed>
     */
    public static function for(User $user, Blueprint $blueprint, string $language, array $values): array
    {
        $type = $blueprint->latestVersion->base->layer['type'] ?? 'bare';
        if ($problem = Project::typeProblem($language, $type)) {
            return ['available' => false, 'problem' => $problem];
        }

        $workflow = $values['workflows'][$language] ?? null;
        $supported = $blueprint->latestVersion->compatibility()['workflows'];
        $fits = is_string($workflow) && ProjectDefaults::workflowFits($workflow, $language) && (! $supported || in_array($workflow, $supported, true));

        return self::forBlueprint($user, $blueprint, $language, $fits ? $workflow : null);
    }

    /**
     * What a project from any blueprint the person can see gets, for one
     * language and workflow: the same resolution New project makes with
     * that blueprint, language, and workflow.
     *
     * @param  array<string, mixed>|null  $defaults  the person's defaults; [] for none, as on a blueprint's public page
     * @return array<string, mixed>
     */
    public static function forBlueprint(User $user, Blueprint $blueprint, string $language, ?string $workflow, ?array $defaults = null): array
    {
        try {
            $resolved = (new Resolver($user, true, $defaults))->resolve(['name' => 'project', 'blueprint' => $blueprint->handle, 'language' => $language, 'workflow' => $workflow]);
        } catch (ValidationException $e) {
            return ['available' => false, 'problem' => collect($e->errors())->flatten()->first()];
        }

        return self::describe($resolved, $blueprint, ['ai_other' => in_array('other', ProjectDefaults::agentDefaults($resolved['blueprint']), true)]);
    }

    /**
     * @param  array<string, mixed>  $resolved  from Resolver::resolve()
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function describe(array $resolved, Blueprint $blueprint, array $values): array
    {
        $layers = $resolved['envelope']['layers'];
        // R starts every project with Framework's own package list, beneath the layers
        $spec = self::merge($resolved['language'] === 'r'
            ? [['layer' => ['packages' => ['default_packages' => ProjectDefaults::packageLayer(ProjectDefaults::packages(ProjectDefaults::DEFAULT_PACKAGES)['r'])]]], ...$layers]
            : $layers);
        $version = $blueprint->latestVersion;
        $own = $version->facetVersions->pluck('id')->all();
        $from = fn (FacetVersion $v) => in_array($v->id, $own, true) ? 'blueprint' : 'framework';
        $ofKind = fn (string $kind) => collect($resolved['pieces'])->filter(fn (FacetVersion $v) => $v->facet->kind === $kind)->values();
        $packages = fn (?array $layer) => array_keys($layer['packages']['default_packages'] ?? []);

        $skillSets = $ofKind('skill_set');
        $inSets = $skillSets->flatMap(fn (FacetVersion $v) => array_keys($v->catalogLayer($resolved['language'])['skills'] ?? []))->all();
        $aiOn = (bool) ($spec['ai']['enabled'] ?? true);

        // Settings the blueprint (or its structure) sets itself; the rest are Framework's
        $blueprintLayer = $version->layer ?? [];
        $sensitive = ProjectLayers::isSensitive($layers);

        return [
            'available' => true,
            'language' => $resolved['language'],
            'workflow' => $resolved['workflow'],
            'notices' => $resolved['notices'],
            'tree' => ProjectTree::for($spec, $resolved['language'], $ofKind('tool')->map(fn (FacetVersion $v) => $v->facet->key)->all(), $sensitive),
            // Every package the project starts with, and where each comes from
            'packages' => collect($spec['packages']['default_packages'] ?? [])->map(fn ($entry, $name) => [
                'name' => (string) $name,
                'source' => $entry['source'] ?? null,
                'auto_attach' => (bool) ($entry['auto_attach'] ?? false),
                'from' => collect($resolved['pieces'])->first(fn (FacetVersion $v) => isset($v->catalogLayer($resolved['language'])['packages']['default_packages'][$name]))?->facet->name,
            ])->values()->all(),
            'package_sets' => $ofKind('package_set')->map(fn (FacetVersion $v) => [
                'name' => $v->facet->name, 'id' => $v->facet->id, 'packages' => $packages($v->catalogLayer($resolved['language'])), 'from' => $from($v),
            ])->all(),
            // Packages that come with other pieces, such as ggplot2 with its skill set
            'other_packages' => collect($resolved['pieces'])->reject(fn (FacetVersion $v) => $v->facet->kind === 'package_set')
                ->flatMap(fn (FacetVersion $v) => array_map(fn ($p) => ['package' => $p, 'from' => $v->facet->name], $packages($v->catalogLayer($resolved['language']))))->values()->all(),
            'skill_sets' => $skillSets->map(fn (FacetVersion $v) => [
                'name' => $v->facet->name, 'id' => $v->facet->id, 'from' => $from($v),
                'skills' => array_map(self::skillName(...), array_keys($v->catalogLayer($resolved['language'])['skills'] ?? [])),
            ])->all(),
            'skills' => $aiOn ? array_map(self::skillName(...), array_values(array_diff(array_keys($spec['skills'] ?? []), $inSets))) : [],
            'agents' => $aiOn,
            'agent_keys' => array_values(array_intersect(ProjectDefaults::AGENTS, array_keys(array_filter((array) ($spec['ai']['assistants'] ?? []))))),
            'agent_other' => (bool) ($values['ai_other'] ?? false),
            'agent_names' => array_values(array_map(fn (string $a) => ProjectDefaults::AGENT_NAMES[$a], array_intersect(ProjectDefaults::AGENTS, array_keys(array_filter((array) ($spec['ai']['assistants'] ?? [])))))),
            'tools' => $ofKind('tool')->map(fn (FacetVersion $v) => ['name' => $v->facet->name, 'from' => $from($v)])->all(),
            // Kept for older pages: nothing outside the blueprint turns its tools off now
            'tools_off' => [],
            'settings' => [
                'git' => ['on' => (bool) ($spec['git']['use_git'] ?? true), 'from' => isset($blueprintLayer['git']['use_git']) ? 'blueprint' : 'framework'],
                // The blueprint and its structure decide
                'sensitive' => ['on' => $sensitive],
                'agents' => ['on' => $aiOn, 'from' => isset($blueprintLayer['ai']) ? 'blueprint' : 'framework'],
                'hooks' => [
                    'on' => array_keys(array_filter((array) ($spec['git']['hooks'] ?? []))),
                    'from' => isset($blueprintLayer['git']['hooks']) ? 'blueprint' : 'framework',
                ],
            ],
        ];
    }

    /**
     * The layers merged into one spec: maps merge by key, a null removes,
     * lists and the atomic entries are replaced whole.
     *
     * @param  list<array<string, mixed>>  $layers
     * @return array<string, mixed>
     */
    public static function merge(array $layers): array
    {
        $spec = [];
        foreach ($layers as $entry) {
            $spec = self::mergeInto($spec, (array) ($entry['layer'] ?? []), '');
        }

        return $spec;
    }

    private static function mergeInto(array $base, array $over, string $path): array
    {
        foreach ($over as $key => $value) {
            $here = ltrim("{$path}/{$key}", '/');
            if ($value === null) {
                unset($base[$key]);
            } elseif (is_array($value) && ! array_is_list($value) && ! in_array($path, self::ATOMIC, true) && is_array($base[$key] ?? null)) {
                $base[$key] = self::mergeInto($base[$key], $value, $here);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function skillName(string $key): string
    {
        return Skill::whereNull('user_id')->where('key', $key)->value('name') ?? $key;
    }
}
