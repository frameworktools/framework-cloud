<?php

namespace App\Support;

use App\Http\Controllers\App\ProjectsController;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * A project's setup as pull_all() and push_all() see it: its packages
 * and skills, as the layers it keeps merge, and a revision that changes
 * whenever they change.
 */
class ProjectSetup
{
    /**
     * The project's setup.
     *
     * @return array{revision: string, language: string, packages: list<array<string, mixed>>, skills: list<array<string, mixed>>}
     */
    public static function for(Project $project): array
    {
        $language = $project->language ?? 'r';
        $spec = self::spec($project, $language);
        $definitions = self::definitions($spec);
        $packages = array_map(fn (array $definition, $name) => self::row((string) $name, $definition, $language), $definitions, array_keys($definitions));
        $skills = self::skills($spec['skills'] ?? [], $language);

        return [
            'revision' => self::revision($packages, $skills),
            'language' => $language,
            'packages' => $packages,
            'skills' => $skills,
        ];
    }

    /**
     * Apply a desired package list to the project: what's new or changed is
     * added, and what's missing is removed only with $remove. Saving resolves
     * the project again from its choices, as Edit project does.
     *
     * @param  list<array<string, mixed>>  $desired  rows: name, source, version, auto_attach, repo
     * @return array{applied: bool, revision: string, changes: array<string, list<mixed>>}
     */
    public static function pushPackages(Project $project, array $desired, bool $remove, bool $dryRun): array
    {
        $language = $project->language ?? 'r';
        $current = self::definitions(self::spec($project, $language));
        $changes = ['added' => [], 'changed' => [], 'removed' => [], 'kept' => []];
        $definitions = [];

        foreach ($desired as $row) {
            $name = (string) $row['name'];
            $before = $current[$name] ?? null;
            $definition = self::definition($row, $before, $language);
            if ($before === null) {
                $changes['added'][] = self::row($name, $definition, $language);
                $definitions[$name] = $definition;
            } elseif (self::compared($before, $language) !== self::compared($definition, $language)) {
                $changes['changed'][] = ['name' => $name, 'from' => self::row($name, $before, $language), 'to' => self::row($name, $definition, $language)];
                $definitions[$name] = $definition;
            }
        }

        $named = array_column($desired, 'name');
        foreach ($current as $name => $definition) {
            if (! in_array((string) $name, $named, true)) {
                $changes[$remove ? 'removed' : 'kept'][] = self::row((string) $name, $definition, $language);
                if ($remove) {
                    $definitions[$name] = null;
                }
            }
        }

        if ($dryRun || $definitions === []) {
            return ['applied' => false, 'revision' => self::for($project)['revision'], 'changes' => $changes];
        }

        self::save($project, $definitions);

        return ['applied' => true, 'revision' => self::for($project->refresh())['revision'], 'changes' => $changes];
    }

    /**
     * Store the package entries in the project's choices and resolve it
     * again, as saving Edit project with a setup change does. The blueprint
     * and package sets stay as they are.
     *
     * @param  array<string, array<string, mixed>|null>  $definitions
     */
    private static function save(Project $project, array $definitions): void
    {
        self::resolveWith($project, 'packages', function (array $choices) use ($definitions) {
            $choices['package_definitions'] = array_replace($choices['package_definitions'] ?? [], $definitions);
            // The form's switches for these packages would otherwise override what was pushed
            foreach (['package_overrides', 'package_auto_attach'] as $key) {
                if (isset($choices[$key])) {
                    $choices[$key] = array_diff_key($choices[$key], $definitions);
                }
            }

            return $choices;
        });
    }

    /**
     * Change the project's stored choices and resolve it again from them,
     * as saving Edit project does. Problems are reported under $field.
     *
     * @param  \Closure(array<string, mixed>): array<string, mixed>  $change
     */
    public static function resolveWith(Project $project, string $field, \Closure $change): void
    {
        $project->loadMissing('user', 'blueprintVersion.blueprint.owner');
        $blueprint = $project->blueprintVersion?->blueprint;
        // Without a blueprint, only exact pieces from POST /api/v1/projects can resolve again
        $exact = ! $blueprint && ($project->choices['customized'] ?? false) && filled($project->choices['structure'] ?? null);
        if ((! $blueprint && ! $exact) || ! $project->user) {
            throw ValidationException::withMessages([$field => 'This project has no blueprint, so its setup can\'t change on Framework Cloud.']);
        }

        $stored = $project->choices ?? ($project->composition ? self::derivedChoices($project) : ['customized' => false]);
        if ($stored === null) {
            throw ValidationException::withMessages([$field => 'This project has changes from before Framework Cloud kept choices. Open Edit project on Framework Cloud and save its setup once first.']);
        }

        $choices = $change($stored);
        $resolved = self::resolve($project, $choices);

        $project->update(Resolver::setupAttributes($project->name, $resolved) + [
            'choices' => ProjectsController::storedChoices($choices),
            'setup_updated_at' => now(),
        ]);
    }

    /** The project resolved from stored choices, as Edit project resolves them. */
    private static function resolve(Project $project, array $choices): array
    {
        $input = ['name' => $project->name, 'language' => $project->language ?? 'r'] + $choices;
        $blueprint = $project->blueprintVersion?->blueprint;

        return (new Resolver($project->user))->resolve($blueprint
            ? ProjectsController::choicesFrom($input, $blueprint)
            : Arr::only($input, ['name', 'language', ...ProjectsController::CHOICE_FIELDS]));
    }

    /**
     * The choices to store for a project made with POST /api/v1/projects.
     * With a blueprint, the request's pieces and settings are changes to it,
     * kept with customized off. Without one, they are the exact pieces.
     *
     * @param  array<string, mixed>  $choices  the request's validated choices
     * @param  array<string, mixed>  $resolved  from Resolver::resolve()
     * @return array<string, mixed>
     */
    public static function apiChoices(array $choices, array $resolved): array
    {
        return ProjectsController::storedChoices([
            'customized' => $resolved['blueprint'] === null,
            // Pinned, so a later change to the blueprint's default workflow leaves the project as it is
            'workflow' => $resolved['workflow'] ?? ($choices['workflow'] ?? null),
        ] + Arr::only($choices, ProjectsController::BLUEPRINT_CHANGES));
    }

    /**
     * Choices for a project from before Framework Cloud kept them: its
     * pieces and its own layer, read as changes to its blueprint. Used only
     * when they resolve to exactly the project's current setup; otherwise
     * null.
     *
     * @return array<string, mixed>|null
     */
    private static function derivedChoices(Project $project): ?array
    {
        $version = $project->blueprintVersion;
        $composition = $project->composition;
        if (! $version || ! isset($composition['structure_version_id'])) {
            return null;
        }

        $version->loadMissing('base.facet', 'facetVersions.facet');
        $pieces = FacetVersion::with('facet.owner')->whereIn('id', $composition['piece_version_ids'] ?? [])->get();
        $structure = FacetVersion::with('facet.owner')->find($composition['structure_version_id']);
        $inBlueprint = $version->facetVersions->pluck('facet_id')->all();
        $ofKind = fn (string $kind) => $pieces->filter(fn (FacetVersion $v) => $v->facet->kind === $kind);
        $added = fn (string $kind) => $ofKind($kind)->reject(fn (FacetVersion $v) => in_array($v->facet_id, $inBlueprint, true))->map(fn (FacetVersion $v) => $v->facet->handle)->values()->all();

        $choices = ['customized' => false, 'workflow' => $ofKind('workflow')->first()?->facet->category];
        if ($structure && $structure->facet_id !== $version->base?->facet_id) {
            $choices['structure'] = $structure->facet->handle;
        }
        $tools = $ofKind('tool')->mapWithKeys(fn (FacetVersion $v) => [$v->facet->isSystem() ? $v->facet->key : $v->facet->handle => true])->all();
        foreach ($version->facetVersions->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool') as $v) {
            $tools[$v->facet->isSystem() ? $v->facet->key : $v->facet->handle] ??= false;
        }
        $choices['tools'] = $tools;
        foreach (['package_sets' => 'package_set', 'skill_sets' => 'skill_set'] as $field => $pieceKind) {
            if ($handles = $added($pieceKind)) {
                $choices[$field] = $handles;
            }
        }

        // The project's own layer: single skills, packages, the data registry, and settings
        $own = collect(ProjectLayers::for($project)['layers'] ?? [])
            ->first(fn (array $l) => ($l['kind'] ?? null) === 'project' && str_ends_with((string) ($l['key'] ?? ''), ' choices'))['layer'] ?? [];
        $handle = function (string $key, mixed $entry) use ($project): ?string {
            $skill = isset($entry['sync']['skill_id'])
                ? Skill::with('owner')->where('sync_id', $entry['sync']['skill_id'])->first()
                : Skill::visibleTo($project->user)->with('owner')->where('key', $key)->orderByRaw('user_id is not null')->first();

            return $skill?->handle;
        };
        foreach ($own['skills'] ?? [] as $key => $entry) {
            if ($found = $handle((string) $key, $entry)) {
                $choices[$entry === null ? 'excluded_skills' : 'skills'][] = $found;
            }
        }
        if (isset($own['packages']['default_packages'])) {
            $choices['package_definitions'] = $own['packages']['default_packages'];
        }
        if (isset($own['data'])) {
            $choices['data_entries'] = $own['data'];
        }
        $settings = array_filter([
            'sensitive' => $own['security']['sensitive'] ?? null,
            'git' => $own['git']['use_git'] ?? null,
            'hooks' => isset($own['git']['hooks']) ? array_keys(array_filter($own['git']['hooks'])) : null,
            'ai' => $own['ai'] ?? null,
        ], fn ($v) => $v !== null);
        if ($settings) {
            $choices['settings'] = $settings;
        }
        if (isset($own['scaffold']['notebook_format'])) {
            $choices['notebook_format'] = $own['scaffold']['notebook_format'];
        }

        // Sound only when nothing but the change itself would differ
        try {
            $again = self::resolve($project, $choices);
        } catch (ValidationException) {
            return null;
        }

        return DefaultsPreview::merge($again['envelope']['layers']) == DefaultsPreview::merge(ProjectLayers::for($project)['layers'] ?? [])
            ? $choices
            : null;
    }

    /**
     * After a push saved $mine, the user's own copy of a skill, the project
     * uses that copy where its setup listed a built-in Framework skill or the
     * skill the copy was made from, so the next pull keeps it. True when the
     * setup changed.
     */
    public static function useSkill(Project $project, Skill $mine, ?Skill $source): bool
    {
        $language = $project->language ?? 'r';
        if (! $mine->fits($language)) {
            return false;
        }
        $entries = self::spec($project, $language)['skills'] ?? [];
        $key = collect([$source?->key, $mine->key])->filter()->first(fn (string $k) => is_array($entries[$k] ?? null));
        if ($key === null) {
            return false;
        }

        $entry = $entries[$key];
        $current = isset($entry['sync']['skill_id']) ? Skill::with('owner')->where('sync_id', $entry['sync']['skill_id'])->first() : null;
        if ($current?->is($mine)) {
            return false;
        }
        $builtIn = ($entry['package'] ?? false) === true || ($current && $current->isSystem());
        if (! $builtIn && ! ($source && $current?->is($source))) {
            return false;
        }
        $replaced = $current?->handle ?? Skill::whereNull('user_id')->where('key', $key)->first()?->handle;

        self::resolveWith($project, 'skills', function (array $choices) use ($project, $key, $mine, $replaced) {
            $keys = Skill::visibleTo($project->user)->with('owner')->get()->mapWithKeys(fn (Skill $s) => [$s->handle => $s->key]);
            $other = fn (string $h) => ($keys[$h] ?? null) !== $key && ($keys[$h] ?? null) !== $mine->key;
            $choices['skills'] = [...array_values(array_filter($choices['skills'] ?? [], $other)), $mine->handle];
            $excluded = array_values(array_filter($choices['excluded_skills'] ?? [], $other));
            // A copy under another key replaces the one it was made from
            if ($mine->key !== $key && $replaced) {
                $excluded[] = $replaced;
            }
            if ($excluded || isset($choices['excluded_skills'])) {
                $choices['excluded_skills'] = $excluded;
            }

            return $choices;
        });

        return true;
    }

    /**
     * A row from the request as a layer entry. Left out, auto_attach keeps
     * what the package had (else off), and a GitHub ref is kept.
     */
    private static function definition(array $row, ?array $before, string $language): array
    {
        if (($row['auto_attach'] ?? false) === true && $language !== 'r') {
            throw ValidationException::withMessages(['packages' => 'Auto-attach is available for R only. Python and Stata do not auto-import packages.']);
        }
        $source = $row['source'] ?? ($language === 'python' ? 'pypi' : 'cran');
        $definition = array_filter([
            'source' => $source,
            'version' => filled($row['version'] ?? null) ? trim((string) $row['version']) : null,
            'repo' => $source === 'github' && filled($row['repo'] ?? null) ? trim((string) $row['repo']) : null,
            'ref' => $source === 'github' ? ($before['ref'] ?? null) : null,
            'auto_attach' => $language === 'r' && (bool) ($row['auto_attach'] ?? $before['auto_attach'] ?? false),
        ], fn ($value) => $value !== null);

        if ($problem = Resolver::packageProblem((string) $row['name'], $definition, $language)) {
            throw ValidationException::withMessages(['packages' => $problem]);
        }

        return $definition;
    }

    /** What counts as a change: the source, version, GitHub repository, and, in R, auto-attach. */
    private static function compared(array $definition, string $language): array
    {
        return [
            $definition['source'] ?? null,
            filled($definition['version'] ?? null) ? (string) $definition['version'] : null,
            ($definition['source'] ?? null) === 'github' ? ($definition['repo'] ?? null) : null,
            $language === 'r' && ($definition['auto_attach'] ?? false),
        ];
    }

    /**
     * The merged spec. The language package's own default packages (dplyr,
     * ggplot2, and so on in R) sit beneath the layers, as clients add them.
     */
    private static function spec(Project $project, string $language): array
    {
        $defaults = in_array($language, ProjectDefaults::PACKAGE_LANGUAGES, true)
            ? ProjectDefaults::packageLayer(ProjectDefaults::packages(ProjectDefaults::DEFAULT_PACKAGES)[$language])
            : [];

        return DefaultsPreview::merge([
            ['layer' => $defaults ? ['packages' => ['default_packages' => $defaults]] : []],
            ...(ProjectLayers::for($project)['layers'] ?? []),
        ]);
    }

    /** @return array<string, array<string, mixed>> the merged packages, removed ones left out */
    private static function definitions(array $spec): array
    {
        return array_filter((array) ($spec['packages']['default_packages'] ?? []), 'is_array');
    }

    private static function row(string $name, array $definition, string $language): array
    {
        return [
            'name' => $name,
            'source' => $definition['source'] ?? ($language === 'python' ? 'pypi' : 'cran'),
            'version' => filled($definition['version'] ?? null) ? (string) $definition['version'] : null,
            'auto_attach' => $language === 'r' && (bool) ($definition['auto_attach'] ?? false),
            'repo' => filled($definition['repo'] ?? null) ? (string) $definition['repo'] : null,
        ];
    }

    /**
     * Skills as clients install them: the key, the handle and version when
     * the skill is known, and either package: true (the language package
     * ships it) or its content and bundle.
     *
     * @return list<array<string, mixed>>
     */
    private static function skills(array $entries, string $language): array
    {
        $entries = array_filter($entries, 'is_array');
        ksort($entries);
        $syncIds = array_filter(array_map(fn (array $e) => $e['sync']['skill_id'] ?? null, $entries));
        $known = Skill::with('owner', 'latestVersion')
            ->where(fn ($q) => $q->whereIn('sync_id', $syncIds)->orWhere(fn ($q) => $q->whereNull('user_id')->whereIn('key', array_keys($entries))))
            ->get();

        return array_values(array_map(function (array $entry, string $key) use ($known) {
            $skill = isset($entry['sync']['skill_id'])
                ? $known->firstWhere('sync_id', $entry['sync']['skill_id'])
                : $known->first(fn (Skill $s) => $s->user_id === null && $s->key === $key);
            $package = ($entry['package'] ?? false) === true;

            return [
                'key' => $key,
                'handle' => $skill?->handle,
                'version' => $entry['sync']['version'] ?? $skill?->latestVersion?->version,
                'package' => $package,
            ] + ($package ? [] : array_filter([
                'content' => $entry['content'] ?? null,
                'bundle' => $entry['bundle'] ?? null,
                'files' => $entry['files'] ?? null,
                'sync' => $entry['sync'] ?? null,
            ], fn ($v) => $v !== null));
        }, $entries, array_keys($entries)));
    }

    /** Changes exactly when the packages or skills do. */
    private static function revision(array $packages, array $skills): string
    {
        $skills = array_map(fn (array $s) => [$s['key'], $s['package'], $s['version'], isset($s['bundle']) ? hash('sha256', json_encode($s['bundle'])) : null, isset($s['content']) ? hash('sha256', $s['content']) : null], $skills);

        return substr(hash('sha256', json_encode([$packages, $skills], JSON_UNESCAPED_SLASHES)), 0, 32);
    }
}
