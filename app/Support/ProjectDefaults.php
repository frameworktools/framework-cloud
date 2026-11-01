<?php

namespace App\Support;

use App\Models\Project;
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

    /**
     * Each language's workflow for an account that never chose: Quarto for
     * R, Jupyter for Python, and Scripts only for Stata, the one Stata has.
     */
    public const WORKFLOWS = ['r' => 'quarto', 'python' => 'jupyter', 'stata' => 'scripts'];

    public const WORKFLOW_KEYS = ['quarto', 'jupyter', 'scripts'];

    /**
     * The _quarto.yml settings for HTML documents and RevealJS slides, as the
     * Framework GUI offered them and R's global settings ship them.
     */
    public const QUARTO = [
        'html' => [
            'theme' => 'default',
            'embed_resources' => true,
            'toc' => true,
            'toc_depth' => 3,
            'code_fold' => false,
            'code_tools' => false,
            'highlight_style' => 'github',
        ],
        'revealjs' => [
            'theme' => 'default',
            'incremental' => false,
            'slide_number' => true,
            'controls' => true,
            'progress' => true,
            'center' => true,
            'transition' => 'slide',
            'background_transition' => 'fade',
            'highlight_style' => 'github',
        ],
    ];

    /** The choices for the Quarto settings that take one, as the GUI listed them. */
    public const QUARTO_CHOICES = [
        'html.theme' => ['default', 'cosmo', 'cerulean', 'flatly', 'journal', 'lumen', 'minty', 'pulse', 'sandstone', 'simplex', 'sketchy', 'slate', 'solar', 'spacelab', 'superhero', 'united', 'yeti'],
        'revealjs.theme' => ['default', 'beige', 'black', 'blood', 'league', 'moon', 'night', 'serif', 'simple', 'sky', 'solarized', 'white'],
        'transition' => ['none', 'fade', 'slide', 'convex', 'concave', 'zoom'],
        'highlight_style' => ['github', 'tango', 'pygments', 'kate', 'monochrome', 'espresso', 'zenburn', 'haddock', 'breezedark'],
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
    public const HOOKS = ['data_security', 'check_sensitive_dirs', 'check_notebook_outputs'];

    /**
     * @return array{blueprint: string, package_sets: list<string>, skill_sets: list<string>, packages: array<string, list<array<string, mixed>>>, tools: array<string, bool>, settings: array{git: bool, ai: bool, hooks: list<string>, ide: string, scaffold: array<string, bool|string>, agents: list<string>, quarto: array<string, array<string, mixed>>}, language: ?string, workflows: array<string, string>, rmarkdown: bool}
     */
    public static function for(User $user): array
    {
        $doc = $user->globalSettings()->document;
        $own = $user->project_defaults ?? [];

        return [
            'blueprint' => 'framework/'.Arr::get($doc, 'defaults.project_type', self::BLUEPRINT),
            // Only what the person chose: a blueprint's own language and workflow show through the rest
            'language' => in_array($own['language'] ?? null, Project::LANGUAGES, true) ? $own['language'] : null,
            'workflows' => self::chosenWorkflows(Arr::get($doc, 'defaults.workflows')),
            'rmarkdown' => Arr::get($doc, 'defaults.notebook_format') === 'rmarkdown',
            'package_sets' => array_values($own['package_sets'] ?? []),
            'skill_sets' => array_values($own['skill_sets'] ?? []),
            'packages' => self::packages($own['packages'] ?? self::DEFAULT_PACKAGES),
            'tools' => ['renv' => (bool) Arr::get($doc, 'defaults.use_renv', false)] + ($own['tools'] ?? []),
            'settings' => [
                'git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'ai' => (bool) Arr::get($doc, 'ai.enabled', true),
                'hooks' => array_keys(array_filter(Arr::only($own['hooks'] ?? [], self::HOOKS))),
                'ide' => (string) Arr::get($doc, 'defaults.ide', self::IDE),
                'scaffold' => self::scaffold((array) ($doc['defaults'] ?? [])),
                'agents' => self::agents(Arr::get($doc, 'ai.assistants')),
                'quarto' => self::quarto((array) ($doc['quarto'] ?? [])),
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
     * An opaque revision of a person's defaults: it changes whenever what
     * their new projects start with changes. Clients cache the default
     * envelope by it.
     */
    public static function revision(User $user): string
    {
        return substr(hash('sha256', json_encode(self::for($user))), 0, 16);
    }

    /**
     * Each language's workflow, from what was saved, with the initial ones
     * for languages never chosen or saved with a workflow they can't use.
     *
     * @return array{r: string, python: string, stata: string}
     */
    public static function workflows(mixed $saved): array
    {
        $out = self::WORKFLOWS;
        foreach (is_array($saved) ? $saved : [] as $language => $workflow) {
            if (isset($out[$language]) && is_string($workflow) && self::workflowFits($workflow, $language)) {
                $out[$language] = $workflow;
            }
        }

        return $out;
    }

    /**
     * Only the workflows a person chose, by language, for resolution.
     *
     * @return array<string, string>
     */
    public static function chosenWorkflows(mixed $saved): array
    {
        return array_filter(is_array($saved) ? $saved : [], fn ($workflow, $language) => is_string($workflow) && isset(self::WORKFLOWS[$language]) && self::workflowFits($workflow, $language), ARRAY_FILTER_USE_BOTH);
    }

    /** Whether a language has a workflow: Stata has only Scripts only. */
    public static function workflowFits(string $workflow, string $language): bool
    {
        return in_array($workflow, $language === 'stata' ? ['scripts'] : self::WORKFLOW_KEYS, true);
    }

    /**
     * The notebook format a workflow gives a language. R Markdown is a
     * variant of the Quarto workflow for R. Scripts only has no notebooks;
     * the workflow piece names the script format where the packages take it.
     */
    public static function notebookFormat(string $workflow, string $language, bool $rmarkdown = false): ?string
    {
        return match ($workflow) {
            'quarto' => $language === 'r' && $rmarkdown ? 'rmarkdown' : 'quarto',
            'jupyter' => 'jupyter',
            default => null,
        };
    }

    /**
     * A person's defaults as a layer for one language, under creation
     * choices and above the blueprint's own values. It holds what the
     * person set that differs from Framework's own, so a blueprint's values
     * show through wherever the person kept Framework's. Editor files and
     * agents are the person's alone, so they're always here.
     *
     * Tools, package sets, and skill sets are pieces; the resolver adds them.
     *
     * @param  array<string, mixed>  $defaults  from for() or fromValues()
     * @return array<string, mixed>
     */
    public static function personalLayer(array $defaults, string $language): array
    {
        $settings = $defaults['settings'];
        $layer = [];

        if ($language !== 'stata') {
            $layer['scaffold']['ide'] = $settings['ide'];
            $keys = $language === 'r' ? array_keys(self::SCAFFOLD) : ['seed_on_scaffold', 'seed', 'source_all_functions'];
            foreach ($keys as $key) {
                if ($settings['scaffold'][$key] !== self::SCAFFOLD[$key]) {
                    $layer['scaffold'][$key] = $settings['scaffold'][$key];
                }
            }
            foreach (self::QUARTO as $format => $values) {
                foreach ($values as $key => $value) {
                    if ($settings['quarto'][$format][$key] !== $value) {
                        $layer['quarto'][$format][$key] = $settings['quarto'][$format][$key];
                    }
                }
            }
        }

        $layer['ai']['assistants'] = array_combine(self::AGENTS, array_map(fn (string $a) => in_array($a, $settings['agents'], true), self::AGENTS));
        if (! $settings['ai']) {
            $layer['ai']['enabled'] = false;
        }

        if (! $settings['git']) {
            $layer['git']['use_git'] = false;
        } elseif ($settings['hooks'] && $language !== 'stata') {
            $layer['git']['hooks'] = array_fill_keys($settings['hooks'], true);
        }

        return $layer;
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

    /**
     * The Quarto settings from the synced document or the form, each one
     * checked against its kind and choices, with R's defaults for the rest.
     *
     * @param  array<string, mixed>  $quarto
     * @return array{html: array<string, mixed>, revealjs: array<string, mixed>}
     */
    public static function quarto(array $quarto): array
    {
        $choices = fn (string $format, string $key) => self::QUARTO_CHOICES["{$format}.{$key}"]
            ?? self::QUARTO_CHOICES[str_ends_with($key, 'transition') ? 'transition' : $key] ?? null;

        return collect(self::QUARTO)->map(fn (array $defaults, string $format) => collect($defaults)->map(function ($default, string $key) use ($quarto, $format, $choices) {
            $value = $quarto[$format][$key] ?? null;

            return match (true) {
                is_bool($default) => is_bool($value) ? $value : $default,
                is_int($default) => is_numeric($value) && (int) $value >= 1 && (int) $value <= 6 ? (int) $value : $default,
                default => in_array($value, $choices($format, $key) ?? [], true) ? $value : $default,
            };
        })->all())->all();
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
     * @return array{blueprint: string, package_sets: list<string>, skill_sets: list<string>, packages: array<string, list<array<string, mixed>>>, tools: array<string, bool>, settings: array{git: bool, ai: bool, hooks: list<string>, ide: string, scaffold: array<string, bool|string>, agents: list<string>, quarto: array<string, array<string, mixed>>}, language: ?string, workflows: array<string, string>, rmarkdown: bool}
     */
    public static function fromValues(array $values): array
    {
        return [
            'blueprint' => 'framework/'.($values['project_type'] ?? self::BLUEPRINT),
            'language' => in_array($values['language'] ?? null, Project::LANGUAGES, true) ? $values['language'] : null,
            'workflows' => self::chosenWorkflows($values['workflows'] ?? null),
            'rmarkdown' => (bool) ($values['rmarkdown'] ?? false),
            'package_sets' => array_values(array_filter((array) ($values['package_sets'] ?? []), 'is_string')),
            'skill_sets' => array_values(array_filter((array) ($values['skill_sets'] ?? []), 'is_string')),
            'packages' => self::packages((array) ($values['packages'] ?? [])),
            'tools' => ['renv' => (bool) ($values['use_renv'] ?? false)] + array_map('boolval', Arr::except((array) ($values['tools'] ?? []), ['renv'])),
            'settings' => [
                'git' => (bool) ($values['use_git'] ?? true),
                'ai' => (bool) ($values['ai_enabled'] ?? true),
                'hooks' => array_keys(array_filter(Arr::only((array) ($values['hooks'] ?? []), self::HOOKS))),
                'ide' => (string) ($values['ide'] ?? self::IDE),
                'scaffold' => self::scaffold($values),
                'agents' => self::agents($values['ai_agents'] ?? null),
                'quarto' => self::quarto((array) ($values['quarto'] ?? [])),
            ],
        ];
    }
}
