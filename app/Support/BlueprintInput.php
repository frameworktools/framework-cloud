<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Blueprint choices as the builder and the API send them: a structure, tools, package sets, skill
 * sets, skills, settings, and extra YAML. Validated and resolved the same way for both, so an agent
 * saves exactly what the builder would.
 */
class BlueprintInput
{
    /**
     * The builder sends choices; the resolver turns them into the structure,
     * pieces, and settings layer a version pins. Nobody's defaults apply.
     *
     * @return array{0: array<string, mixed>, 1: FacetVersion, 2: list<int>, 3: array<string, mixed>, 4: ?string}
     */
    public static function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(Blueprint::VISIBILITIES)],
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'agents' => ['sometimes', 'array', 'min:1'],
            'agents.*' => [Rule::in([...ProjectDefaults::AGENTS, 'other', 'none'])],
            'workflow' => ['nullable', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            'notebook_format' => ['nullable', Rule::in(['quarto', 'rmarkdown', 'jupyter'])],
            'structure' => ['required', 'string', 'max:200'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'package_sets.*' => ['string', 'max:200'],
            'package_overrides' => ['nullable', 'array'],
            'package_overrides.*' => ['boolean'],
            'package_auto_attach' => ['nullable', 'array'],
            'package_auto_attach.*' => ['boolean'],
            'skill_sets' => ['nullable', 'array'],
            'skill_sets.*' => ['string', 'max:200'],
            'excluded_skills' => ['nullable', 'array'],
            'excluded_skills.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:200'],
            'settings' => ['nullable', 'array'],
            'settings.sensitive' => ['boolean'],
            'settings.git' => ['boolean'],
            'settings.ai' => ['boolean'],
            'settings.hooks' => ['nullable', 'array'],
            'settings.hooks.*' => [Rule::in(ProjectDefaults::HOOKS)],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'languages' => ['sometimes', 'array', 'min:1'],
            'languages.*' => [Rule::in(Project::LANGUAGES)],
            'workflows' => ['sometimes', 'array', 'min:1'],
            'workflows.*' => [Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            ...self::settingRules(),
        ]);
        $compatibility = self::compatibility($data);
        if (isset($data['agents'])) {
            $agents = in_array('none', $data['agents'], true) ? ['none'] : array_values(array_unique($data['agents']));
            $compatibility['agent_defaults'] = $agents;
            $data['settings']['ai'] = $agents !== ['none'];
        }
        if (array_key_exists('ide', $data)) {
            $compatibility['ide'] = $data['language'] === 'stata' ? null : self::ide($data['ide']);
        }
        $data['workflow'] = $compatibility['workflows'][0];
        $data['tools']['targets'] = false;
        unset($data['settings']['sensitive']);

        $resolved = (new Resolver($request->user(), withDefaults: false))->resolve(Arr::only($data, [
            'name', 'language', 'workflow', 'notebook_format', 'structure', 'tools', 'package_sets', 'package_overrides', 'package_auto_attach', 'skill_sets', 'skills', 'excluded_skills', 'settings', 'extra_yaml', 'connections',
        ]));

        return [
            array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility', 'language'])),
            $resolved['structure'],
            array_map(fn (FacetVersion $v) => $v->id, $resolved['pieces']),
            self::withSettings($resolved['layer'], $data),
            $data['notes'] ?? null,
            $compatibility,
        ];
    }

    /**
     * A saved blueprint has one language and one notebook workflow.
     * Legacy array inputs must describe the same single selection.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function compatibility(array $data): array
    {
        $language = $data['language'];
        if (isset($data['languages']) && array_values(array_unique($data['languages'])) !== [$language]) {
            throw ValidationException::withMessages(['languages' => 'Choose one language for this blueprint.']);
        }
        if (isset($data['workflows']) && count(array_unique($data['workflows'])) !== 1) {
            throw ValidationException::withMessages(['workflows' => 'Choose one notebook format for this blueprint.']);
        }
        $workflow = $data['workflow'] ?? $data['workflows'][0] ?? ProjectDefaults::WORKFLOWS[$language];
        if ($language === 'stata' && ! isset($data['workflow']) && ! isset($data['workflows'])) {
            $workflow = 'scripts';
        }
        if (! ProjectDefaults::workflowFits($workflow, $language)) {
            throw ValidationException::withMessages(['workflow' => 'Choose a notebook format for '.Project::LANGUAGE_NAMES[$language].'.']);
        }
        if (isset($data['workflow'], $data['workflows']) && $data['workflows'] !== [$workflow]) {
            throw ValidationException::withMessages(['workflows' => 'Choose one notebook format for this blueprint.']);
        }

        return [
            'languages' => [$language],
            'workflows' => [$workflow],
            'default_workflow' => [$language => $workflow],
        ] + ($language === 'stata' ? ['requires_tools' => []] : []);
    }

    /**
     * The files and folders a project from unsaved blueprint choices would get, for one language
     * and workflow. Nobody's defaults apply, as when the blueprint is saved.
     */
    public static function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'agents' => ['sometimes', 'array', 'min:1'],
            'agents.*' => [Rule::in([...ProjectDefaults::AGENTS, 'other', 'none'])],
            'workflow' => ['required', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            'notebook_format' => ['nullable', Rule::in(['quarto', 'rmarkdown', 'jupyter'])],
            'structure' => ['required', 'string', 'max:200'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'package_overrides' => ['nullable', 'array'],
            'package_overrides.*' => ['boolean'],
            'package_auto_attach' => ['nullable', 'array'],
            'package_auto_attach.*' => ['boolean'],
            'skill_sets' => ['nullable', 'array'],
            'excluded_skills' => ['nullable', 'array'],
            'excluded_skills.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
            'settings.hooks' => ['nullable', 'array'],
            'settings.hooks.*' => [Rule::in(ProjectDefaults::HOOKS)],
            'ide' => ['sometimes', 'nullable', Rule::in(self::IDE_CHOICES)],
            ...Connections::rules(),
        ]);

        unset($data['settings']['sensitive']);
        $ide = array_key_exists('ide', $data) ? Arr::pull($data, 'ide') : false;

        try {
            $resolved = (new Resolver($request->user(), withDefaults: false))->resolve(['name' => 'project'] + $data);
        } catch (ValidationException $e) {
            return response()->json(['problem' => collect($e->errors())->flatten()->first()], 422);
        }
        $spec = ['name' => 'project'] + DefaultsPreview::merge($resolved['envelope']['layers']);
        if (isset($data['agents'])) {
            $spec['ai']['enabled'] = ! in_array('none', $data['agents'], true);
            $spec['ai']['assistants'] = array_fill_keys(ProjectDefaults::AGENTS, false);
            foreach (array_intersect($data['agents'], ProjectDefaults::AGENTS) as $agent) {
                $spec['ai']['assistants'][$agent] = true;
            }
        }
        // The blueprint's editor files, as a project from it would get them
        if ($ide !== false && $resolved['language'] !== 'stata') {
            $spec['scaffold']['ide'] = self::ide($ide);
        }
        $tools = collect($resolved['pieces'])->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool')->map(fn (FacetVersion $v) => $v->facet->key)->values()->all();
        $sensitive = (bool) ($spec['security']['sensitive'] ?? false);

        return response()->json([
            'tree' => ProjectTree::for($spec, $resolved['language'], $tools, $sensitive),
            'render_dirs' => collect((array) ($spec['render_dirs'] ?? []))->map(fn ($to, $key) => ['from' => $spec['directories'][$key] ?? $key, 'to' => $to])->values()->all(),
            'sensitive' => $sensitive,
        ]);
    }

    /** The editor choices a blueprint takes. positron is an older name for vscode. */
    public const IDE_CHOICES = ['rstudio', 'vscode', 'positron', 'both', 'none'];

    /** The scaffold settings each language reads: the ggplot2 theme is R's, and Stata has none. */
    public const SCAFFOLD_KEYS = [
        'r' => ['seed_on_scaffold', 'seed', 'set_theme_on_scaffold', 'ggplot_theme', 'source_all_functions'],
        'python' => ['seed_on_scaffold', 'seed', 'source_all_functions'],
        'stata' => [],
    ];

    /** The publishing destinations a blueprint can name. Automatic names none. */
    public const DESTINATIONS = ['cloud', 'github'];

    /** An editor choice as it's stored: rstudio, vscode, both, or none. */
    public static function ide(?string $ide): ?string
    {
        return match ($ide) {
            null, '' => null,
            'positron' => 'vscode',
            default => in_array($ide, BlueprintVersion::IDES, true) ? $ide : null,
        };
    }

    /**
     * Rules for the settings a blueprint holds beside its pieces: scaffold,
     * Quarto, editor files, the publishing destination, and connections. Each is optional,
     * so clients that don't send them change nothing.
     *
     * @return array<string, list<mixed>>
     */
    private static function settingRules(): array
    {
        $rules = [
            'scaffold' => ['sometimes', 'nullable', 'array'],
            'scaffold.seed_on_scaffold' => ['boolean'],
            'scaffold.seed' => ['nullable', 'regex:/^-?\d{1,9}$/'],
            'scaffold.set_theme_on_scaffold' => ['boolean'],
            'scaffold.ggplot_theme' => ['nullable', Rule::in(ProjectDefaults::GGPLOT_THEMES)],
            'scaffold.source_all_functions' => ['boolean'],
            'quarto' => ['sometimes', 'nullable', 'array'],
            'quarto.html' => ['array'],
            'quarto.revealjs' => ['array'],
            'ide' => ['sometimes', 'nullable', Rule::in(self::IDE_CHOICES)],
            'publish_destination' => ['sometimes', 'nullable', Rule::in(['', ...self::DESTINATIONS])],
            ...Connections::rules(),
        ];
        foreach (ProjectDefaults::QUARTO as $format => $values) {
            foreach ($values as $key => $default) {
                $choices = ProjectDefaults::QUARTO_CHOICES["{$format}.{$key}"]
                    ?? ProjectDefaults::QUARTO_CHOICES[str_ends_with($key, 'transition') ? 'transition' : $key] ?? null;
                $rules["quarto.{$format}.{$key}"] = match (true) {
                    is_bool($default) => ['boolean'],
                    is_int($default) => ['integer', 'between:1,6'],
                    default => [Rule::in($choices ?? [])],
                };
            }
        }

        return $rules;
    }

    /**
     * The blueprint's own layer with the settings the editor sent: every
     * scaffold key the language reads (the packages fill a missing key with
     * their own default), the HTML and RevealJS Quarto settings, the
     * publishing destination, and connections with the .env variables they
     * read. Stata reads neither scaffold, Quarto, nor connections.
     *
     * @param  array<string, mixed>  $layer
     * @param  array<string, mixed>  $data  validated input
     * @return array<string, mixed>
     */
    public static function withSettings(array $layer, array $data): array
    {
        $language = $data['language'];
        if (is_array($data['scaffold'] ?? null) && self::SCAFFOLD_KEYS[$language]) {
            $scaffold = ProjectDefaults::scaffold($data['scaffold']);
            foreach (self::SCAFFOLD_KEYS[$language] as $key) {
                $layer['scaffold'][$key] = $scaffold[$key];
            }
        }
        if (is_array($data['quarto'] ?? null) && $language !== 'stata') {
            foreach (ProjectDefaults::quarto($data['quarto']) as $format => $values) {
                $layer['quarto'][$format] = array_replace((array) ($layer['quarto'][$format] ?? []), $values);
            }
        }
        if (in_array($destination = $data['publish_destination'] ?? null, self::DESTINATIONS, true)) {
            $layer['publish']['default'] = $destination;
            $layer['publish']['destinations'][$destination] = ['driver' => $destination];
        }
        // Connections, and the .env variables they read
        if (is_array($data['connections'] ?? null)) {
            foreach (Connections::layer($data['connections'], $language) as $key => $value) {
                $layer[$key] = array_replace_recursive(is_array($layer[$key] ?? null) ? $layer[$key] : [], $value);
            }
        }

        return $layer;
    }

    /** The destination a layer names in the editor's shape (default X with driver X), or null. */
    public static function destination(array $layer): ?string
    {
        $default = $layer['publish']['default'] ?? null;

        return in_array($default, self::DESTINATIONS, true) && ($layer['publish']['destinations'][$default] ?? null) === ['driver' => $default] ? $default : null;
    }

    /**
     * A layer without what the editor's settings tabs hold, for the extra
     * settings YAML. Other Quarto, scaffold, and publishing keys stay.
     *
     * @param  array<string, mixed>  $layer
     * @return array<string, mixed>
     */
    public static function withoutSettings(array $layer): array
    {
        if ($destination = self::destination($layer)) {
            unset($layer['publish']['default'], $layer['publish']['destinations'][$destination]);
        }
        [, $layer] = Connections::split($layer);
        foreach (array_keys(ProjectDefaults::SCAFFOLD) as $key) {
            unset($layer['scaffold'][$key]);
        }
        foreach (ProjectDefaults::QUARTO as $format => $values) {
            foreach (array_keys($values) as $key) {
                unset($layer['quarto'][$format][$key]);
            }
        }
        foreach (['publish.destinations', 'publish', 'quarto.html', 'quarto.revealjs', 'quarto', 'scaffold'] as $path) {
            if (Arr::has($layer, $path) && Arr::get($layer, $path) === []) {
                Arr::forget($layer, $path);
            }
        }

        return $layer;
    }

    /**
     * Choices to start the editor from, from Catalog::choices(), with the
     * settings tabs' values. What a version doesn't set shows the person's
     * own defaults, as agents do, so saving keeps what their projects got.
     *
     * @param  array<string, mixed>|null  $choices  from Catalog::choices(), or null for a new blueprint
     * @return array<string, mixed>
     */
    public static function start(?BlueprintVersion $version, ?array $choices, User $user): array
    {
        $defaults = ProjectDefaults::for($user);
        $layer = $version?->layer ?? [];
        $settings = [
            'scaffold' => ProjectDefaults::scaffold(array_replace($defaults['settings']['scaffold'], (array) ($layer['scaffold'] ?? []))),
            'quarto' => ProjectDefaults::quarto(array_replace_recursive($defaults['settings']['quarto'], (array) ($layer['quarto'] ?? []))),
            'ide' => self::ide($version?->ide) ?? self::ide($defaults['settings']['ide']) ?? ProjectDefaults::IDE,
            'publish_destination' => self::destination($layer) ?? (in_array($defaults['publish_destination'], self::DESTINATIONS, true) ? $defaults['publish_destination'] : ''),
            'connections' => Connections::split($layer)[0],
        ];
        if ($choices === null) {
            return $settings;
        }

        [$extra] = Layer::fromYaml((string) ($choices['extra_yaml'] ?? ''));
        if (is_array($extra) && array_intersect(['scaffold', 'quarto', 'publish', 'connections', 'env'], array_keys($extra))) {
            $choices['extra_yaml'] = Layer::toYaml(self::withoutSettings($extra));
        }

        return $settings + $choices;
    }
}
