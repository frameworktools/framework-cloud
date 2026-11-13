<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The folders and files a new project starts with, from its merged spec and
 * the rules each language's new() follows when it writes the project:
 * framework's R package (R/spec_create.R, R/project_create.R), framework-py
 * (create.py), and fw for Stata (internal/stata/scaffold.go). Keep it in
 * step with them.
 *
 * Entries are ['path', 'type' => dir|file, 'note' => ?string, 'when' => ?string, 'later' => bool].
 * A note says what something is when its name doesn't. "when" marks what
 * depends on something the preview can't know. A later folder is made when
 * it's first used or rendered, not with the project.
 */
class ProjectTree
{
    /** Split settings files, as R writes them; Python has no packages.yml */
    private const SPLIT_SETTINGS = ['author', 'packages', 'git', 'ai', 'scaffold', 'quarto', 'data', 'connections'];

    /**
     * @param  array<string, mixed>  $spec  from DefaultsPreview::merge()
     * @param  list<string>  $tools  the keys of the tools the project gets
     * @return list<array{path: string, type: string, note: ?string, when: ?string, later: bool}>
     */
    public static function for(array $spec, string $language, array $tools, bool $sensitive): array
    {
        $entries = [];
        $explicitFolders = array_column(array_filter((array) ($spec['extra_directories'] ?? []), 'is_array'), 'path');
        $add = function (string $path, string $type, ?string $note = null, ?string $when = null, bool $later = false) use (&$entries, $explicitFolders) {
            if (in_array($path, $explicitFolders, true)) {
                $type = 'dir';
            }
            $path = trim($path, '/');
            if ($path !== '' && $path !== '.' && ! isset($entries[$path])) {
                $entries[$path] = ['path' => $path, 'type' => $type, 'note' => $note, 'when' => $when, 'later' => $later];
            }
        };

        $type = $spec['type'] ?? 'bare';
        // Files named after the project, as the packages name them
        $slug = Str::slug((string) ($spec['name'] ?? 'project')) ?: 'project';
        $core = ($spec['core_files'] ?? $type !== 'bare') !== false;
        // R leaves out only .env and scaffold.R without core files; the bare
        // structure alone skips agent files, the workspace, and Quarto.
        // Python and fw still treat no core files as bare.
        $extras = $language === 'r' ? $type !== 'bare' : $core;
        $directories = array_filter((array) ($spec['directories'] ?? []), 'is_string');
        $renderDirs = array_filter((array) ($spec['render_dirs'] ?? []), 'is_string');
        $git = (bool) ($spec['git']['use_git'] ?? true);
        $agents = (bool) ($spec['ai']['enabled'] ?? true);
        $skills = array_keys((array) ($spec['skills'] ?? []));
        // Editor files follow scaffold.ide: every choice but none gets the .Rproj (also the root
        // marker here::here() needs without .git), and VS Code and Positron add a .code-workspace
        $ide = (string) ($spec['scaffold']['ide'] ?? 'rstudio');
        $rstudio = $ide !== 'none';
        $vscode = in_array($ide, ['vscode', 'positron', 'both'], true);

        if ($language === 'stata') {
            // fw makes every folder now, outputs included, and ignores render folders
            foreach ($directories as $path) {
                $add($path, self::isFile($path) ? 'file' : 'dir');
            }
            // settings.yml holds the catalog and destinations, and settings.do the path globals
            $add('settings.yml', 'file');
            $add('settings.do', 'file');
            $add('.gitignore', 'file');
            // Starter do-files come from the blueprint, like any starter scripts
            if ($core) {
                $add('master.do', 'file');
            }
            if ($agents && $core) {
                // Of Framework's skills, fw ships only these for Stata
                self::agentFiles($add, $spec, array_values(array_intersect($skills, ['framework-stata', 'framework-sensitive-data'])), $sensitive);
            }
        } else {
            // R and Python make the folders now, except outputs and render folders, which come with use
            foreach ($directories as $path) {
                if (self::isFile($path)) {
                    $add($path, 'file');
                } elseif (! str_starts_with($path, 'outputs/') && $path !== 'outputs') {
                    $add($path, 'dir');
                }
            }
            foreach ($renderDirs as $key => $path) {
                str_contains((string) $key, 'notebook') ? $add($path, 'dir') : null;
            }
            foreach ([...array_values($directories), ...array_values($renderDirs)] as $path) {
                if (! self::isFile($path) && (str_starts_with($path, 'outputs/') || $path === 'outputs')) {
                    $add($path, 'dir', later: true);
                }
            }
            foreach ($renderDirs as $path) {
                $add($path, 'dir', later: true);
            }

            // The connections the project defines beyond Framework's own database, in the file that holds them
            $described = Connections::described((array) ($spec['connections'] ?? []));
            $connectionNote = ($named = [...$described['databases'], ...$described['storage_buckets']]) ? 'connections: '.implode(', ', $named) : null;
            $split = ($spec['settings_layout'] ?? (in_array($type, ['project', 'project_sensitive'], true) ? 'split' : 'flat')) === 'split';
            $add('settings.yml', 'file', $split ? null : $connectionNote);
            if ($split) {
                foreach (self::SPLIT_SETTINGS as $name) {
                    if (! ($language === 'python' && $name === 'packages')) {
                        $add("settings/{$name}.yml", 'file', $name === 'connections' ? $connectionNote : null);
                    }
                }
            }
            $add('.gitignore', 'file');

            if ($language === 'r') {
                if ($rstudio) {
                    $add("{$slug}.Rproj", 'file', null, 'named after the project');
                }
            } else {
                $add('pyproject.toml', 'file');
            }

            if ($core) {
                $variables = array_keys(Connections::variables((array) ($spec['connections'] ?? [])));
                $add('.env', 'file', 'secrets, kept out of git'.($variables ? '. Fill in '.implode(', ', $variables) : ''));
                $add($language === 'r' ? 'scaffold.R' : 'scaffold.py', 'file');
                // Python makes framework.db with the project; R makes it on the first scaffold()
                $language === 'r'
                    ? $add('framework.db', 'file', 'made by the first scaffold()', later: true)
                    : $add('framework.db', 'file');
            }
            if ($extras && $vscode) {
                $add("{$slug}.code-workspace", 'file', null, 'named after the project');
            }

            // Quarto follows R's own default for the type until the cloud names tools to clients
            $quarto = config('framework.spec_tools') ? in_array('quarto', $tools, true) : $type !== 'bare';
            // Without core files (Bare) there's no _quarto.yml. One Quarto
            // project at the root holds the shared settings, as R and Python
            // write it: a folder rendering to <output-dir>/<folder> needs no
            // file, a folder with its own format (slides) gets a _metadata.yml,
            // and a folder rendering elsewhere keeps its own _quarto.yml.
            if ($quarto && $extras) {
                $add('_quarto.yml', 'file', 'the shared Quarto settings');
                // Each rendered folder exists, with a file only where it needs one
                foreach ($renderDirs as $key => $target) {
                    $source = $directories[$key] ?? $key;
                    if (is_string($source) && $source !== '' && ! self::isFile($source)) {
                        $add($source, 'dir');
                    }
                }
                foreach (self::quartoFolders($type, $directories, $renderDirs) as [$path, $note]) {
                    $add($path, 'file', $note);
                }
            }

            if (in_array('renv', $tools, true) && $language === 'r') {
                $add('renv', 'dir', 'set up by renv::init()');
            }

            if ($extras && $agents) {
                self::agentFiles($add, $spec, $skills, $sensitive);
            }
        }

        if ($git) {
            $add('.git', 'dir', 'with a first commit');
        }

        // Folders and files the layers add, such as a manuscript or slides
        foreach ((array) ($spec['extra_directories'] ?? []) as $dir) {
            if (is_string($dir['path'] ?? null)) {
                $add($dir['path'], 'dir');
            }
        }
        foreach (array_keys((array) ($spec['files'] ?? [])) as $path) {
            $add((string) $path, 'file');
        }

        return array_values($entries);
    }

    /** What an agent folder holds, for its tooltip. */
    /**
     * The context file and each agent's own files, as R, Python, and fw write
     * them: AGENTS.md, a CLAUDE.md for Claude Code that imports it, Copilot's
     * instructions, and the skills in .claude/ for Claude Code and .agents/
     * for the others. Agent folders show alone, with what's inside in their tooltips.
     *
     * @param  list<string>  $skills
     */
    private static function agentFiles(callable $add, array $spec, array $skills, bool $sensitive): void
    {
        $agents = array_values(array_intersect(ProjectDefaults::AGENTS, array_keys(array_filter((array) ($spec['ai']['assistants'] ?? [])))));
        $claude = in_array('claude', $agents, true);
        $add('AGENTS.md', 'file', 'the instructions agents read');
        if ($claude) {
            $add('CLAUDE.md', 'file', 'imports AGENTS.md for Claude Code');
        }
        if (in_array('copilot', $agents, true)) {
            $add('.github', 'dir', 'copilot-instructions.md, which points Copilot to AGENTS.md');
        }
        if ($claude && ($skills || $sensitive)) {
            $add('.claude', 'dir', self::inside($skills, $sensitive));
        }
        // Codex and Copilot read .agents/skills, where skills go with no agent chosen too
        if ($skills && (! $agents || array_diff($agents, ['claude']))) {
            $add('.agents', 'dir', self::inside($skills, false));
        }
    }

    /**
     * Quarto files beneath the root, as quarto_generate_all() decides: the
     * output folder is the parent most render targets share, and only folders
     * that don't fit it, or need another format, get a file.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function quartoFolders(string $type, array $directories, array $renderDirs): array
    {
        $pairs = [];
        foreach ($renderDirs as $key => $target) {
            $source = $directories[$key] ?? $key;
            if (! is_string($target) || $target === '' || ! is_string($source) || $source === '' || self::isFile($source)) {
                continue;
            }
            $parent = match (true) {
                $target === $source => '.',
                str_ends_with($target, "/{$source}") => substr($target, 0, -strlen($source) - 1),
                default => null,
            };
            $pairs[$key] = [$source, $parent];
        }

        $counts = array_count_values(array_filter(array_column($pairs, 1), 'is_string'));
        arsort($counts);
        $outputDir = array_key_first($counts) ?? '.';
        $rootFormat = $type === 'presentation' ? 'revealjs' : 'html';

        $files = [];
        foreach ($pairs as $key => [$source, $parent]) {
            $format = preg_match('/slides?/i', (string) $key) ? 'revealjs' : 'html';
            if ($parent !== $outputDir) {
                $files[] = ["{$source}/_quarto.yml", 'its own Quarto project, for its own output folder'];
            } elseif ($format !== $rootFormat) {
                $files[] = ["{$source}/_metadata.yml", "renders as {$format}, with the root's other settings"];
            }
        }

        return $files;
    }

    private static function inside(array $skills, bool $settings): string
    {
        return implode('; ', array_filter([
            $skills ? 'skills/: '.implode(', ', $skills) : null,
            $settings ? 'settings.json, which keeps private folders from Claude Code' : null,
        ]));
    }

    public static function isFile(string $path): bool
    {
        return str_contains(basename($path), '.');
    }
}
