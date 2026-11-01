<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * What blueprints and pieces may do to a project. Content from other people
 * is untrusted: using it never runs code and never grants permissions.
 *
 * - Settings that belong to the person (their editor files and AI agents)
 *   are theirs alone, so no blueprint or piece sets them.
 * - Files that run on their own, or that hand out permissions, can't come
 *   from someone else's blueprint or piece: R and Python startup files,
 *   Framework's scaffold scripts, CI workflows, editor tasks, environment
 *   hooks, git internals, and agent settings.
 */
class Trust
{
    /** Settings only the person sets. */
    public const PERSONAL = ['scaffold.ide', 'scaffold.positron', 'ai.assistants'];

    /** Paths, or folders ending in "/", that other people's content can't write. */
    public const UNTRUSTED_PATHS = [
        '.Rprofile', 'scaffold.R', 'scaffold.py', 'sitecustomize.py', 'usercustomize.py', '.envrc',
        '.github/workflows/', '.vscode/tasks.json', '.vscode/settings.json', '.claude/', '.agents/', '.git/', '.githooks/',
    ];

    /** A layer without the person's own settings. */
    public static function withoutPersonal(array $layer): array
    {
        foreach (self::PERSONAL as $path) {
            Arr::forget($layer, $path);
        }
        foreach (['scaffold', 'ai'] as $key) {
            if (($layer[$key] ?? null) === []) {
                unset($layer[$key]);
            }
        }

        return $layer;
    }

    /**
     * Files in a layer that someone else's content may not write.
     *
     * @return list<string>
     */
    public static function blockedFiles(array $layer): array
    {
        return array_values(array_filter(array_keys((array) ($layer['files'] ?? [])), function (string $path) {
            // Compare without a leading ./ or /, keeping dot files' dots
            $path = preg_replace('#^(\./|/)+#', '', str_replace('\\', '/', $path));
            foreach (self::UNTRUSTED_PATHS as $blocked) {
                if (str_ends_with($blocked, '/') ? str_starts_with($path, $blocked) : strcasecmp($path, $blocked) === 0) {
                    return true;
                }
            }

            return false;
        }));
    }
}
