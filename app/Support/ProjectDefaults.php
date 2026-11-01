<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * What a user's new projects start with when they don't choose otherwise.
 * Some of it lives in the synced settings document that the R package also
 * reads (the default blueprint, git, renv, and AI); the rest is cloud-only in
 * users.project_defaults (package sets, skill sets, tools, sensitive, and
 * git hooks).
 */
class ProjectDefaults
{
    /** The blueprint for an account that never chose one: the standard project, as in R. */
    public const BLUEPRINT = 'project';

    /** The language the Project defaults pages start from when the person hasn't picked one. */
    public const LANGUAGE = 'r';

    /**
     * Editor files for an account that never chose: VS Code and Positron's
     * .code-workspace. Saved as rstudio, vscode, both, or none; older
     * accounts may hold positron, which means the same as vscode.
     */
    public const IDE = 'vscode';

    /**
     * What scaffold() does at the start of every notebook and script, for an
     * account that never chose: seed with 123, as R does, and leave the
     * ggplot2 theme alone. The theme is R only; R sources functions/, and
     * Python puts it on the import path.
     */
    public const SCAFFOLD = [
        'seed_on_scaffold' => true,
        'seed' => '123',
        'set_theme_on_scaffold' => false,
        'ggplot_theme' => 'theme_minimal',
        'source_all_functions' => true,
    ];

    /** The ggplot2 themes the Scaffold page offers. */
    public const GGPLOT_THEMES = ['theme_minimal', 'theme_bw', 'theme_classic', 'theme_light', 'theme_gray', 'theme_linedraw', 'theme_dark', 'theme_void'];

    /**
     * The coding agents Framework sets projects up for, as R, Python, and fw
     * name them. An account that never chose has none: the project still gets
     * AGENTS.md, and skills go to .agents/skills.
     */
    public const AGENTS = ['claude', 'codex', 'copilot'];

    public const AGENTS_DEFAULT = [];

    public const AGENT_NAMES = ['claude' => 'Claude Code', 'codex' => 'Codex', 'copilot' => 'GitHub Copilot'];

    /** The git hooks a user can turn on for new projects, as the R package names them. */
    public const HOOKS = ['data_security', 'check_sensitive_dirs'];

    /**
     * @return array{blueprint: string, package_sets: list<string>, skill_sets: list<string>, packages: array<string, list<array<string, mixed>>>, tools: array<string, bool>, settings: array{sensitive: bool, git: bool, ai: bool, hooks: list<string>, ide: string, scaffold: array<string, bool|string>, agents: list<string>}}
     */
    public static function for(User $user): array
    {
        $doc = $user->globalSettings()->document;
        $own = $user->project_defaults ?? [];

        return [
            'blueprint' => 'framework/'.Arr::get($doc, 'defaults.project_type', self::BLUEPRINT),
            'package_sets' => array_values($own['package_sets'] ?? []),
            'skill_sets' => array_values($own['skill_sets'] ?? []),
            'packages' => self::packages($own['packages'] ?? self::DEFAULT_PACKAGES),
            'tools' => ['renv' => (bool) Arr::get($doc, 'defaults.use_renv', false)] + ($own['tools'] ?? []),
            'settings' => [
                'sensitive' => (bool) ($own['sensitive'] ?? false),
                'git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'ai' => (bool) Arr::get($doc, 'ai.enabled', true),
                'hooks' => array_keys(array_filter(Arr::only($own['hooks'] ?? [], self::HOOKS))),
                'ide' => (string) Arr::get($doc, 'defaults.ide', self::IDE),
                'scaffold' => self::scaffold((array) ($doc['defaults'] ?? [])),
                'agents' => self::agents(Arr::get($doc, 'ai.assistants')),
            ],
        ];
    }

    /**
     * The scaffold settings from the synced document's defaults or the form's values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, bool|string>
     */
    public static function scaffold(array $values): array
    {
        $seed = trim((string) ($values['seed'] ?? ''));
        $theme = (string) ($values['ggplot_theme'] ?? '');

        return [
            'seed_on_scaffold' => (bool) ($values['seed_on_scaffold'] ?? self::SCAFFOLD['seed_on_scaffold']),
            'seed' => preg_match('/^-?\d{1,9}$/', $seed) ? $seed : self::SCAFFOLD['seed'],
            'set_theme_on_scaffold' => (bool) ($values['set_theme_on_scaffold'] ?? self::SCAFFOLD['set_theme_on_scaffold']),
            'ggplot_theme' => in_array($theme, self::GGPLOT_THEMES, true) ? $theme : self::SCAFFOLD['ggplot_theme'],
            'source_all_functions' => (bool) ($values['source_all_functions'] ?? self::SCAFFOLD['source_all_functions']),
        ];
    }

    /**
     * The agents a person uses, in AGENTS order. Never saved means none.
     *
     * @return list<string>
     */
    public static function agents(mixed $agents): array
    {
        return is_array($agents) ? array_values(array_intersect(self::AGENTS, $agents)) : self::AGENTS_DEFAULT;
    }

    /** The languages with a default package list: Stata has no packages. */
    public const PACKAGE_LANGUAGES = ['r', 'python'];

    /**
     * Package lists for an account that never made its own: the R package's
     * defaults (inst/settings/global-settings-default.yml), with the rest of
     * the core tidyverse and lme4. Python starts empty, as framework-py does.
     */
    public const DEFAULT_PACKAGES = [
        'r' => [
            ['name' => 'dplyr', 'source' => 'cran', 'auto_attach' => true],
            ['name' => 'ggplot2', 'source' => 'cran', 'auto_attach' => true],
            ['name' => 'tidyr', 'source' => 'cran', 'auto_attach' => true],
            ['name' => 'stringr', 'source' => 'cran', 'auto_attach' => true],
            ['name' => 'readr', 'source' => 'cran', 'auto_attach' => true],
            ['name' => 'purrr', 'source' => 'cran', 'auto_attach' => false],
            ['name' => 'tibble', 'source' => 'cran', 'auto_attach' => true],
            ['name' => 'forcats', 'source' => 'cran', 'auto_attach' => true],
            ['name' => 'lubridate', 'source' => 'cran', 'auto_attach' => false],
            ['name' => 'glue', 'source' => 'cran', 'auto_attach' => false],
            ['name' => 'here', 'source' => 'cran', 'auto_attach' => false],
            ['name' => 'lme4', 'source' => 'cran', 'auto_attach' => false],
        ],
        'python' => [],
    ];

    /**
     * Default package lists, one per language, as rows in the order the
     * person put them: name, source, and for R whether scaffold() attaches it.
     *
     * @return array<string, list<array{name: string, source: string, auto_attach: bool, version: ?string, repo: ?string}>>
     */
    public static function packages(array $lists): array
    {
        $out = [];
        foreach (self::PACKAGE_LANGUAGES as $language) {
            $out[$language] = collect($lists[$language] ?? [])->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
                ->map(fn (array $row) => [
                    'name' => trim((string) $row['name']),
                    'source' => (string) ($row['source'] ?? ($language === 'python' ? 'pypi' : 'cran')),
                    'auto_attach' => $language === 'r' && (bool) ($row['auto_attach'] ?? false),
                    'version' => filled($row['version'] ?? null) ? trim((string) $row['version']) : null,
                    'repo' => filled($row['repo'] ?? null) ? trim((string) $row['repo']) : null,
                ])->unique('name')->values()->all();
        }

        return $out;
    }

    /**
     * A package list as the layer spec's packages.default_packages map.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function packageLayer(array $rows): array
    {
        return collect($rows)->mapWithKeys(fn (array $row) => [$row['name'] => array_filter([
            'source' => $row['source'],
            'version' => $row['version'] ?? null,
            'repo' => $row['source'] === 'github' ? ($row['repo'] ?? null) : null,
            'auto_attach' => (bool) $row['auto_attach'],
        ], fn ($value) => $value !== null)])->all();
    }

    /**
     * The same defaults from the Project defaults form's values, saved or
     * not, for previewing what a new project would get.
     *
     * @param  array<string, mixed>  $values  as DefaultsController hands them to the form
     * @return array{blueprint: string, package_sets: list<string>, skill_sets: list<string>, packages: array<string, list<array<string, mixed>>>, tools: array<string, bool>, settings: array{sensitive: bool, git: bool, ai: bool, hooks: list<string>, ide: string, scaffold: array<string, bool|string>, agents: list<string>}}
     */
    public static function fromValues(array $values): array
    {
        return [
            'blueprint' => 'framework/'.($values['project_type'] ?? self::BLUEPRINT),
            'package_sets' => array_values(array_filter((array) ($values['package_sets'] ?? []), 'is_string')),
            'skill_sets' => array_values(array_filter((array) ($values['skill_sets'] ?? []), 'is_string')),
            'packages' => self::packages((array) ($values['packages'] ?? [])),
            'tools' => ['renv' => (bool) ($values['use_renv'] ?? false)] + array_map('boolval', Arr::except((array) ($values['tools'] ?? []), ['renv'])),
            'settings' => [
                'sensitive' => (bool) ($values['sensitive'] ?? false),
                'git' => (bool) ($values['use_git'] ?? true),
                'ai' => (bool) ($values['ai_enabled'] ?? true),
                'hooks' => array_keys(array_filter(Arr::only((array) ($values['hooks'] ?? []), self::HOOKS))),
                'ide' => (string) ($values['ide'] ?? self::IDE),
                'scaffold' => self::scaffold($values),
                'agents' => self::agents($values['ai_agents'] ?? null),
            ],
        ];
    }

    /**
     * The choices a resolve starts from. Without a blueprint or structure,
     * the default blueprint and the preferred pieces apply. Settings always
     * fall back to the defaults, with the blueprint's own settings in between.
     *
     * @param  array<string, mixed>  $choices
     * @param  array<string, mixed>|null  $defaults  from fromValues() to preview unsaved defaults
     * @return array<string, mixed>
     */
    public static function apply(User $user, array $choices, ?array $defaults = null): array
    {
        $defaults ??= self::for($user);

        if (blank($choices['blueprint'] ?? null) && blank($choices['structure'] ?? null)) {
            $choices['blueprint'] = $defaults['blueprint'];
            $choices['package_sets'] = array_values(array_unique([...$defaults['package_sets'], ...($choices['package_sets'] ?? [])]));
            $choices['skill_sets'] = array_values(array_unique([...$defaults['skill_sets'], ...($choices['skill_sets'] ?? [])]));
            $choices['tools'] = ($choices['tools'] ?? []) + $defaults['tools'];
            // A list left as Framework's own needs nothing sent: R starts every project with it
            $choices['default_packages'] = array_filter($defaults['packages'], fn (array $rows, string $language) => $rows !== self::packages(self::DEFAULT_PACKAGES)[$language], ARRAY_FILTER_USE_BOTH);
            $choices['from_defaults'] = true;
        }

        $choices['default_settings'] = $defaults['settings'];

        return $choices;
    }
}
