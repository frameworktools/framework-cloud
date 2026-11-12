<?php

namespace App\Support;

use App\Http\Controllers\App\ProjectsController;
use App\Models\Project;
use App\Models\Skill;
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
        if (! $blueprint || ! $project->user) {
            throw ValidationException::withMessages([$field => 'This project has no blueprint, so its setup can\'t change on Framework Cloud.']);
        }
        if ($project->composition && ! $project->choices) {
            throw ValidationException::withMessages([$field => 'This project has changes from before Framework Cloud kept choices. Open Edit project on Framework Cloud and save its setup once first.']);
        }

        $choices = $change($project->choices ?? ['customized' => false]);

        $resolved = (new Resolver($project->user))->resolve(ProjectsController::choicesFrom(
            ['name' => $project->name, 'language' => $project->language ?? 'r'] + $choices,
            $blueprint,
        ));

        $project->update(Resolver::setupAttributes($project->name, $resolved) + [
            'choices' => ProjectsController::storedChoices($choices),
            'setup_updated_at' => now(),
        ]);
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
