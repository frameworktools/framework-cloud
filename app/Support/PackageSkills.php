<?php

namespace App\Support;

use App\Models\Project;

/**
 * The skills each scaffolding package ships at the commit Cloud pins it to
 * (database/seeders/masters/package-skills.json, from fw:sync-package-skills).
 *
 * A layer names a package skill with {package: true}, and the package
 * refuses one it doesn't ship. Blueprints and projects keep the piece
 * versions they were made from, so a piece made when the package shipped a
 * skill can outlive it. Every envelope Cloud sends is upgraded here first:
 * a retired skill becomes the skill that replaced it. A package skill that
 * is still missing is a problem, refused before any project or repository
 * exists.
 */
class PackageSkills
{
    /**
     * Package skills that were retired, and the skill that replaced each.
     * framework de0afdd merged the two ggplot2 skills into ggplot2-figures.
     */
    public const SUCCESSORS = [
        'ggplot2-exploring' => 'ggplot2-figures',
        'ggplot2-publishing' => 'ggplot2-figures',
    ];

    private static ?array $record = null;

    public static function path(): string
    {
        return database_path('seeders/masters/package-skills.json');
    }

    /** @return array<string, array{package: string, ref: string, commit: string, version: ?string, skills: list<string>}> */
    public static function record(): array
    {
        return self::$record ??= json_decode(file_get_contents(self::path()), true);
    }

    public static function forget(): void
    {
        self::$record = null;
    }

    /** The pin this server scaffolds a language with: a commit for R and Python, a release for Stata. */
    public static function configuredRef(string $language): ?string
    {
        $ref = match ($language) {
            'r' => config('services.github.framework_sha'),
            'python' => config('services.github.framework_py_sha'),
            'stata' => ltrim((string) config('services.github.fw_version'), 'v'),
            default => null,
        };

        return filled($ref) ? (string) $ref : null;
    }

    /**
     * The skills the pinned package ships for a language. Null when the
     * server pins a version the record doesn't describe (an environment
     * override), since then Cloud can't know.
     *
     * @return list<string>|null
     */
    public static function shipped(string $language): ?array
    {
        $entry = self::record()[$language] ?? null;

        return $entry && $entry['ref'] === self::configuredRef($language) ? $entry['skills'] : null;
    }

    /** The env variable that overrides each language's pin in config/services.php */
    public const PIN_VARIABLES = ['r' => 'GITHUB_FRAMEWORK_SHA', 'python' => 'GITHUB_FRAMEWORK_PY_SHA', 'stata' => 'GITHUB_FW_VERSION'];

    /**
     * The pin config/services.php ships with for a language, before any
     * environment override: what the record must describe.
     */
    public static function defaultRef(string $language): ?string
    {
        $variable = self::PIN_VARIABLES[$language] ?? null;
        $source = file_get_contents(config_path('services.php'));

        return $variable && preg_match("/env\\('{$variable}',\\s*'([^']+)'\\)/", $source, $m) ? ltrim($m[1], 'v') : null;
    }

    /** "framework 2.0.13", for messages */
    public static function packageName(string $language): string
    {
        $entry = self::record()[$language] ?? [];

        return trim(($entry['package'] ?? 'the package').' '.($entry['version'] ?? ''));
    }

    /** The project language an envelope's layers name; the topmost wins. */
    public static function language(array $layers): string
    {
        $language = 'r';
        foreach ($layers as $entry) {
            if (is_string($entry['layer']['language'] ?? null)) {
                $language = $entry['layer']['language'];
            }
        }

        return $language;
    }

    /**
     * Replace each retired package skill with its successor. A layer above
     * that removes the successor (null) only did so because the successor
     * wasn't among the pieces when the project was resolved, so that removal
     * goes too. The result is what resolving the same choices now gives.
     *
     * @param  list<array<string, mixed>>  $layers
     * @return list<array<string, mixed>>
     */
    public static function upgradeLayers(array $layers): array
    {
        $shipped = self::record()[self::language($layers)]['skills'] ?? [];
        $replaced = [];

        foreach ($layers as $i => $entry) {
            foreach ($replaced as $successor => $_) {
                if (is_array($entry['layer']['skills'] ?? null) && array_key_exists($successor, $entry['layer']['skills']) && $entry['layer']['skills'][$successor] === null) {
                    unset($layers[$i]['layer']['skills'][$successor]);
                }
            }

            foreach (is_array($entry['layer']['skills'] ?? null) ? $entry['layer']['skills'] : [] as $key => $skill) {
                $successor = self::SUCCESSORS[$key] ?? null;
                if (($skill['package'] ?? false) !== true || ! $successor || in_array($key, $shipped, true) || ! in_array($successor, $shipped, true)) {
                    continue;
                }
                unset($layers[$i]['layer']['skills'][$key]);
                $layers[$i]['layer']['skills'][$successor] ??= ['package' => true];
                $replaced[$successor] = true;
            }
        }

        return $layers;
    }

    public static function upgrade(array $envelope): array
    {
        if (is_array($envelope['layers'] ?? null)) {
            $envelope['layers'] = self::upgradeLayers($envelope['layers']);
        }

        return $envelope;
    }

    /**
     * Package skills the merged layers keep that the pinned package doesn't
     * ship. A later layer replaces a skill, and null removes it.
     *
     * @return list<string>
     */
    public static function problems(array $envelope): array
    {
        $layers = is_array($envelope['layers'] ?? null) ? $envelope['layers'] : [];
        $language = self::language($layers);
        $shipped = self::shipped($language);
        if ($shipped === null) {
            return [];
        }

        $skills = [];
        foreach ($layers as $entry) {
            foreach (is_array($entry['layer']['skills'] ?? null) ? $entry['layer']['skills'] : [] as $key => $skill) {
                $skills[$key] = $skill;
            }
        }

        $languageName = Project::LANGUAGE_NAMES[$language] ?? $language;

        return collect($skills)
            ->filter(fn ($skill, $key) => is_array($skill) && ($skill['package'] ?? false) === true && ! in_array($key, $shipped, true))
            ->keys()
            ->map(fn (string $key) => "The skill {$key} does not ship with ".self::packageName($language).", which builds {$languageName} projects here. Remove it from the blueprint or skill set that adds it.")
            ->values()->all();
    }
}
