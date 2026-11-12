<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * What a user's new projects start with when they don't choose otherwise:
 * their default blueprint for the project's language, stored with their
 * default language in users.blueprint_defaults. Everything else about a
 * project comes from its blueprint, plus the author details in the synced
 * settings. Also Framework's own values beneath every blueprint.
 */
class ProjectDefaults
{
    /** The blueprint for an account that never chose one: the standard project, as in R. */
    public const BLUEPRINT = 'project';

    /** The default language for a person who hasn't picked one. */
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

    /** The key of the layer of Framework's own settings beneath every blueprint. */
    public const BASE_LAYER = 'framework defaults';

    /** The editor files a blueprint can ask for, as R and Python read scaffold.ide. Positron means vscode. */
    public const IDES = ['vscode', 'positron', 'rstudio', 'both', 'none'];

    /**
     * The person's default language: the one they chose, else the language
     * guided setup saved before blueprint defaults, else R.
     */
    public static function language(User $user): string
    {
        return self::chosenLanguage($user) ?? self::LANGUAGE;
    }

    /** The default language only when the person chose one. */
    public static function chosenLanguage(User $user): ?string
    {
        foreach ([$user->blueprint_defaults['language'] ?? null, $user->project_defaults['language'] ?? null] as $language) {
            if (in_array($language, Project::LANGUAGES, true)) {
                return $language;
            }
        }

        return null;
    }

    /**
     * The person's default blueprint for a language, with its latest version
     * loaded: the one they chose for the language, else the default saved
     * before per-language defaults (their own blueprint or a Framework
     * type), else Framework's standard project. One that no longer exists,
     * can't be seen, or doesn't take the language is passed over.
     */
    public static function blueprint(User $user, string $language): Blueprint
    {
        $with = ['owner', 'latestVersion.blueprint', 'latestVersion.base.facet.owner', 'latestVersion.facetVersions.facet.owner'];
        $candidates = [
            fn () => self::visibleBlueprint($user, $user->blueprint_defaults['blueprints'][$language] ?? null, $with),
            fn () => self::visibleBlueprint($user, $user->project_defaults['blueprint_id'] ?? null, $with, own: true),
            fn () => Blueprint::systemTypes()->where('key', Arr::get($user->exists ? $user->globalSettings()->document : [], 'defaults.project_type'))->with($with)->first(),
        ];
        foreach ($candidates as $candidate) {
            $blueprint = $candidate();
            if ($blueprint && self::fits($blueprint, $language)) {
                return $blueprint;
            }
        }

        return Blueprint::whereNull('user_id')->where('key', self::BLUEPRINT)->with($with)->firstOrFail();
    }

    private static function visibleBlueprint(User $user, mixed $id, array $with, bool $own = false): ?Blueprint
    {
        if (! is_int($id)) {
            return null;
        }
        $blueprint = Blueprint::with($with)->find($id);

        return $blueprint && ($own ? $blueprint->user_id === $user->id : self::canBeDefault($blueprint, $user)) ? $blueprint : null;
    }

    /** Whether a blueprint can be someone's default: Framework's project types and their own. */
    public static function canBeDefault(Blueprint $blueprint, User $user): bool
    {
        return ($blueprint->isSystem() && in_array($blueprint->key, Project::TYPES, true)) || ($user->exists && $blueprint->user_id === $user->id);
    }

    /** Whether a blueprint takes a language: its latest version lists it, and its structure works in it. */
    public static function fits(Blueprint $blueprint, string $language): bool
    {
        $version = $blueprint->latestVersion;
        if (! $version?->base) {
            return false;
        }
        $languages = $version->compatibility()['languages'];

        return (! $languages || in_array($language, $languages, true)) && ! Project::typeProblem($language, $version->base->layer['type'] ?? 'bare');
    }

    /** Make a blueprint the person's default for a language. */
    public static function setBlueprint(User $user, string $language, Blueprint $blueprint): void
    {
        self::save($user, null, [$language => $blueprint]);
    }

    /** Set the person's default language. */
    public static function setLanguage(User $user, string $language): void
    {
        self::save($user, $language, []);
    }

    /**
     * The folder new projects go in on the person's computer, such as
     * ~/code, from defaults.projects_dir in the synced settings. Null when
     * unset: the packages then ask, starting from the current folder.
     */
    public static function projectsDir(User $user): ?string
    {
        $dir = $user->exists ? Arr::get($user->globalSettings()->document, 'defaults.projects_dir') : null;

        return is_string($dir) && trim($dir) !== '' ? trim($dir) : null;
    }

    /** The pull response header that carries the owner's projects folder. */
    public const PROJECTS_DIR_HEADER = 'Framework-Projects-Dir';

    /**
     * A path as a header value: percent-encoded UTF-8, with / and ~ kept
     * readable, so ~/code stays ~/code. Clients percent-decode it.
     */
    public static function headerValue(string $dir): string
    {
        return str_replace('%2F', '/', rawurlencode($dir));
    }

    /** Save the projects folder in the synced settings. Null or blank removes it. */
    public static function setProjectsDir(User $user, ?string $dir): void
    {
        $dir = is_string($dir) && trim($dir) !== '' ? trim($dir) : null;
        $setting = $user->globalSettings();
        $doc = $setting->document;
        if ($dir === null) {
            Arr::forget($doc, 'defaults.projects_dir');
        } else {
            Arr::set($doc, 'defaults.projects_dir', $dir);
        }
        if ($doc !== $setting->document) {
            $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);
        }
    }

    /**
     * Validation for the projects folder: blank, or a full path. That is ~,
     * a path under ~, an absolute path, or a Windows drive or network path.
     *
     * @return list<mixed>
     */
    public static function projectsDirRules(): array
    {
        return ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
            if (! is_string($value)) {
                return;
            }
            $value = trim($value);
            if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $fail('The projects folder cannot contain line breaks or control characters.');
            } elseif (! preg_match('#^(~|~[/\\\\].*|/.*|[A-Za-z]:[/\\\\].*|\\\\\\\\.+)$#s', $value)) {
                $fail('Use a full path, such as ~/code or /Users/you/code.');
            }
        }];
    }

    /**
     * Save the default language and default blueprints by language.
     *
     * @param  array<string, Blueprint>  $blueprints
     */
    public static function save(User $user, ?string $language, array $blueprints): void
    {
        $saved = $user->blueprint_defaults ?? [];
        if ($language) {
            $saved['language'] = $language;
        }
        foreach ($blueprints as $for => $blueprint) {
            $saved['blueprints'][$for] = $blueprint->id;
        }
        $user->forceFill(['blueprint_defaults' => $saved])->save();
        self::syncSettings($user);
    }

    /**
     * Copy the default language's blueprint into the synced settings as a
     * project type and workflow, which R and Python read when they create
     * a project offline.
     */
    private static function syncSettings(User $user): void
    {
        $language = self::language($user);
        $version = self::blueprint($user, $language)->latestVersion;
        $setting = $user->globalSettings();
        $doc = $setting->document;
        $type = $version->base->layer['type'] ?? null;
        Arr::set($doc, 'defaults.project_type', in_array($type, Project::TYPES, true) ? $type : self::BLUEPRINT);
        $workflows = self::workflows(Arr::get($doc, 'defaults.workflows'));
        $workflows[$language] = self::workflowOf($version, $language);
        Arr::set($doc, 'defaults.workflows', $workflows);
        if ($language === 'r') {
            $rmarkdown = ($version->layer['scaffold']['notebook_format'] ?? null) === 'rmarkdown';
            Arr::set($doc, 'defaults.notebook_format', self::notebookFormat($workflows['r'], 'r', $rmarkdown) ?? 'quarto');
        }
        if ($doc !== $setting->document) {
            $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);
        }
    }

    /**
     * What a person's new projects start with, read from their default
     * blueprints: the shape GET /api/v1/defaults has always returned, so
     * older R and Python packages keep working. Settings and pieces describe
     * the default language's blueprint; blueprints, workflows, and packages
     * cover each language.
     *
     * @return array{publish_destination: string, blueprint: string, blueprints: array<string, string>, language: ?string, workflows: array<string, string>, rmarkdown: bool, package_sets: list<string>, skill_sets: list<string>, packages: array<string, list<array<string, mixed>>>, tools: array<string, bool>, settings: array{git: bool, ai: bool, hooks: list<string>, ide: string, scaffold: array<string, bool|string>, agents: list<string>, quarto: array<string, array<string, mixed>>}}
     */
    public static function for(User $user): array
    {
        return self::read($user)['defaults'];
    }

    /** @return array{defaults: array<string, mixed>, versions: array<string, int>} */
    private static function read(User $user): array
    {
        $language = self::chosenLanguage($user);
        $main = $language ?? self::LANGUAGE;
        $blueprints = collect(Project::LANGUAGES)->mapWithKeys(fn (string $l) => [$l => self::blueprint($user, $l)]);
        $version = $blueprints[$main]->latestVersion;
        $layer = $version->layer ?? [];
        $pieces = $version->facetVersions->filter(fn (FacetVersion $v) => ProjectLayers::fits($v, $main));
        $handles = fn (string $kind) => $pieces->filter(fn (FacetVersion $v) => $v->facet->kind === $kind)->map(fn (FacetVersion $v) => $v->facet->handle)->values()->all();
        $agents = self::agentDefaults($version);

        return [
            'versions' => $blueprints->map(fn (Blueprint $b) => $b->latestVersion->id)->all(),
            'defaults' => [
                'publish_destination' => (string) ($layer['publish']['default'] ?? ''),
                'blueprint' => $blueprints[$main]->handle,
                'blueprints' => $blueprints->map(fn (Blueprint $b) => $b->handle)->all(),
                'language' => $language,
                'workflows' => $blueprints->map(fn (Blueprint $b, string $l) => self::workflowOf($b->latestVersion, $l))->all(),
                'rmarkdown' => ($blueprints['r']->latestVersion->layer['scaffold']['notebook_format'] ?? null) === 'rmarkdown',
                'package_sets' => $handles('package_set'),
                'skill_sets' => $handles('skill_set'),
                'packages' => collect(self::PACKAGE_LANGUAGES)->mapWithKeys(fn (string $l) => [$l => self::packageRows($blueprints[$l]->latestVersion, $l)])->all(),
                'tools' => ['renv' => false] + $pieces->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool')->mapWithKeys(fn (FacetVersion $v) => [$v->facet->key => true])->all(),
                'settings' => [
                    'git' => (bool) ($layer['git']['use_git'] ?? true),
                    'ai' => ! in_array('none', $agents, true) && (bool) ($layer['ai']['enabled'] ?? true),
                    'hooks' => array_keys(array_filter(Arr::only((array) ($layer['git']['hooks'] ?? []), self::HOOKS))),
                    'ide' => self::ide($version),
                    'scaffold' => self::scaffold((array) ($layer['scaffold'] ?? [])),
                    'agents' => self::agents($agents),
                    'quarto' => self::quarto((array) ($layer['quarto'] ?? [])),
                ],
            ],
        ];
    }

    /** The workflow a blueprint version gives a language: its default, else Framework's, else the first it supports. */
    public static function workflowOf(BlueprintVersion $version, string $language): string
    {
        $compatibility = $version->compatibility();
        $supported = array_values(array_filter($compatibility['workflows'] ?? self::WORKFLOW_KEYS, fn (string $w) => self::workflowFits($w, $language)));

        return collect([$compatibility['default_workflow'][$language] ?? null, self::WORKFLOWS[$language]])
            ->first(fn ($w) => in_array($w, $supported, true)) ?? $supported[0] ?? self::WORKFLOWS[$language];
    }

    /**
     * The packages a blueprint version starts a language's projects with:
     * for R, Framework's own list beneath, then its sets, then its own list,
     * where a null takes a package out.
     *
     * @return list<array<string, mixed>>
     */
    private static function packageRows(BlueprintVersion $version, string $language): array
    {
        $entries = $language === 'r' ? self::packageLayer(self::packages(self::DEFAULT_PACKAGES)['r']) : [];
        foreach ($version->facetVersions as $piece) {
            if (ProjectLayers::fits($piece, $language)) {
                $entries = array_replace($entries, $piece->catalogLayer($language)['packages']['default_packages'] ?? []);
            }
        }
        $entries = array_filter(array_replace($entries, $version->layer['packages']['default_packages'] ?? []), fn ($entry) => $entry !== null);

        return self::packages([$language => collect($entries)->map(fn ($entry, $name) => ['name' => $name] + (array) $entry)->values()->all()])[$language];
    }

    /** The editor files a blueprint version asks for, else Framework's. */
    public static function ide(?BlueprintVersion $version): string
    {
        $ide = $version?->getAttribute('ide');

        return in_array($ide, self::IDES, true) ? $ide : self::IDE;
    }

    /**
     * The agents a blueprint version sets projects up for: named agents,
     * other (AGENTS.md only), or none. Empty when it doesn't say.
     *
     * @return list<string>
     */
    public static function agentDefaults(?BlueprintVersion $version): array
    {
        return array_values(array_intersect([...self::AGENTS, 'other', 'none'], (array) ($version?->agent_defaults ?? [])));
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
     * their new projects start with changes, including a new version of a
     * default blueprint. Clients cache the default envelope by it.
     */
    public static function revision(User $user): string
    {
        return substr(hash('sha256', json_encode(self::read($user))), 0, 16);
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
     * Framework's own settings for a project, beneath its blueprint's: the
     * scaffold settings (every key, since the packages fill a missing one
     * with their own default, which differs from Cloud's), and the editor
     * files and agents the blueprint asks for. Editor files and agents
     * never come from a blueprint's layer, so they're set here.
     *
     * @return array<string, mixed>
     */
    public static function baseLayer(string $language, ?BlueprintVersion $version): array
    {
        $layer = [];
        if ($language !== 'stata') {
            $layer['scaffold']['ide'] = self::ide($version);
            foreach ($language === 'r' ? array_keys(self::SCAFFOLD) : ['seed_on_scaffold', 'seed', 'source_all_functions'] as $key) {
                $layer['scaffold'][$key] = self::SCAFFOLD[$key];
            }
        }

        $agents = self::agentDefaults($version);
        $layer['ai']['assistants'] = array_combine(self::AGENTS, array_map(fn (string $a) => in_array($a, $agents, true), self::AGENTS));
        if (in_array('none', $agents, true)) {
            $layer['ai']['enabled'] = false;
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
     * Package lists for an account that never made its own. R starts with
     * the core tidyverse. Python starts empty, as framework-py does.
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
            'auto_attach' => (bool) ($row['auto_attach'] ?? false),
        ], fn ($value) => $value !== null)])->all();
    }
}
