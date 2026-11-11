<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\Project;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\ProjectDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Guided setup's account defaults: the form values it starts from, the
 * Framework blueprints it offers, and its preview. Default settings
 * themselves are gone; new projects take everything from their blueprint.
 */
class DefaultsController extends Controller
{
    /**
     * The person's stored defaults as guided setup's form holds them.
     *
     * @return array<string, mixed>
     */
    public static function values(User $user): array
    {
        $doc = $user->globalSettings()->document;

        return [
            'publish_destination' => $user->project_defaults['publish_destination'] ?? '',
            'language' => ProjectDefaults::language($user),
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
    private const ORDER = ['project', 'project_sensitive', 'presentation', 'course', 'shiny', 'plumber', 'shiny_python', 'fastapi', 'bare'];

    /**
     * A blueprint as the Blueprint page lists it. What it creates comes
     * from preview().
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
}
