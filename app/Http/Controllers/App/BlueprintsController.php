<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Support\Layer;
use App\Support\Library;
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
            $start = $source?->isVisibleTo($request->user()) ? Library::blueprintVersion($source->latestVersion) : null;
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

        return $this->builder($request, $blueprint, Library::blueprintVersion($blueprint->latestVersion));
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

    /** @param array<string, mixed>|null $start a version to begin from */
    private function builder(Request $request, ?Blueprint $blueprint, ?array $start): Response
    {
        $user = $request->user();
        $facets = Facet::listedFor($user)->with(['owner', 'latestVersion'])
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get();

        return Inertia::render('Blueprints/Builder', [
            'blueprint' => $blueprint ? Library::blueprint($blueprint, $user) : null,
            'start' => $start,
            'bases' => $facets->where('kind', 'structure')->values()->map(fn ($f) => Library::facet($f, $user)),
            'facets' => $facets->whereIn('kind', Facet::PIECES)->values()->map(fn ($f) => Library::facet($f, $user)),
            'categories' => Facet::CATEGORIES,
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: FacetVersion, 2: list<int>, 3: array<string, mixed>, 4: ?string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(Blueprint::VISIBILITIES)],
            'language' => ['nullable', Rule::in(Project::LANGUAGES)],
            'base_version_id' => ['required', 'integer'],
            'facet_version_ids' => ['array'],
            'facet_version_ids.*' => ['integer'],
            'settings_yaml' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $ids = array_map('intval', $data['facet_version_ids'] ?? []);
        $versions = FacetVersion::with('facet')->whereIn('id', [$data['base_version_id'], ...$ids])->get()->keyBy('id');

        $base = $versions->get((int) $data['base_version_id']);
        if (! $base || ! $base->facet->isStructure() || ! $base->facet->isVisibleTo($user)) {
            throw ValidationException::withMessages(['base_version_id' => 'Choose a project type.']);
        }

        if ($problem = Project::typeProblem($data['language'] ?? 'r', $base->layer['type'] ?? 'bare')) {
            throw ValidationException::withMessages(['language' => $problem]);
        }

        $facetIds = [];
        foreach ($ids as $id) {
            $version = $versions->get($id);
            if (! $version || ! in_array($version->facet->kind, [...Facet::PIECES, 'legacy'], true) || ! $version->facet->isVisibleTo($user)) {
                throw ValidationException::withMessages(['facet_version_ids' => 'One of the chosen facets is not available.']);
            }
            if (in_array($version->facet_id, $facetIds, true)) {
                throw ValidationException::withMessages(['facet_version_ids' => "{$version->facet->name} is in this blueprint twice."]);
            }
            $facetIds[] = $version->facet_id;
        }

        [$layer, $error] = Layer::fromYaml($data['settings_yaml'] ?? '');
        if ($error || ($problems = Layer::problems($layer, 'blueprint'))) {
            throw ValidationException::withMessages(['settings_yaml' => $error ?? implode("\n", $problems)]);
        }

        return [array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility', 'language'])), $base, $ids, $layer, $data['notes'] ?? null];
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
