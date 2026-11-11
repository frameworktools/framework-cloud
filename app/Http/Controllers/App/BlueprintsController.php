<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Project;
use App\Support\BlueprintInput;
use App\Support\Catalog;
use App\Support\DefaultsPreview;
use App\Support\Library;
use App\Support\ProjectDefaults;
use App\Support\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BlueprintsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $doc = $user->globalSettings()->document;

        return Inertia::render('Blueprints/Index', [
            'blueprints' => Blueprint::listedFor($user)->with(Library::BLUEPRINT_WITH)
                ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')
                ->get()->filter(fn (Blueprint $b) => $b->latestVersion)
                ->map(fn (Blueprint $b) => Library::blueprint($b, $user) + self::supports($b->latestVersion) + [
                    // Framework's blueprints by type, and your own
                    'can_be_default' => ($b->isSystem() && in_array($b->key, Project::TYPES, true)) || $b->user_id === $user->id,
                ])->values(),
            // Where browsing starts: the saved language and each language's workflow
            'language' => ProjectDefaults::for($user)['language'] ?? ProjectDefaults::LANGUAGE,
            'workflows' => ProjectDefaults::workflows(Arr::get($doc, 'defaults.workflows')),
            'default_blueprint' => ProjectDefaults::for($user)['blueprint'],
        ]);
    }

    /**
     * The languages and workflows a version supports, each language with the
     * workflows it can take. Nothing listed means everything.
     *
     * @return array{languages: list<string>, workflows: list<string>, combinations: array<string, list<string>>}
     */
    private static function supports(BlueprintVersion $version): array
    {
        $compatibility = $version->compatibility();
        $languages = $compatibility['languages'] ?? Project::LANGUAGES;
        $workflows = $compatibility['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS;

        return [
            'languages' => array_values($languages),
            'workflows' => array_values($workflows),
            'combinations' => collect($languages)->mapWithKeys(fn (string $language) => [
                $language => array_values(array_filter($workflows, fn (string $w) => ProjectDefaults::workflowFits($w, $language))),
            ])->filter()->all(),
        ];
    }

    /**
     * POST /blueprints/{blueprint}/preview: what a project from this
     * blueprint gets for one language and workflow, with the person's saved
     * defaults, resolved as New project resolves it.
     */
    public function previewBlueprint(Request $request, Blueprint $blueprint): JsonResponse
    {
        abort_unless($blueprint->isVisibleTo($request->user()) && $blueprint->latestVersion, 404);
        $data = $request->validate([
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'workflow' => ['nullable', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
        ]);
        $blueprint->load('owner', 'latestVersion.base', 'latestVersion.facetVersions.facet');

        return response()->json(DefaultsPreview::forBlueprint($request->user(), $blueprint, $data['language'], $data['workflow'] ?? null));
    }

    /**
     * POST /blueprints/{blueprint}/default: make this the default blueprint
     * and the workflow the language's default, leaving every other default
     * as it is.
     */
    public function makeDefault(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $user = $request->user();
        $system = $blueprint->isSystem() && in_array($blueprint->key, Project::TYPES, true);
        // Framework's blueprints by type, or one of your own
        abort_unless($system || $blueprint->user_id === $user->id, 404);
        $data = $request->validate([
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'workflow' => ['required', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
        ]);
        $supported = self::supports($blueprint->latestVersion)['combinations'][$data['language']] ?? [];
        if (! in_array($data['workflow'], $supported, true)) {
            throw ValidationException::withMessages(['workflow' => "{$blueprint->name} doesn't support that language and workflow."]);
        }

        // The language's default blueprint; saving also keeps the synced settings the packages read offline
        ProjectDefaults::setBlueprint($user, $data['language'], $blueprint);

        return back()->with('status', 'blueprint-default');
    }

    public function create(Request $request): Response
    {
        $start = null;

        if ($from = $request->integer('from')) {
            $source = Blueprint::with(Library::BLUEPRINT_WITH)->find($from);
            $start = $source?->isVisibleTo($request->user()) ? BlueprintInput::start($source->latestVersion, Catalog::choices($source->latestVersion, $request->user()), $request->user()) : null;
        }

        return $this->builder($request, null, $start ?? BlueprintInput::start(null, null, $request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = BlueprintInput::validated($request);

        $blueprint = DB::transaction(function () use ($user, $meta, $base, $facetVersionIds, $layer, $notes, $compatibility) {
            $blueprint = Blueprint::create($meta + [
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $meta['name'], $user->id),
                'is_system' => false,
            ]);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes, $compatibility);

            return $blueprint;
        });

        return to_route('blueprints.show', $blueprint)->with('status', 'blueprint-saved');
    }

    public function show(Request $request, Blueprint $blueprint): Response
    {
        $user = $request->user();
        abort_unless($blueprint->isVisibleTo($user), 404);

        $blueprint->load(Library::BLUEPRINT_WITH);
        $version = $request->filled('version')
            ? $blueprint->versions()->where('version', $request->integer('version'))->firstOrFail()
            : $blueprint->latestVersion;
        $version->load(['base.facet.latestVersion', 'base.facet.owner', 'facetVersions.facet.latestVersion', 'facetVersions.facet.owner']);

        return Inertia::render('Blueprints/Show', [
            'blueprint' => Library::blueprint($blueprint, $user),
            'version' => Library::blueprintVersion($version),
            'versions' => $blueprint->versions()->get(['id', 'version', 'notes', 'created_at']),
            'forked_from' => $this->forkedFrom($blueprint, $user),
            'share' => Share::for($blueprint),
        ]);
    }

    public function edit(Request $request, Blueprint $blueprint): Response
    {
        abort_unless($blueprint->isEditableBy($request->user()), 403);

        $blueprint->load(Library::BLUEPRINT_WITH);

        return $this->builder($request, $blueprint, BlueprintInput::start($blueprint->latestVersion, Catalog::choices($blueprint->latestVersion, $request->user()), $request->user()));
    }

    public function update(Request $request, Blueprint $blueprint): RedirectResponse
    {
        abort_unless($blueprint->isEditableBy($request->user()), 403);

        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = BlueprintInput::validated($request);

        DB::transaction(function () use ($blueprint, $meta, $base, $facetVersionIds, $layer, $notes, $compatibility) {
            $blueprint->update($meta);
            // The builder edits what it supports; requirements carry over, and
            // a default workflow it no longer supports goes
            $compatibility += $blueprint->latestVersion?->only(Blueprint::COMPATIBILITY) ?? [];
            $compatibility['default_workflow'] = array_filter((array) ($compatibility['default_workflow'] ?? []), fn ($workflow, $language) => in_array($language, $compatibility['languages'] ?? Project::LANGUAGES, true)
                && in_array($workflow, $compatibility['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS, true), ARRAY_FILTER_USE_BOTH);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes, $compatibility);
        });

        // Saving keeps you where you were in the editor
        return back()->with('status', 'blueprint-saved');
    }

    public function fork(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $user = $request->user();
        abort_unless($blueprint->isVisibleTo($user), 404);

        $source = $blueprint->latestVersion;
        // The language and workflow being browsed become the copy's own
        $chosen = $request->validate([
            'language' => ['nullable', Rule::in(Project::LANGUAGES)],
            'workflow' => ['nullable', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
        ]);
        $combinations = self::supports($source)['combinations'];
        $language = isset($combinations[$chosen['language'] ?? '']) ? $chosen['language'] : (isset($combinations[$blueprint->language]) ? $blueprint->language : array_key_first($combinations));
        $workflow = collect([$chosen['workflow'] ?? null, $source->default_workflow[$language] ?? null, ProjectDefaults::WORKFLOWS[$language]])
            ->first(fn ($workflow) => in_array($workflow, $combinations[$language] ?? [], true)) ?? $combinations[$language][0];
        $compatibility = BlueprintInput::compatibility(['language' => $language, 'workflow' => $workflow]) + $source->only(Blueprint::COMPATIBILITY);

        $copy = DB::transaction(function () use ($user, $blueprint, $source, $language, $compatibility) {
            $copy = Blueprint::create([
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $blueprint->name, $user->id),
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'language' => $language,
                'visibility' => 'private',
                'forked_from_id' => $source->id,
                'is_system' => false,
            ]);
            $copy->publish(
                $source->base,
                $source->facetVersions->pluck('id')->all(),
                $source->layer ?? [],
                "Forked from {$blueprint->handle} v{$source->version}",
                // A copy pins the language and notebook format being browsed.
                $compatibility,
            );

            return $copy;
        });

        return to_route('blueprints.edit', ['blueprint' => $copy, 'copy' => 1])->with('status', 'blueprint-forked');
    }

    public function destroy(Request $request, Blueprint $blueprint): RedirectResponse
    {
        abort_unless($blueprint->isEditableBy($request->user()) && ! $blueprint->isSystem(), 403);

        if (Project::whereIn('blueprint_version_id', $blueprint->versions()->pluck('id'))->exists()) {
            return back()->withErrors(['blueprint' => 'Projects were made from this blueprint, so it stays. Make it private to hide it from others.']);
        }

        $blueprint->delete();

        return to_route('blueprints.index');
    }

    /** The editor's tabs, as ?tab= names them. */
    private const TABS = ['name', 'language', 'notebooks', 'quarto', 'jupyter', 'structure', 'environment', 'packages', 'scaffold', 'editor', 'ai', 'skills', 'git', 'publishing'];

    /** @param array<string, mixed>|null $start choices to begin from: the version being edited, or ?from= */
    private function builder(Request $request, ?Blueprint $blueprint, ?array $start): Response
    {
        $user = $request->user();
        $defaults = ProjectDefaults::for($user);

        return Inertia::render('Blueprints/Builder', [
            'blueprint' => $blueprint ? Library::blueprint($blueprint, $user) : null,
            'start' => $start,
            'agents' => $defaults['settings']['ai'] ? ($defaults['settings']['agents'] ?: ['other']) : ['none'],
            'catalog' => Catalog::for($user),
            'ggplotThemes' => ProjectDefaults::GGPLOT_THEMES,
            'quartoChoices' => ProjectDefaults::QUARTO_CHOICES,
            'section' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'name',
        ]);
    }

    /** POST /blueprints/preview: the files and folders the builder's unsaved choices would make. */
    public function preview(Request $request): JsonResponse
    {
        return BlueprintInput::preview($request);
    }

    private function forkedFrom(Blueprint $blueprint, $user): ?array
    {
        if (! $blueprint->forked_from_id) {
            return null;
        }

        $source = BlueprintVersion::with('blueprint.owner')->find($blueprint->forked_from_id);

        return $source?->blueprint->isVisibleTo($user) ? [
            'id' => $source->blueprint_id,
            'handle' => $source->blueprint->handle,
            'version' => $source->version,
        ] : null;
    }
}
