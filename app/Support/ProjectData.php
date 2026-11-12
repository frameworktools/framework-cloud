<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

/**
 * A project's data registry as pull_data_registry() and push_data_registry()
 * see it: the data catalog its layers merge (dot-notation name -> entry),
 * and a revision that changes whenever it does. Only definitions live here:
 * never data files, and never their hashes, which stay in each computer's
 * framework.db.
 */
class ProjectData
{
    /** The fields an entry keeps, as the spec's data_entry has them. */
    public const FIELDS = ['path', 'type', 'delimiter', 'locked', 'private', 'description', 'stata_names'];

    /** The types data_read() reads, with the aliases it accepts. */
    public const TYPES = [
        'csv', 'tsv', 'rds', 'excel', 'xlsx', 'xls', 'parquet', 'stata', 'dta',
        'spss', 'sav', 'zsav', 'spss_por', 'por', 'sas', 'sas7bdat', 'sas7bcat', 'sas_xpt', 'xpt',
    ];

    public const DELIMITERS = ['comma', 'tab', 'semicolon', 'space'];

    public const NAME_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_-]*(\.[A-Za-z0-9_][A-Za-z0-9_-]*)*$/';

    /**
     * The project's registry, sorted by name.
     *
     * @return array{revision: string, entries: list<array<string, mixed>>}
     */
    public static function for(Project $project): array
    {
        $entries = self::entries($project);
        $rows = array_map(fn (string $name) => self::row($name, $entries[$name]), array_keys($entries));

        return ['revision' => self::revision($rows), 'entries' => $rows];
    }

    /**
     * Apply a desired entry list: what's new or changed is added, and what's
     * missing is removed only with $remove. Saving resolves the project
     * again from its choices, as Edit project does.
     *
     * @param  list<array<string, mixed>>  $desired  rows: name, path, type, delimiter, locked, private, description, stata_names
     * @return array{applied: bool, revision: string, changes: array<string, list<mixed>>}
     */
    public static function push(Project $project, array $desired, bool $remove, bool $dryRun): array
    {
        $current = self::entries($project);
        $changes = ['added' => [], 'changed' => [], 'removed' => [], 'kept' => []];
        $definitions = [];

        foreach ($desired as $row) {
            $name = (string) $row['name'];
            $before = $current[$name] ?? null;
            $definition = self::definition($row);
            if ($before === null) {
                $changes['added'][] = self::row($name, $definition);
                $definitions[$name] = $definition;
            } elseif (self::row($name, $before) !== self::row($name, $definition)) {
                $changes['changed'][] = ['name' => $name, 'from' => self::row($name, $before), 'to' => self::row($name, $definition)];
                $definitions[$name] = $definition;
            }
        }

        $named = array_map('strval', array_column($desired, 'name'));
        foreach ($current as $name => $entry) {
            if (! in_array((string) $name, $named, true)) {
                $changes[$remove ? 'removed' : 'kept'][] = self::row((string) $name, $entry);
                if ($remove) {
                    $definitions[$name] = null;
                }
            }
        }

        // The registry as it would be, checked as the spec checks it
        $after = array_filter(array_replace($current, $definitions), fn ($entry) => $entry !== null);
        if ($problems = Layer::problems(['data' => $after], 'blueprint')) {
            throw ValidationException::withMessages(['entries' => $problems[0]]);
        }

        if ($dryRun || $definitions === []) {
            return ['applied' => false, 'revision' => self::for($project)['revision'], 'changes' => $changes];
        }

        ProjectSetup::resolveWith($project, 'entries', function (array $choices) use ($definitions) {
            $choices['data_entries'] = array_replace($choices['data_entries'] ?? [], $definitions);

            return $choices;
        });

        return ['applied' => true, 'revision' => self::for($project->refresh())['revision'], 'changes' => $changes];
    }

    /** @return array<string, array<string, mixed>> the merged catalog by name, removed entries left out */
    private static function entries(Project $project): array
    {
        $spec = DefaultsPreview::merge(ProjectLayers::for($project)['layers'] ?? []);
        $entries = array_filter((array) ($spec['data'] ?? []), 'is_array');
        ksort($entries, SORT_STRING);

        return $entries;
    }

    /** A row from the request as a layer entry: empty fields left out, and locked only when on. */
    private static function definition(array $row): array
    {
        $names = array_filter((array) ($row['stata_names'] ?? []), fn ($value) => filled($value));
        ksort($names, SORT_STRING);

        return array_filter([
            'path' => trim((string) $row['path']),
            'type' => filled($row['type'] ?? null) ? (string) $row['type'] : null,
            'delimiter' => filled($row['delimiter'] ?? null) ? (string) $row['delimiter'] : null,
            'locked' => ($row['locked'] ?? false) ? true : null,
            'private' => isset($row['private']) ? (bool) $row['private'] : null,
            'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
            'stata_names' => $names ?: null,
        ], fn ($value) => $value !== null);
    }

    /** An entry as clients see it, every field present. */
    private static function row(string $name, array $entry): array
    {
        $names = is_array($entry['stata_names'] ?? null) ? $entry['stata_names'] : [];
        ksort($names, SORT_STRING);

        return [
            'name' => $name,
            'path' => (string) ($entry['path'] ?? ''),
            'type' => filled($entry['type'] ?? null) ? (string) $entry['type'] : null,
            'delimiter' => filled($entry['delimiter'] ?? null) ? (string) $entry['delimiter'] : null,
            'locked' => (bool) ($entry['locked'] ?? false),
            'private' => isset($entry['private']) ? (bool) $entry['private'] : null,
            'description' => filled($entry['description'] ?? null) ? (string) $entry['description'] : null,
            'stata_names' => $names ?: null,
        ];
    }

    /** Changes exactly when the entries do. */
    private static function revision(array $rows): string
    {
        return substr(hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES)), 0, 32);
    }
}
