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
use Inertia\Inertia;
use Inertia\Response;

class DefaultsController extends Controller
{
    /** The settings pages, in sidebar order. They share one form. */
    public const SECTIONS = ['language', 'blueprint', 'editor', 'packages', 'scaffold', 'git', 'agents', 'data', 'hooks', 'notebooks', 'quarto'];

    public function edit(Request $request, string $section): Response
    {
        $setting = $request->user()->globalSettings();
        $doc = $setting->document;

        return Inertia::render('Settings/Defaults', [
            'section' => $section,
            'revision' => $setting->revision,
            'blueprints' => Blueprint::systemTypes()->with('latestVersion.base.facet', 'latestVersion.facetVersions.facet')->get()
                ->filter(fn (Blueprint $b) => $b->latestVersion)->sortBy(fn (Blueprint $b) => array_search($b->key, self::ORDER, true))
                ->map(fn (Blueprint $b) => self::listing($b))->values(),
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
            'values' => [
                'language' => $request->user()->project_defaults['language'] ?? ProjectDefaults::LANGUAGE,
                'project_type' => Arr::get($doc, 'defaults.project_type', ProjectDefaults::BLUEPRINT),
                'notebook_format' => Arr::get($doc, 'defaults.notebook_format', 'quarto'),
                'ide' => Arr::get($doc, 'defaults.ide', ProjectDefaults::IDE),
                'use_git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'use_renv' => (bool) Arr::get($doc, 'defaults.use_renv', false),
                ...ProjectDefaults::scaffold((array) ($doc['defaults'] ?? [])),
                'ai_enabled' => (bool) Arr::get($doc, 'ai.enabled', true),
                'ai_agents' => ProjectDefaults::agents(Arr::get($doc, 'ai.assistants')),
                'ai_skills' => (bool) Arr::get($doc, 'ai.skills', true),
                'package_sets' => $request->user()->project_defaults['package_sets'] ?? [],
                'packages' => self::packageLists($request->user()),
                'skill_sets' => $request->user()->project_defaults['skill_sets'] ?? [],
                'tools' => (object) ($request->user()->project_defaults['tools'] ?? []),
                'sensitive' => (bool) ($request->user()->project_defaults['sensitive'] ?? false),
                'hooks' => (object) ($request->user()->project_defaults['hooks'] ?? []),
            ],
        ]);
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
            'languages' => array_values(array_filter(Project::LANGUAGES, fn (string $language) => ! Project::typeProblem($language, $type))),
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
            'notebook_format' => ['required', 'in:quarto,rmarkdown'],
            'ide' => ['required', 'in:positron,vscode,rstudio,both,none'],
            'use_git' => ['boolean'],
            'use_renv' => ['boolean'],
            'seed_on_scaffold' => ['boolean'],
            'seed' => ['nullable', 'regex:/^-?\d{1,9}$/'],
            'set_theme_on_scaffold' => ['boolean'],
            'ggplot_theme' => ['nullable', 'in:'.implode(',', ProjectDefaults::GGPLOT_THEMES)],
            'source_all_functions' => ['boolean'],
            'ai_enabled' => ['boolean'],
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
            'sensitive' => ['boolean'],
            'hooks' => ['array'],
            'hooks.*' => ['boolean'],
        ]);

        $setting = $request->user()->globalSettings();
        $doc = $setting->document;

        Arr::set($doc, 'defaults.project_type', $validated['project_type']);
        Arr::set($doc, 'defaults.notebook_format', $validated['notebook_format']);
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
        Arr::set($doc, 'ai.skills', (bool) ($validated['ai_skills'] ?? true));

        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);

        // Preferred pieces stay in the cloud; renv already lives in the synced settings
        $request->user()->forceFill(['project_defaults' => [
            'language' => $validated['language'] ?? $request->user()->project_defaults['language'] ?? ProjectDefaults::LANGUAGE,
            // Package sets now only load packages into the lists, which hold the result
            'package_sets' => [],
            'packages' => ProjectDefaults::packages($validated['packages'] ?? []),
            'skill_sets' => array_values($validated['skill_sets'] ?? []),
            'tools' => array_map('boolval', Arr::except($validated['tools'] ?? [], ['renv'])),
            'sensitive' => (bool) ($validated['sensitive'] ?? false),
            'hooks' => array_map('boolval', Arr::only($validated['hooks'] ?? [], ProjectDefaults::HOOKS)),
        ]])->save();

        return back()->with('status', 'defaults-updated');
    }
}
