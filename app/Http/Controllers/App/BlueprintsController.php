<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Support\Catalog;
use App\Support\DefaultsPreview;
use App\Support\Layer;
use App\Support\Library;
use App\Support\ProjectDefaults;
use App\Support\ProjectTree;
use App\Support\Resolver;
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
                    // The defaults model holds Framework's blueprints by key
                    'can_be_default' => $b->isSystem() && in_array($b->key, Project::TYPES, true),
                ])->values(),
            // Where browsing starts: the saved language and each language's workflow
            'language' => ProjectDefaults::for($user)['language'] ?? ProjectDefaults::LANGUAGE,
            'workflows' => ProjectDefaults::workflows(Arr::get($doc, 'defaults.workflows')),
            'default_blueprint' => 'framework/'.Arr::get($doc, 'defaults.project_type', ProjectDefaults::BLUEPRINT),
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
        abort_unless($blueprint->isSystem() && in_array($blueprint->key, Project::TYPES, true), 404);
        $data = $request->validate([
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'workflow' => ['required', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
        ]);
        $supported = self::supports($blueprint->latestVersion)['combinations'][$data['language']] ?? [];
        if (! in_array($data['workflow'], $supported, true)) {
            throw ValidationException::withMessages(['workflow' => "{$blueprint->name} doesn't support that language and workflow."]);
        }

        $setting = $request->user()->globalSettings();
        $doc = $setting->document;
        Arr::set($doc, 'defaults.project_type', $blueprint->key);
        $workflows = ProjectDefaults::workflows(Arr::get($doc, 'defaults.workflows'));
        $workflows[$data['language']] = $data['workflow'];
        Arr::set($doc, 'defaults.workflows', $workflows);
        if ($data['language'] === 'r') {
            // R Markdown is a Quarto choice, so it stays when Quarto does
            $rmarkdown = Arr::get($doc, 'defaults.notebook_format') === 'rmarkdown';
            Arr::set($doc, 'defaults.notebook_format', ProjectDefaults::notebookFormat($workflows['r'], 'r', $rmarkdown) ?? 'quarto');
        }
        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);

        return back()->with('status', 'blueprint-default');
    }

    public function create(Request $request): Response
    {
        $start = null;

        if ($from = $request->integer('from')) {
            $source = Blueprint::with(Library::BLUEPRINT_WITH)->find($from);
            $start = $source?->isVisibleTo($request->user()) ? Catalog::choices($source->latestVersion, $request->user()) : null;
        }

        return $this->builder($request, null, $start);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = $this->validated($request);

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
        ]);
    }

    public function edit(Request $request, Blueprint $blueprint): Response
    {
        abort_unless($blueprint->isEditableBy($request->user()), 403);

        $blueprint->load(Library::BLUEPRINT_WITH);

        return $this->builder($request, $blueprint, Catalog::choices($blueprint->latestVersion, $request->user()));
    }

    public function update(Request $request, Blueprint $blueprint): RedirectResponse
    {
        abort_unless($blueprint->isEditableBy($request->user()), 403);

        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = $this->validated($request);

        DB::transaction(function () use ($blueprint, $meta, $base, $facetVersionIds, $layer, $notes, $compatibility) {
            $blueprint->update($meta);
            // The builder edits what it supports; requirements carry over, and
            // a default workflow it no longer supports goes
            $compatibility += $blueprint->latestVersion?->only(Blueprint::COMPATIBILITY) ?? [];
            $compatibility['default_workflow'] = array_filter((array) ($compatibility['default_workflow'] ?? []), fn ($workflow, $language) => in_array($language, $compatibility['languages'] ?? Project::LANGUAGES, true)
                && in_array($workflow, $compatibility['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS, true), ARRAY_FILTER_USE_BOTH);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes, $compatibility);
        });

        return to_route('blueprints.show', $blueprint)->with('status', 'blueprint-saved');
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
        $language = isset($combinations[$chosen['language'] ?? '']) ? $chosen['language'] : $blueprint->language;
        $compatibility = $source->only(Blueprint::COMPATIBILITY);
        if (in_array($chosen['workflow'] ?? null, $combinations[$language] ?? [], true)) {
            $compatibility['default_workflow'] = [$language => $chosen['workflow']] + ($source->default_workflow ?? []);
        }

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
                // A fork supports and requires what its source does
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

    /** @param array<string, mixed>|null $start choices to begin from: the version being edited, or ?from= */
    private function builder(Request $request, ?Blueprint $blueprint, ?array $start): Response
    {
        $user = $request->user();

        return Inertia::render('Blueprints/Builder', [
            'blueprint' => $blueprint ? Library::blueprint($blueprint, $user) : null,
            'start' => $start,
            'catalog' => Catalog::for($user),
        ]);
    }

    /**
     * The builder sends choices; the resolver turns them into the structure,
     * pieces, and settings layer a version pins. Nobody's defaults apply.
     *
     * @return array{0: array<string, mixed>, 1: FacetVersion, 2: list<int>, 3: array<string, mixed>, 4: ?string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(Blueprint::VISIBILITIES)],
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'structure' => ['required', 'string', 'max:200'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'package_sets.*' => ['string', 'max:200'],
            'skill_sets' => ['nullable', 'array'],
            'skill_sets.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:200'],
            'settings' => ['nullable', 'array'],
            'settings.sensitive' => ['boolean'],
            'settings.git' => ['boolean'],
            'settings.ai' => ['boolean'],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'languages' => ['sometimes', 'array', 'min:1'],
            'languages.*' => [Rule::in(Project::LANGUAGES)],
            'workflows' => ['sometimes', 'array', 'min:1'],
            'workflows.*' => [Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
        ]);
        $compatibility = $this->compatibility($data);

        $resolved = (new Resolver($request->user(), withDefaults: false))->resolve(Arr::only($data, [
            'name', 'language', 'structure', 'tools', 'package_sets', 'skill_sets', 'skills', 'settings', 'extra_yaml',
        ]));

        return [
            array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility', 'language'])),
            $resolved['structure'],
            array_map(fn (FacetVersion $v) => $v->id, $resolved['pieces']),
            $resolved['layer'],
            $data['notes'] ?? null,
            $compatibility,
        ];
    }

    /**
     * What the blueprint supports, checked so every language it lists has a
     * workflow: Stata has only Scripts only. Everything listed is stored as
     * nothing listed, which means no restriction. What isn't sent stays as
     * it was.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function compatibility(array $data): array
    {
        if (! isset($data['languages']) && ! isset($data['workflows'])) {
            return [];
        }
        $languages = array_values(array_intersect(Project::LANGUAGES, $data['languages'] ?? Project::LANGUAGES));
        $workflows = array_values(array_intersect(ProjectDefaults::WORKFLOW_KEYS, $data['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS));

        if (! in_array($data['language'], $languages, true)) {
            throw ValidationException::withMessages(['languages' => 'Include the default language, '.Project::LANGUAGE_NAMES[$data['language']].'.']);
        }
        foreach ($languages as $language) {
            if (! array_filter($workflows, fn (string $w) => ProjectDefaults::workflowFits($w, $language))) {
                throw ValidationException::withMessages(['workflows' => Project::LANGUAGE_NAMES[$language].' projects need '.($language === 'stata' ? 'Scripts only.' : 'one of these workflows.')]);
            }
        }

        return [
            'languages' => count($languages) === count(Project::LANGUAGES) ? null : $languages,
            'workflows' => count($workflows) === count(ProjectDefaults::WORKFLOW_KEYS) ? null : $workflows,
        ];
    }

    /**
     * POST /blueprints/preview: the files and folders a project from the
     * builder's unsaved choices would get, for one language and workflow.
     * Nobody's defaults apply, as when the blueprint is saved.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'workflow' => ['required', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            'structure' => ['required', 'string', 'max:200'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'skill_sets' => ['nullable', 'array'],
            'skills' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
        ]);

        try {
            $resolved = (new Resolver($request->user(), withDefaults: false))->resolve(['name' => 'project'] + $data);
        } catch (ValidationException $e) {
            return response()->json(['problem' => collect($e->errors())->flatten()->first()], 422);
        }
        $spec = ['name' => 'project'] + DefaultsPreview::merge($resolved['envelope']['layers']);
        $tools = collect($resolved['pieces'])->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool')->map(fn (FacetVersion $v) => $v->facet->key)->values()->all();
        $sensitive = (bool) ($spec['security']['sensitive'] ?? false);

        return response()->json([
            'tree' => ProjectTree::for($spec, $resolved['language'], $tools, $sensitive),
            'render_dirs' => collect((array) ($spec['render_dirs'] ?? []))->map(fn ($to, $key) => ['from' => $spec['directories'][$key] ?? $key, 'to' => $to])->values()->all(),
            'sensitive' => $sensitive,
        ]);
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
