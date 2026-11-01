<?php

namespace App\Support;

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
        $add = function (string $path, string $type, ?string $note = null, ?string $when = null, bool $later = false) use (&$entries) {
            $path = trim($path, '/');
            if ($path !== '' && $path !== '.' && ! isset($entries[$path])) {
                $entries[$path] = ['path' => $path, 'type' => $type, 'note' => $note, 'when' => $when, 'later' => $later];
            }
        };

        $type = $spec['type'] ?? 'bare';
        $core = ($spec['core_files'] ?? $type !== 'bare') !== false;
        $directories = array_filter((array) ($spec['directories'] ?? []), 'is_string');
        $renderDirs = array_filter((array) ($spec['render_dirs'] ?? []), 'is_string');
        $git = (bool) ($spec['git']['use_git'] ?? true);
        $agents = (bool) ($spec['ai']['enabled'] ?? true);
        $skills = array_keys((array) ($spec['skills'] ?? []));

        if ($language === 'stata') {
            // fw makes every folder now, outputs included, and ignores render folders
            foreach ($directories as $path) {
                $add($path, self::isFile($path) ? 'file' : 'dir');
            }
            $add('settings.do', 'file');
            $add('.gitignore', 'file');
            if ($core) {
                $add('master.do', 'file');
                $do = $directories['do'] ?? 'do';
                foreach (['01_import.do', '02_clean.do', '03_analysis.do'] as $file) {
                    $add("{$do}/{$file}", 'file');
                }
            }
            if ($agents) {
                // Of Framework's skills, fw ships only these for Stata
                foreach (array_intersect($skills, ['framework-stata', 'framework-sensitive-data']) as $skill) {
                    $add(".claude/skills/{$skill}", 'dir');
                }
                if ($sensitive) {
                    $add('.claude/settings.json', 'file', 'keeps private folders from Claude Code');
                }
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

            $add('settings.yml', 'file');
            if (($spec['settings_layout'] ?? (in_array($type, ['project', 'project_sensitive'], true) ? 'split' : 'flat')) === 'split') {
                foreach (self::SPLIT_SETTINGS as $name) {
                    if (! ($language === 'python' && $name === 'packages')) {
                        $add("settings/{$name}.yml", 'file');
                    }
                }
            }
            $add('.gitignore', 'file');

            if ($language === 'r') {
                $add('project.Rproj', 'file', null, 'named after the project');
            } else {
                $add('pyproject.toml', 'file');
            }

            if ($core) {
                $add('.env', 'file', 'secrets, kept out of git');
                $add($language === 'r' ? 'scaffold.R' : 'scaffold.py', 'file');
                if ($language === 'python') {
                    $add('framework.db', 'file');
                }
                $add('project.code-workspace', 'file', null, 'with VS Code or Positron');
            }

            // Quarto follows R's own default for the type until the cloud names tools to clients
            $quarto = config('framework.spec_tools') ? in_array('quarto', $tools, true) : $type !== 'bare';
            if ($quarto) {
                $add('_quarto.yml', 'file');
                foreach (array_keys($renderDirs) as $key) {
                    $source = $directories[$key] ?? $key;
                    if (! self::isFile($source)) {
                        $add("{$source}/_quarto.yml", 'file');
                    }
                }
            }

            if (in_array('renv', $tools, true) && $language === 'r') {
                $add('renv', 'dir', 'set up by renv::init()');
            }

            if ($core && $agents) {
                $add('AGENTS.md', 'file');
                $add('CLAUDE.md', 'file', null, 'with Claude Code');
                $add('.agents/skills', 'dir', null, 'or .claude/skills/ with Claude Code');
                foreach ($skills as $skill) {
                    $add(".agents/skills/{$skill}", 'dir');
                }
                if ($sensitive) {
                    $add('.claude/settings.json', 'file', 'keeps private folders from Claude Code', 'with Claude Code');
                }
            }
        }

        if ($git) {
            $add('.git', 'dir', 'with a first commit');
        }
        $add('.framework/spec.json', 'file', 'the layers it was made from');

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

    private static function isFile(string $path): bool
    {
        return str_contains(basename($path), '.');
    }
}
