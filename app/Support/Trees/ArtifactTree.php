<?php

namespace App\Support\Trees;

use InvalidArgumentException;
use JsonException;

/**
 * The tree artifact: a project's folder structure, with notes on its paths.
 * Clients build it and leave ignored and private names out: an ignored or
 * private folder arrives as a stub with a file count, and ignored files as one
 * group per rule. This class validates the flat list of paths clients send and
 * builds the nested, sorted form the reader and embeds draw. See
 * docs/plans/2026-11-25-structure-artifacts.md.
 */
class ArtifactTree
{
    public const KIND = 'tree';

    public const MAX_BYTES = 1_048_576;

    public const MAX_NODES = 5000;

    public const MAX_DEPTH = 32;

    public const MAX_PATH = 1024;

    public const MAX_NOTE = 500;

    /** A file count at this number reads as "10,000+ files". */
    public const MAX_HIDDEN = 10000;

    public const METHODS = ['git', 'gitignore', 'none'];

    public const LANGUAGES = ['r', 'python', 'stata', 'shell'];

    /** Whether JSON says it is a tree, valid or not. */
    public static function claims(string $json): bool
    {
        return preg_match('/"kind"\s*:\s*"tree"/', $json) === 1;
    }

    /**
     * The normalized tree from JSON, or an exception whose message says what is wrong.
     *
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('A project structure can be at most 1 MB.');
        }
        try {
            $input = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The project structure is not valid JSON.');
        }
        if (! is_array($input) || array_is_list($input)) {
            throw new InvalidArgumentException('The project structure is not a JSON object.');
        }

        return self::normalize($input);
    }

    /** @throws InvalidArgumentException */
    public static function normalize(array $input): array
    {
        if (($input['schema'] ?? null) !== 'framework.artifact/v1' || ($input['kind'] ?? null) !== self::KIND) {
            throw new InvalidArgumentException('A project structure needs schema framework.artifact/v1 and kind tree.');
        }
        $root = $input['root'] ?? null;
        if (! is_string($root) || trim($root) === '' || mb_strlen($root) > 255 || preg_match('/[\/\\\\\x00-\x1f]/', $root)) {
            throw new InvalidArgumentException('A project structure needs root, the name of its top folder.');
        }
        $base = $input['base'] ?? '';
        if (! is_string($base)) {
            throw new InvalidArgumentException('base is the published folder\'s path in the project, or "".');
        }
        $base = trim($base, '/');
        if ($base !== '') {
            self::path($base, 'base');
        }
        $method = $input['method'] ?? null;
        if ($method !== null && ! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('method is git, gitignore, or none.');
        }
        $nodes = $input['nodes'] ?? null;
        if (! is_array($nodes) || ! array_is_list($nodes) || $nodes === []) {
            throw new InvalidArgumentException('A project structure needs nodes, a list of paths.');
        }
        if (count($nodes) > self::MAX_NODES) {
            throw new InvalidArgumentException('A project structure can have at most '.number_format(self::MAX_NODES).' paths.');
        }

        // Folders by path; each holds its entries by kind and name
        $dirs = ['' => self::folder('', '')];
        $files = [];
        $groups = [];
        $ensure = function (string $path) use (&$dirs, &$files): void {
            $parts = explode('/', $path);
            for ($i = 1; $i <= count($parts); $i++) {
                $prefix = implode('/', array_slice($parts, 0, $i));
                if (isset($files[$prefix])) {
                    throw new InvalidArgumentException("\"{$prefix}\" is both a file and a folder.");
                }
                $dirs[$prefix] ??= self::folder($prefix, $parts[$i - 1]);
            }
        };

        foreach ($nodes as $index => $node) {
            $where = 'nodes['.$index.']';
            if (! is_array($node) || array_is_list($node)) {
                throw new InvalidArgumentException("{$where} is not an object.");
            }
            $type = $node['type'] ?? null;
            if (! in_array($type, ['dir', 'file', 'ignored'], true)) {
                throw new InvalidArgumentException("{$where} needs type dir, file, or ignored.");
            }
            $raw = $node['path'] ?? null;
            if (! is_string($raw)) {
                throw new InvalidArgumentException("{$where} needs path.");
            }
            $path = trim($raw, '/');
            if ($path !== '' || $type !== 'ignored') {
                self::path($path, $where);
            }
            $extra = array_filter([
                'note' => self::note($node['note'] ?? null, $where),
                'ignored' => self::rule($node['ignored'] ?? null, $where, $type === 'ignored'),
                'private' => ($node['private'] ?? false) === true ? true : null,
                'depth' => ($node['depth'] ?? false) === true ? true : null,
                'hidden' => self::hidden($node['hidden'] ?? null, $where),
            ], fn ($value) => $value !== null);

            if ($type === 'ignored') {
                if ($path !== '') {
                    $ensure($path);
                }
                if (! isset($extra['hidden'])) {
                    throw new InvalidArgumentException("{$where} needs hidden, the number of files the rule hides.");
                }
                $name = rtrim($extra['ignored']['rule'], '/');
                $groups[$path][$name] = ['name' => $name, 'path' => $path, 'type' => 'ignored'] + array_intersect_key($extra, array_flip(['note', 'ignored', 'hidden']));

                continue;
            }
            if ($type === 'file') {
                if (isset($dirs[$path])) {
                    throw new InvalidArgumentException("\"{$path}\" is both a file and a folder.");
                }
                $parent = self::parent($path);
                if ($parent !== '') {
                    $ensure($parent);
                }
                $files[$path] = ['name' => self::name($path), 'path' => $path, 'type' => 'file'] + array_intersect_key($extra, array_flip(['note']));

                continue;
            }
            $ensure($path);
            $dirs[$path] = array_merge($dirs[$path], $extra);
        }

        // Stubs list nothing: their contents stay on the producer's computer
        foreach (array_merge(array_keys($dirs), array_keys($files), array_keys($groups)) as $path) {
            foreach (self::ancestors((string) $path) as $ancestor) {
                if ($ancestor !== $path && isset($dirs[$ancestor]['hidden'])) {
                    throw new InvalidArgumentException("\"{$path}\" is inside \"{$ancestor}\", whose contents aren't listed.");
                }
            }
        }

        $highlight = self::pathList($input['highlight'] ?? [], 'highlight');
        $open = self::pathList($input['open'] ?? null, 'open');
        foreach ($highlight as $path) {
            if (! isset($dirs[$path]) && ! isset($files[$path])) {
                throw new InvalidArgumentException("highlight names \"{$path}\", which isn't in the tree.");
            }
            if (isset($files[$path])) {
                $files[$path]['highlight'] = true;
            } else {
                $dirs[$path]['highlight'] = true;
            }
        }
        if ($open !== null) {
            foreach ($open as $path) {
                if (! isset($dirs[$path])) {
                    throw new InvalidArgumentException("open names \"{$path}\", which isn't a folder in the tree.");
                }
            }
        }
        // Top-level folders start open unless open says otherwise; a highlighted path's folders open too
        $opened = array_flip($open ?? array_values(array_filter(array_keys($dirs), fn ($path) => $path !== '' && ! str_contains($path, '/'))));
        foreach ($highlight as $path) {
            foreach (self::ancestors(self::parent($path)) as $ancestor) {
                $opened[$ancestor] = true;
            }
        }
        foreach ($dirs as $path => &$dir) {
            $dir['open'] = $path === '' || (isset($opened[$path]) && ! isset($dir['hidden']));
        }
        unset($dir);

        // Nest: children attach to their parent folder, deepest first
        $children = [];
        foreach ($files as $path => $file) {
            $children[self::parent($path)]['files'][] = $file;
        }
        foreach ($groups as $path => $named) {
            foreach ($named as $group) {
                $children[$path]['groups'][] = $group;
            }
        }
        $paths = array_keys($dirs);
        usort($paths, fn ($a, $b) => substr_count((string) $b, '/') <=> substr_count((string) $a, '/') ?: strcmp((string) $b, (string) $a));
        $built = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            $dir = $dirs[$path];
            $sort = fn (array $list) => self::sorted($list);
            $dir['children'] = array_merge(
                $sort($children[$path]['dirs'] ?? []),
                $sort($children[$path]['files'] ?? []),
                $sort($children[$path]['groups'] ?? []),
            );
            if ($path === '') {
                $built = $dir;

                continue;
            }
            $children[self::parent($path)]['dirs'][] = $dir;
        }

        $counts = ['dirs' => count($dirs) - 1, 'files' => count($files), 'ignored' => 0, 'hidden' => 0];
        foreach ($dirs as $dir) {
            $counts['hidden'] += $dir['hidden'] ?? 0;
            $counts['ignored'] += isset($dir['ignored']) ? 1 : 0;
        }
        foreach ($groups as $named) {
            foreach ($named as $group) {
                $counts['hidden'] += $group['hidden'];
                $counts['ignored']++;
            }
        }
        $language = $input['source']['language'] ?? null;

        return array_filter([
            'schema' => 'framework.artifact/v1',
            'kind' => self::KIND,
            'source' => in_array($language, self::LANGUAGES, true) ? ['language' => $language] : null,
            'tree' => array_filter([
                'root' => trim($root),
                'base' => $base,
                'method' => $method,
                'counts' => $counts,
                'children' => $built['children'],
            ], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null);
    }

    /** The tree with ignored stubs and groups, or notes, left out: what an embed shows when those settings are off. */
    public static function without(array $data, bool $ignored, bool $notes): array
    {
        $strip = function (array $children) use (&$strip, $ignored, $notes): array {
            $out = [];
            foreach ($children as $child) {
                if ($ignored && isset($child['ignored']) && ! isset($child['private'])) {
                    continue;
                }
                if ($ignored && isset($child['ignored'])) {
                    unset($child['ignored']);
                }
                if ($notes) {
                    unset($child['note']);
                }
                if (isset($child['children'])) {
                    $child['children'] = $strip($child['children']);
                }
                $out[] = $child;
            }

            return $out;
        };
        $data['tree']['children'] = $strip($data['tree']['children']);

        return $data;
    }

    public static function hasNotes(array $data): bool
    {
        return self::any($data['tree']['children'] ?? [], fn ($child) => isset($child['note']));
    }

    public static function hasIgnored(array $data): bool
    {
        return self::any($data['tree']['children'] ?? [], fn ($child) => isset($child['ignored']));
    }

    public static function alt(array $data): string
    {
        $tree = $data['tree'];
        $plural = fn (int $n, string $word) => number_format($n).' '.$word.($n === 1 ? '' : 's');
        $text = 'Folder tree of '.$tree['root'].': '.$plural($tree['counts']['dirs'], 'folder').' and '.$plural($tree['counts']['files'], 'file');
        if ($tree['counts']['ignored'] > 0) {
            $text .= ', '.number_format($tree['counts']['ignored']).' ignored';
        }

        return $text.'.';
    }

    /** "12 files", or "10,000+ files" at the cap. */
    public static function fileCount(int $hidden): string
    {
        return $hidden >= self::MAX_HIDDEN ? number_format(self::MAX_HIDDEN).'+ files' : number_format($hidden).' '.($hidden === 1 ? 'file' : 'files');
    }

    /** Where a rule comes from, for a title: ".gitignore line 7", "global excludes file". */
    public static function ruleSource(array $rule): ?string
    {
        $source = $rule['source'] ?? null;
        if ($source === null) {
            return null;
        }
        $source = match ($source) {
            'global' => 'global excludes file',
            'info/exclude' => '.git/info/exclude',
            default => $source,
        };

        return isset($rule['line']) ? $source.' line '.$rule['line'] : $source;
    }

    /** The widest guide and name in characters, capped at 48: the note column lines up after it. */
    public static function nameWidth(array $data): int
    {
        $widest = mb_strlen($data['tree']['root']) + 1;
        $walk = function (array $children, int $depth) use (&$walk, &$widest): void {
            foreach ($children as $child) {
                $widest = max($widest, ($depth + 1) * 4 + mb_strlen($child['name']) + ($child['type'] === 'dir' ? 3 : 0));
                $walk($child['children'] ?? [], $depth + 1);
            }
        };
        $walk($data['tree']['children'], 0);

        return min($widest, 48);
    }

    private static function any(array $children, callable $test): bool
    {
        foreach ($children as $child) {
            if ($test($child) || self::any($child['children'] ?? [], $test)) {
                return true;
            }
        }

        return false;
    }

    private static function folder(string $path, string $name): array
    {
        return ['name' => $name, 'path' => $path, 'type' => 'dir'];
    }

    /** Folders first, files, then ignored groups; each by name, ignoring case. */
    private static function sorted(array $list): array
    {
        usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']) ?: strcmp($a['name'], $b['name']));

        return $list;
    }

    /** @throws InvalidArgumentException */
    private static function path(string $path, string $where): void
    {
        if ($path === '' || strlen($path) > self::MAX_PATH || str_contains($path, '\\') || str_contains($path, "\0")
            || preg_match('/[\x00-\x1f]/', $path) || in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true)
            || str_contains($path, '//')) {
            throw new InvalidArgumentException("{$where} has an invalid path: paths are relative, with / between names and no .. or backslashes.");
        }
        if (substr_count($path, '/') + 1 > self::MAX_DEPTH) {
            throw new InvalidArgumentException("{$where} is more than ".self::MAX_DEPTH.' levels deep.');
        }
        if (explode('/', $path)[0] === '.git') {
            throw new InvalidArgumentException("{$where} is inside .git, which a project structure leaves out.");
        }
    }

    private static function parent(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? '' : substr($path, 0, $slash);
    }

    private static function name(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? $path : substr($path, $slash + 1);
    }

    /** The folder and each folder above it, the root last. */
    private static function ancestors(string $path): array
    {
        $out = [];
        while ($path !== '') {
            $out[] = $path;
            $path = self::parent($path);
        }
        $out[] = '';

        return $out;
    }

    private static function note(mixed $note, string $where): ?string
    {
        if ($note === null) {
            return null;
        }
        if (! is_string($note)) {
            throw new InvalidArgumentException("{$where}'s note is not text.");
        }
        $note = trim(preg_replace('/\s+/', ' ', $note));
        if (mb_strlen($note) > self::MAX_NOTE) {
            throw new InvalidArgumentException("{$where}'s note is longer than ".self::MAX_NOTE.' characters.');
        }

        return $note === '' ? null : $note;
    }

    private static function rule(mixed $rule, string $where, bool $required): ?array
    {
        if ($rule === null || $rule === false) {
            if ($required) {
                throw new InvalidArgumentException("{$where} needs ignored, the rule that hides its files.");
            }

            return null;
        }
        if (! is_array($rule) || ! is_string($rule['rule'] ?? null) || trim($rule['rule']) === '' || strlen($rule['rule']) > self::MAX_PATH) {
            throw new InvalidArgumentException("{$where}'s ignored needs rule, the pattern that matched.");
        }
        $source = $rule['source'] ?? null;
        if ($source !== null && (! is_string($source) || str_starts_with($source, '/') || preg_match('/^[A-Za-z]:|\\\\|(^|\/)\.\.(\/|$)/', $source) || strlen($source) > self::MAX_PATH)) {
            throw new InvalidArgumentException("{$where}'s ignored source is a path relative to the root, global, or info/exclude.");
        }
        $line = $rule['line'] ?? null;
        if ($line !== null && (! is_int($line) || $line < 1)) {
            throw new InvalidArgumentException("{$where}'s ignored line is a line number.");
        }

        return array_filter(['rule' => trim($rule['rule']), 'source' => $source, 'line' => $line], fn ($value) => $value !== null);
    }

    private static function hidden(mixed $hidden, string $where): ?int
    {
        if ($hidden === null) {
            return null;
        }
        if (! is_int($hidden) || $hidden < 0) {
            throw new InvalidArgumentException("{$where}'s hidden is a count of files.");
        }

        return min($hidden, self::MAX_HIDDEN);
    }

    /** @return list<string>|null */
    private static function pathList(mixed $list, string $field): ?array
    {
        if ($list === null) {
            return null;
        }
        if (! is_array($list) || ! array_is_list($list)) {
            throw new InvalidArgumentException("{$field} is a list of paths.");
        }
        $out = [];
        foreach ($list as $path) {
            if (! is_string($path)) {
                throw new InvalidArgumentException("{$field} is a list of paths.");
            }
            $out[] = trim($path, '/');
        }

        return array_values(array_unique($out));
    }
}
