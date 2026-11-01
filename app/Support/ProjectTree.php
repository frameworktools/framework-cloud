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
        // Editor files follow scaffold.ide: .Rproj for RStudio, .code-workspace for VS Code and Positron
        // (the cloud always sends it; without it, R writes just the .Rproj, as it always did)
        $ide = (string) ($spec['scaffold']['ide'] ?? 'rstudio');
        $rstudio = in_array($ide, ['rstudio', 'both'], true);
        $vscode = in_array($ide, ['vscode', 'positron', 'both'], true);

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
                if ($rstudio) {
                    $add('project.Rproj', 'file', null, 'named after the project');
                }
            } else {
                $add('pyproject.toml', 'file');
            }

            if ($core) {
                $add('.env', 'file', 'secrets, kept out of git');
                $add($language === 'r' ? 'scaffold.R' : 'scaffold.py', 'file');
                if ($language === 'python') {
                    $add('framework.db', 'file');
                }
                if ($vscode) {
                    $add('project.code-workspace', 'file', null, 'named after the project');
                }
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
     * them: AGENTS.md, a CLAUDE.md for Claude Code that points to it (or holds
     * everything, for someone who uses only Claude Code), Copilot's
     * instructions, and the skills in .claude/ for Claude Code and .agents/
     * for the others. Agent folders show alone, with what's inside in their tooltips.
     *
     * @param  list<string>  $skills
     */
    private static function agentFiles(callable $add, array $spec, array $skills, bool $sensitive): void
    {
        $agents = array_values(array_intersect(ProjectDefaults::AGENTS, array_keys(array_filter((array) ($spec['ai']['assistants'] ?? [])))));
        $claude = in_array('claude', $agents, true);
        $claudeOnly = $agents === ['claude'] && ($spec['ai']['context_file'] ?? 'AGENTS.md') === 'CLAUDE.md';

        if ($claudeOnly) {
            $add('CLAUDE.md', 'file', 'the instructions for Claude Code');
        } else {
            $add('AGENTS.md', 'file', 'the instructions agents read');
            if ($claude) {
                $add('CLAUDE.md', 'file', 'points Claude Code to AGENTS.md');
            }
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

    private static function inside(array $skills, bool $settings): string
    {
        return implode('; ', array_filter([
            $skills ? 'skills/: '.implode(', ', $skills) : null,
            $settings ? 'settings.json, which keeps private folders from Claude Code' : null,
        ]));
    }

    private static function isFile(string $path): bool
    {
        return str_contains(basename($path), '.');
    }
}
