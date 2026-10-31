<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Support\Catalog;
use App\Support\Layer;
use App\Support\Library;
use App\Support\Resolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlueprintsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Blueprints/Index', [
            'blueprints' => Blueprint::listedFor($user)->with(Library::BLUEPRINT_WITH)
                ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')
                ->get()->map(fn ($b) => Library::blueprint($b, $user) + [
                    'fragments' => $b->latestVersion ? Library::blueprintVersion($b->latestVersion) : null,
                ]),
        ]);
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
        [$meta, $base, $facetVersionIds, $layer, $notes] = $this->validated($request);

        $blueprint = DB::transaction(function () use ($user, $meta, $base, $facetVersionIds, $layer, $notes) {
            $blueprint = Blueprint::create($meta + [
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $meta['name'], $user->id),
                'is_system' => false,
            ]);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes);

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

        [$meta, $base, $facetVersionIds, $layer, $notes] = $this->validated($request);

        DB::transaction(function () use ($blueprint, $meta, $base, $facetVersionIds, $layer, $notes) {
            $blueprint->update($meta);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes);
        });

        return to_route('blueprints.show', $blueprint)->with('status', 'blueprint-saved');
    }

    public function fork(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $user = $request->user();
        abort_unless($blueprint->isVisibleTo($user), 404);

        $source = $blueprint->latestVersion;

        $copy = DB::transaction(function () use ($user, $blueprint, $source) {
            $copy = Blueprint::create([
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $blueprint->name, $user->id),
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'language' => $blueprint->language,
                'visibility' => 'private',
                'forked_from_id' => $source->id,
                'is_system' => false,
            ]);
            $copy->publish(
                $source->base,
                $source->facetVersions->pluck('id')->all(),
                $source->layer ?? [],
                "Forked from {$blueprint->handle} v{$source->version}",
            );

            return $copy;
        });

        return to_route('blueprints.edit', $copy)->with('status', 'blueprint-forked');
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
        ]);

        $resolved = (new Resolver($request->user(), withDefaults: false))->resolve(Arr::only($data, [
            'name', 'language', 'structure', 'tools', 'package_sets', 'skill_sets', 'skills', 'settings', 'extra_yaml',
        ]));

        return [
            array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility', 'language'])),
            $resolved['structure'],
            array_map(fn (FacetVersion $v) => $v->id, $resolved['pieces']),
            $resolved['layer'],
            $data['notes'] ?? null,
        ];
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
