<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Project;
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
    public const SECTIONS = ['blueprint', 'editor', 'packages', 'scaffold', 'git', 'agents', 'data', 'hooks', 'notebooks', 'quarto'];

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
            'values' => [
                'project_type' => Arr::get($doc, 'defaults.project_type', ProjectDefaults::BLUEPRINT),
                'notebook_format' => Arr::get($doc, 'defaults.notebook_format', 'quarto'),
                'ide' => Arr::get($doc, 'defaults.ide', 'positron'),
                'use_git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'use_renv' => (bool) Arr::get($doc, 'defaults.use_renv', false),
                'seed_on_scaffold' => (bool) Arr::get($doc, 'defaults.seed_on_scaffold', true),
                'ai_enabled' => (bool) Arr::get($doc, 'ai.enabled', true),
                'ai_canonical_file' => Arr::get($doc, 'ai.canonical_file', 'AGENTS.md'),
                'ai_skills' => (bool) Arr::get($doc, 'ai.skills', true),
                'package_sets' => $request->user()->project_defaults['package_sets'] ?? [],
                'skill_sets' => $request->user()->project_defaults['skill_sets'] ?? [],
                'tools' => (object) ($request->user()->project_defaults['tools'] ?? []),
                'sensitive' => (bool) ($request->user()->project_defaults['sensitive'] ?? false),
                'hooks' => (object) ($request->user()->project_defaults['hooks'] ?? []),
            ],
        ]);
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
            'project_type' => ['required', 'exists:blueprints,key'],
            'notebook_format' => ['required', 'in:quarto,rmarkdown'],
            'ide' => ['required', 'in:positron,vscode,rstudio,both,none'],
            'use_git' => ['boolean'],
            'use_renv' => ['boolean'],
            'seed_on_scaffold' => ['boolean'],
            'ai_enabled' => ['boolean'],
            'ai_canonical_file' => ['required', 'string', 'max:60'],
            'ai_skills' => ['boolean'],
            'package_sets' => ['array'],
            'package_sets.*' => ['string', 'max:200'],
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
        Arr::set($doc, 'defaults.seed_on_scaffold', (bool) ($validated['seed_on_scaffold'] ?? true));
        Arr::set($doc, 'ai.enabled', (bool) ($validated['ai_enabled'] ?? true));
        Arr::set($doc, 'ai.canonical_file', $validated['ai_canonical_file']);
        Arr::set($doc, 'ai.skills', (bool) ($validated['ai_skills'] ?? true));

        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);

        // Preferred pieces stay in the cloud; renv already lives in the synced settings
        $request->user()->forceFill(['project_defaults' => [
            'package_sets' => array_values($validated['package_sets'] ?? []),
            'skill_sets' => array_values($validated['skill_sets'] ?? []),
            'tools' => array_map('boolval', Arr::except($validated['tools'] ?? [], ['renv'])),
            'sensitive' => (bool) ($validated['sensitive'] ?? false),
            'hooks' => array_map('boolval', Arr::only($validated['hooks'] ?? [], ProjectDefaults::HOOKS)),
        ]])->save();

        return back()->with('status', 'defaults-updated');
    }
}
