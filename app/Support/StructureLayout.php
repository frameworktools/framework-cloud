<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Free folder paths with a separate mapping for Framework's runtime locations. */
class StructureLayout
{
    public const LOCATIONS = ['functions', 'cache'];

    private static function isFolder(string $path, ?string $key = null): bool
    {
        return in_array($key, self::LOCATIONS, true) || ! preg_match('/\.(qmd|rmd|r|py|do|ipynb|md|html|csv|json|yml|yaml)$/i', $path);
    }

    public static function choices(array $layer): array
    {
        $paths = [];
        $add = function (string $path) use (&$paths) {
            if ($path === '.' || $path === '') {
                return;
            }
            $parts = explode('/', trim($path, '/'));
            while ($parts) {
                $paths[implode('/', $parts)] = true;
                array_pop($parts);
            }
        };
        foreach ($layer['directories'] ?? [] as $key => $path) {
            if (is_string($path) && self::isFolder($path, $key)) {
                $add($path);
            } elseif (is_string($path) && str_contains($path, '/')) {
                $add(dirname($path));
            }
        }
        foreach ($layer['extra_directories'] ?? [] as $entry) {
            if (is_array($entry) && is_string($entry['path'] ?? null)) {
                $add($entry['path']);
            }
        }
        foreach ($layer['render_dirs'] ?? [] as $path) {
            if (is_string($path)) {
                $add($path);
            }
        }
        if (is_string($layer['quarto']['render_dir'] ?? null)) {
            $add($layer['quarto']['render_dir']);
        }
        foreach (array_keys($layer['files'] ?? []) as $path) {
            if (str_contains($path, '/')) {
                $add(dirname($path));
            }
        }
        $folders = array_keys($paths);
        sort($folders, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'folders' => array_map(fn ($path) => ['path' => $path, 'original_path' => $path], $folders),
            'framework_locations' => array_intersect_key($layer['directories'] ?? [], array_flip(self::LOCATIONS)),
        ];
    }

    public static function apply(array $layer, array $folders, array $locations): array
    {
        $paths = [];
        $moves = [];
        foreach ($folders as $i => $folder) {
            $path = trim($folder['path']);
            if ($path === '' || strlen($path) > 255 || str_starts_with($path, '/') || preg_match('/^[a-z]:|[\\\\\x00-\x1f]/i', $path)
                || array_intersect(explode('/', $path), ['', '.', '..'])) {
                throw ValidationException::withMessages(["folders.{$i}.path" => 'Use a relative path without empty, . or .. segments.']);
            }
            if (isset($paths[$path])) {
                throw ValidationException::withMessages(["folders.{$i}.path" => 'Each folder path must be unique.']);
            }
            $paths[$path] = true;
            if ($original = $folder['original_path'] ?? null) {
                $moves[$original] = $path;
            }
        }
        uksort($moves, fn ($a, $b) => strlen($b) <=> strlen($a));
        $move = function (string $path) use ($moves) {
            foreach ($moves as $from => $to) {
                if ($path === $from || str_starts_with($path, $from.'/')) {
                    return $to.substr($path, strlen($from));
                }
            }

            return $path;
        };
        $directories = [];
        foreach ($layer['directories'] ?? [] as $key => $path) {
            if (in_array($key, self::LOCATIONS, true)) {
                continue;
            }
            if (! is_string($path)) {
                $directories[$key] = $path;
            } elseif (! self::isFolder($path, $key)) {
                $directories[$key] = $move($path);
            } elseif (isset($paths[$move($path)])) {
                $directories[$key] = $move($path);
            }
        }
        foreach (self::LOCATIONS as $key) {
            $path = $locations[$key] ?? null;
            if ($path !== null && $path !== '') {
                if ($path !== '.' && ! isset($paths[$path])) {
                    throw ValidationException::withMessages(["framework_locations.{$key}" => 'Choose an existing folder or the project root.']);
                }
                $directories[$key] = $path;
            }
        }
        $extras = [];
        $covered = [];
        foreach ($layer['extra_directories'] ?? [] as $key => $entry) {
            if (is_array($entry) && isset($entry['path']) && isset($paths[$move($entry['path'])])) {
                $entry['path'] = $move($entry['path']);
                $extras[$key] = $entry;
                $covered[$entry['path']] = true;
            }
        }
        // Every chosen folder is explicit, including folders without Framework roles.
        foreach (array_keys($paths) as $path) {
            if (! isset($covered[$path])) {
                $extras['folder_'.substr(hash('sha256', $path), 0, 12)] = ['label' => basename($path), 'path' => $path];
            }
        }
        $renders = [];
        foreach ($layer['render_dirs'] ?? [] as $key => $path) {
            if ($path === null || (isset($directories[$key]) && ($path === '.' || isset($paths[$move($path)])))) {
                $renders[$key] = is_string($path) ? $move($path) : $path;
            }
        }
        $layer['directories'] = $directories;
        $layer['extra_directories'] = $extras;
        $layer['render_dirs'] = $renders;
        if (is_string($layer['quarto']['render_dir'] ?? null)) {
            $layer['quarto']['render_dir'] = $move($layer['quarto']['render_dir']);
        }

        return $layer;
    }
}
