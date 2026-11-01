<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\Project;
use App\Models\User;
use App\Support\Catalog;
use App\Support\DefaultsPreview;
use App\Support\ProjectDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DefaultsController extends Controller
{
    /** The settings pages, in sidebar order. They share one form. */
    public const SECTIONS = ['language', 'blueprint', 'editor', 'packages', 'scaffold', 'git', 'hooks', 'agents', 'notebooks', 'quarto', 'jupyter'];

    public function edit(Request $request, string $section): Response
    {
        $setting = $request->user()->globalSettings();
        $doc = $setting->document;

        return Inertia::render('Settings/Defaults', [
            'section' => $section,
            'revision' => $setting->revision,
            'blueprints' => self::blueprintList(),
            'catalog' => Catalog::for($request->user()),
            // Package sets load their packages into a list, for each language they fit
            'presets' => Facet::visibleTo($request->user())->where('kind', 'package_set')->with('latestVersion')
                ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
                ->map(fn (Facet $set) => [
                    'key' => $set->handle,
                    'name' => $set->name,
                    'language' => $set->language ?? ProjectDefaults::PACKAGE_LANGUAGES,
                    'packages' => self::rowsFrom($set->latestVersion?->layer ?? []),
                ])->values(),
            'ggplotThemes' => ProjectDefaults::GGPLOT_THEMES,
            'quartoChoices' => ProjectDefaults::QUARTO_CHOICES,
            'values' => self::values($request->user()),
        ]);
    }

    /**
     * The person's defaults as the Project defaults form holds them. Guided
     * setup starts from these too.
     *
     * @return array<string, mixed>
     */
    public static function values(User $user): array
    {
        $doc = $user->globalSettings()->document;

        return [
            'language' => $user->project_defaults['language'] ?? ProjectDefaults::LANGUAGE,
            'project_type' => Arr::get($doc, 'defaults.project_type', ProjectDefaults::BLUEPRINT),
            // A workflow per language; R Markdown is a variant of R's Quarto workflow
            'workflows' => ProjectDefaults::workflows(Arr::get($doc, 'defaults.workflows')),
            'rmarkdown' => Arr::get($doc, 'defaults.notebook_format') === 'rmarkdown',
            'ide' => Arr::get($doc, 'defaults.ide', ProjectDefaults::IDE),
            'use_git' => (bool) Arr::get($doc, 'defaults.use_git', true),
            'use_renv' => (bool) Arr::get($doc, 'defaults.use_renv', false),
            ...ProjectDefaults::scaffold((array) ($doc['defaults'] ?? [])),
            'ai_enabled' => (bool) Arr::get($doc, 'ai.enabled', true),
            'ai_agents' => ProjectDefaults::agents(Arr::get($doc, 'ai.assistants')),
            // Uses agents beyond the named ones (AGENTS.md); never saved means none named
            'ai_other' => (bool) ($user->project_defaults['ai_other'] ?? ! ProjectDefaults::agents(Arr::get($doc, 'ai.assistants'))),
            'quarto' => ProjectDefaults::quarto((array) ($doc['quarto'] ?? [])),
            'ai_skills' => (bool) Arr::get($doc, 'ai.skills', true),
            'package_sets' => $user->project_defaults['package_sets'] ?? [],
            'packages' => self::packageLists($user),
            'skill_sets' => $user->project_defaults['skill_sets'] ?? [],
            'tools' => (object) ($user->project_defaults['tools'] ?? []),
            'hooks' => (object) ($user->project_defaults['hooks'] ?? []),
        ];
    }

    /**
     * The system blueprints, Standard first and Bare last, as the Blueprint
     * page and guided setup list them.
     */
    public static function blueprintList(): Collection
    {
        return Blueprint::systemTypes()->with('latestVersion.base.facet', 'latestVersion.facetVersions.facet')->get()
            ->filter(fn (Blueprint $b) => $b->latestVersion)->sortBy(fn (Blueprint $b) => array_search($b->key, self::ORDER, true))
            ->map(fn (Blueprint $b) => self::listing($b))->values();
    }

    /**
     * The person's package lists. Before there were lists, default package
     * sets stood in for them, so those sets start the lists.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function packageLists(User $user): array
    {
        $own = $user->project_defaults ?? [];
        if (isset($own['packages']) || empty($own['package_sets'])) {
            return ProjectDefaults::packages($own['packages'] ?? ProjectDefaults::DEFAULT_PACKAGES);
        }

        $lists = [];
        foreach (Facet::visibleTo($user)->where('kind', 'package_set')->with('latestVersion')->get() as $set) {
            if (in_array($set->handle, $own['package_sets'], true)) {
                foreach ($set->language ?? ProjectDefaults::PACKAGE_LANGUAGES as $language) {
                    $lists[$language] = [...($lists[$language] ?? []), ...self::rowsFrom($set->latestVersion?->layer ?? [])];
                }
            }
        }

        return ProjectDefaults::packages($lists);
    }

    /** A layer's packages as list rows. */
    private static function rowsFrom(array $layer): array
    {
        return collect($layer['packages']['default_packages'] ?? [])->map(fn ($entry, $name) => [
            'name' => $name,
            'source' => $entry['source'] ?? 'cran',
            'auto_attach' => (bool) ($entry['auto_attach'] ?? false),
            'version' => $entry['version'] ?? null,
            'repo' => $entry['repo'] ?? null,
        ])->values()->all();
    }

    /** The order the Blueprint page lists them in: the standard project first, Bare last. */
    private const ORDER = ['project', 'project_sensitive', 'presentation', 'course', 'bare'];

    /**
     * A blueprint as the Blueprint page lists it. What it creates comes
     * from preview(), resolved with the person's defaults.
     *
     * @return array<string, mixed>
     */
    private static function listing(Blueprint $blueprint): array
    {
        $version = $blueprint->latestVersion;
        $type = $version->base->layer['type'] ?? 'bare';

        return [
            'key' => $blueprint->key,
            'id' => $blueprint->id,
            'name' => $blueprint->name,
            'description' => $blueprint->description,
            'handle' => $blueprint->handle,
            'version' => $version->version,
            'structure' => $version->base->facet->name,
            // Where each folder's documents render, from the project structure
            'render_dirs' => collect($version->base->layer['render_dirs'] ?? [])
                ->map(fn ($target, $key) => ['from' => $version->base->layer['directories'][$key] ?? $key, 'to' => $target])->values(),
            'render_dir' => $version->base->layer['quarto']['render_dir'] ?? null,
            'languages' => array_values(array_filter($version->languages ?: Project::LANGUAGES, fn (string $language) => ! Project::typeProblem($language, $type))),
            // The workflows it supports; none listed means every one the language has
            'workflows' => $version->workflows ?: ProjectDefaults::WORKFLOW_KEYS,
        ];
    }

    /** The preview for one blueprint and language, from the form's values, saved or not. Saves nothing. */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blueprint' => ['required', 'string'],
            'language' => ['required', 'in:r,python,stata'],
            'values' => ['array'],
        ]);

        $blueprint = Blueprint::systemTypes()->where('key', $validated['blueprint'])->with('latestVersion.base', 'latestVersion.facetVersions.facet')->firstOrFail();

        return response()->json(DefaultsPreview::for($request->user(), $blueprint, $validated['language'], $validated['values'] ?? []));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['sometimes', 'in:r,python,stata'],
            'project_type' => ['required', 'exists:blueprints,key'],
            'workflows' => ['array'],
            'workflows.r' => ['sometimes', 'in:quarto,jupyter,scripts'],
            'workflows.python' => ['sometimes', 'in:quarto,jupyter,scripts'],
            'workflows.stata' => ['sometimes', 'in:scripts'],
            'rmarkdown' => ['boolean'],
            'ide' => ['required', 'in:positron,vscode,rstudio,both,none'],
            'use_git' => ['boolean'],
            'use_renv' => ['boolean'],
            'seed_on_scaffold' => ['boolean'],
            'seed' => ['nullable', 'regex:/^-?\d{1,9}$/'],
            'set_theme_on_scaffold' => ['boolean'],
            'ggplot_theme' => ['nullable', 'in:'.implode(',', ProjectDefaults::GGPLOT_THEMES)],
            'source_all_functions' => ['boolean'],
            'ai_enabled' => ['boolean'],
            'quarto' => ['array'],
            'quarto.html' => ['array'],
            'quarto.revealjs' => ['array'],
            'ai_other' => ['boolean'],
            'ai_agents' => ['array'],
            'ai_agents.*' => ['in:'.implode(',', ProjectDefaults::AGENTS)],
            'ai_skills' => ['boolean'],
            'package_sets' => ['array'],
            'package_sets.*' => ['string', 'max:200'],
            'packages' => ['array'],
            'packages.*' => ['array'],
            'packages.*.*.name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'packages.*.*.source' => ['required', 'in:cran,bioc,github,pypi'],
            'packages.*.*.auto_attach' => ['boolean'],
            'packages.*.*.version' => ['nullable', 'string', 'max:50'],
            'packages.*.*.repo' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'skill_sets' => ['array'],
            'skill_sets.*' => ['string', 'max:200'],
            'tools' => ['array'],
            'tools.*' => ['boolean'],
            'hooks' => ['array'],
            'hooks.*' => ['boolean'],
        ]);

        $setting = $request->user()->globalSettings();
        $doc = $setting->document;

        Arr::set($doc, 'defaults.project_type', $validated['project_type']);
        // R and Python read these; notebook_format is R's, for clients that predate workflows
        $workflows = ProjectDefaults::workflows($validated['workflows'] ?? Arr::get($doc, 'defaults.workflows'));
        $rmarkdown = $workflows['r'] === 'quarto' && (bool) ($validated['rmarkdown'] ?? false);
        Arr::set($doc, 'defaults.workflows', $workflows);
        Arr::set($doc, 'defaults.notebook_format', ProjectDefaults::notebookFormat($workflows['r'], 'r', $rmarkdown) ?? 'quarto');
        Arr::set($doc, 'defaults.ide', $validated['ide']);
        Arr::set($doc, 'defaults.use_git', (bool) ($validated['use_git'] ?? true));
        Arr::set($doc, 'defaults.use_renv', (bool) ($validated['use_renv'] ?? false));
        // R's own settings read these from defaults too
        foreach (ProjectDefaults::scaffold($validated) as $name => $value) {
            Arr::set($doc, "defaults.{$name}", $value);
        }
        Arr::set($doc, 'ai.enabled', (bool) ($validated['ai_enabled'] ?? true));
        // R reads the agents as a list. AGENTS.md holds the instructions, and
        // Claude Code gets a CLAUDE.md that imports it.
        Arr::set($doc, 'ai.assistants', ProjectDefaults::agents($validated['ai_agents'] ?? null));
        Arr::set($doc, 'ai.canonical_file', 'AGENTS.md');
        // In the shape R's global settings use, so R can read them too
        Arr::set($doc, 'quarto', ProjectDefaults::quarto($validated['quarto'] ?? (array) ($doc['quarto'] ?? [])));
        Arr::set($doc, 'ai.skills', (bool) ($validated['ai_skills'] ?? true));

        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);

        // Preferred pieces stay in the cloud; renv already lives in the synced settings
        $request->user()->forceFill(['project_defaults' => [
            'language' => $validated['language'] ?? $request->user()->project_defaults['language'] ?? ProjectDefaults::LANGUAGE,
            // Package sets now only load packages into the lists, which hold the result
            'package_sets' => [],
            'packages' => ProjectDefaults::packages($validated['packages'] ?? []),
            'skill_sets' => array_values($validated['skill_sets'] ?? []),
            // Jupyter notebooks need the Jupyter tool's kernel
            'tools' => array_map('boolval', Arr::except($validated['tools'] ?? [], ['renv'])),
            'hooks' => array_map('boolval', Arr::only($validated['hooks'] ?? [], ProjectDefaults::HOOKS)),
            'ai_other' => (bool) ($validated['ai_other'] ?? $request->user()->project_defaults['ai_other'] ?? false),
        ]])->save();

        return back()->with('status', 'defaults-updated');
    }
}
