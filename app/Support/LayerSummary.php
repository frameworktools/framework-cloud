<?php

namespace App\Support;

/**
 * Describes what one layer adds or removes, in plain terms, for the builder
 * and library pages. It reads a single layer and never merges layers: the
 * combined result comes from the framework R package.
 */
class LayerSummary
{
    /**
     * @return list<array{label: string, items: list<string>}>
     */
    public static function of(array $layer): array
    {
        $sections = [];
        $add = function (string $label, array $items) use (&$sections) {
            $items = array_values(array_unique(array_filter(array_map('strval', $items), 'strlen')));
            sort($items);
            if ($items !== []) {
                $sections[] = ['label' => $label, 'items' => $items];
            }
        };

        if (isset($layer['type'])) {
            $add('Project type', [$layer['type']]);
        }

        $directories = $layer['directories'] ?? [];
        $extra = $layer['extra_directories'] ?? [];
        $add('Folders', [
            ...array_values(array_filter($directories, fn ($path) => $path !== null)),
            ...array_map(fn ($d) => $d['path'], array_filter($extra, fn ($d) => is_array($d) && isset($d['path']))),
        ]);
        $add('Removes folders', array_keys([
            ...array_filter($directories, fn ($path) => $path === null),
            ...array_filter($extra, fn ($d) => $d === null),
        ]));

        $add('Renders', array_map(
            fn ($from, $to) => "{$from} → {$to}",
            array_keys($layer['render_dirs'] ?? []),
            array_values($layer['render_dirs'] ?? []),
        ));

        $packages = $layer['packages'] ?? [];
        $add('Packages', array_keys(array_filter($packages['default_packages'] ?? [], fn ($p) => $p !== null)));
        $add('Removes packages', array_keys(array_filter($packages['default_packages'] ?? [], fn ($p) => $p === null)));
        if (array_key_exists('use_renv', $packages)) {
            $add('renv', [$packages['use_renv'] ? 'On' : 'Off']);
        }

        $git = $layer['git'] ?? [];
        $add('Git hooks', array_keys(array_filter($git['hooks'] ?? [])));
        if (filled($git['gitignore_content'] ?? null)) {
            $add('.gitignore', ['Adds rules']);
        }

        $assistants = $layer['ai']['assistants'] ?? [];
        $add('AI assistants', array_keys(array_filter($assistants, fn ($on) => $on === true)));
        $add('Removes AI assistants', array_keys(array_filter($assistants, fn ($on) => $on !== true)));

        $add('Skills', array_map(
            fn ($name, $skill) => match (true) {
                $skill === null => "{$name} (removed)",
                ! empty($skill['package']) => $name,
                default => "{$name} (custom)",
            },
            array_keys($layer['skills'] ?? []),
            array_values($layer['skills'] ?? []),
        ));

        $add('Files', array_map(
            fn ($path, $file) => $file === null ? "{$path} (removed)" : $path,
            array_keys($layer['files'] ?? []),
            array_values($layer['files'] ?? []),
        ));

        if (array_key_exists('sensitive', $layer['security'] ?? [])) {
            $add('Sensitive data', [$layer['security']['sensitive'] ? 'Yes' : 'No']);
        }

        // With the flag on, the framework package keeps assistants out of every
        // folder with a "private" segment (cut there) and .env. Claude Code gets
        // deny rules and a sandbox; the others get written rules only.
        if (($layer['security']['sensitive'] ?? false) === true) {
            $paths = [
                ...array_values($directories),
                ...array_map(fn ($d) => is_array($d) ? ($d['path'] ?? null) : null, array_values($extra)),
            ];
            $private = array_filter($paths, fn ($path) => is_string($path) && preg_match('#(^|/)private(/|$)#', $path));
            $add('Kept from AI assistants', [
                ...($private ? array_map(fn ($path) => preg_replace('#((^|/)private)(/.*)?$#', '$1/', $path), $private) : ['Folders with "private" in their path']),
                '.env',
            ]);
            $sections[] = ['label' => 'Protection', 'items' => [
                'Claude Code: enforced (deny rules and sandbox in .claude/settings.json)',
                'Codex and Copilot: guidance only (AGENTS.md rule and the sensitive-data skill)',
            ]];
        }

        foreach (['scaffold', 'quarto', 'connections', 'env', 'data_hooks'] as $key) {
            if (! empty($layer[$key])) {
                $add(ucfirst(str_replace('_', ' ', $key)), array_keys($layer[$key]));
            }
        }

        return $sections;
    }
}
