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
            $items = array_values(array_filter(array_map('strval', $items), 'strlen'));
            if ($label !== 'AI assistants') {
                sort($items);
            }
            if ($items !== []) {
                $sections[] = ['label' => $label, 'items' => $items];
            }
        };

        if (isset($layer['type'])) {
            $add('Project type', [$layer['type']]);
        }

        $directories = $layer['directories'] ?? [];
        $add('Folders', [
            ...array_values(array_filter($directories, fn ($path) => $path !== null)),
            ...array_map(fn ($d) => $d['path'] ?? $d['key'] ?? '', $layer['extra_directories'] ?? []),
        ]);
        $add('Removes folders', array_keys(array_filter($directories, fn ($path) => $path === null)));

        $add('Renders', array_map(
            fn ($from, $to) => "{$from} → {$to}",
            array_keys($layer['render_dirs'] ?? []),
            array_values($layer['render_dirs'] ?? []),
        ));

        $packages = $layer['packages'] ?? [];
        $add('Packages', array_map(fn ($p) => $p['name'] ?? '', $packages['default_packages'] ?? []));
        if (array_key_exists('use_renv', $packages)) {
            $add('renv', [$packages['use_renv'] ? 'On' : 'Off']);
        }

        $git = $layer['git'] ?? [];
        $add('Git hooks', array_keys(array_filter($git['hooks'] ?? [])));
        if (filled($git['gitignore_content'] ?? null)) {
            $add('.gitignore', ['Adds rules']);
        }

        $add('AI assistants', $layer['ai']['assistants'] ?? []);

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

        foreach (['scaffold', 'quarto', 'connections', 'env', 'data_hooks'] as $key) {
            if (! empty($layer[$key])) {
                $add(ucfirst(str_replace('_', ' ', $key)), array_keys($layer[$key]));
            }
        }

        return $sections;
    }
}
