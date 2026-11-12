<?php

namespace App\Support;

/**
 * What a project structure alone gives a project, for the Project structures
 * page: its folders and core files, and where its documents render. Editor,
 * agent, Git, and package files come from blueprints and defaults, so they're
 * left out. Anything that depends on the language or on Quarto is marked
 * with when it applies.
 */
class StructurePreview
{
    /**
     * @param  array<string, mixed>  $layer  the structure version's layer
     * @return array<string, mixed>
     */
    public static function of(array $layer): array
    {
        // Only the structure: no editor files, agents, or Git
        $spec = array_replace_recursive(['name' => 'project'], $layer, ['scaffold' => ['ide' => 'none'], 'ai' => ['enabled' => false], 'git' => ['use_git' => false]]);
        $sensitive = (bool) ($layer['security']['sensitive'] ?? false);
        $tree = fn (string $language, bool $quarto) => collect(ProjectTree::for($spec, $language, $quarto ? ['quarto'] : [], $sensitive))->keyBy('path');

        $r = $tree('r', false);
        $python = $tree('python', false);
        $quartoPaths = $tree('r', true)->keys()->merge($tree('python', true)->keys())->diff($r->keys()->merge($python->keys()));

        $entries = $r->union($python)->union($tree('r', true))->union($tree('python', true))
            ->map(function (array $entry) use ($r, $python, $quartoPaths) {
                $entry['when'] = match (true) {
                    $quartoPaths->contains($entry['path']) => 'with Quarto',
                    ! $python->has($entry['path']) => 'R only',
                    ! $r->has($entry['path']) => 'Python only',
                    default => $entry['when'],
                };

                return $entry;
            })->values()->all();

        $directories = array_filter((array) ($layer['directories'] ?? []), 'is_string');
        $renders = collect((array) ($layer['render_dirs'] ?? []))
            ->filter(fn ($to) => is_string($to))
            ->map(fn (string $to, $key) => ['from' => $directories[$key] ?? (string) $key, 'to' => $to])
            ->values()->all();
        $renderDir = $layer['quarto']['render_dir'] ?? null;
        $split = ($layer['settings_layout'] ?? (in_array($layer['type'] ?? 'bare', ['project', 'project_sensitive'], true) ? 'split' : 'flat')) === 'split';

        return [
            'tree' => $entries,
            'folders' => count(StructureLayout::choices($layer)['folders']),
            // Each source folder and where its documents render
            'renders' => $renders,
            // Where any other Quarto document renders; "." is next to its source
            'render_default' => is_string($renderDir) ? $renderDir : null,
            'settings' => $split ? 'split' : 'single',
            'sensitive' => $sensitive,
            'private' => $sensitive ? array_values(array_unique(array_map(
                fn (string $path) => preg_replace('#/private/.*$#', '/private', $path),
                array_filter($directories, fn (string $path) => str_contains($path, '/private/')),
            ))) : [],
        ];
    }
}
