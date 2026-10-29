<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Facet;
use App\Models\Skill;
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

class FacetsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Facets/Index', [
            'facets' => Facet::listedFor($user)->with(['owner', 'latestVersion'])
                ->orderByRaw('user_id is not null')->orderBy('kind')->orderBy('position')->orderBy('name')
                ->get()->map(fn ($f) => Library::facet($f, $user)),
            'categories' => Facet::CATEGORIES,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null, $request->query('kind') === 'base' ? 'base' : 'facet');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$meta, $layer, $notes] = $this->validated($request, $request->input('kind') === 'base' ? 'base' : 'facet');

        $facet = Facet::create($meta + [
            'user_id' => $user->id,
            'key' => Library::uniqueKey(Facet::query(), $meta['name'], $user->id),
        ]);
        $facet->publishVersion(['layer' => $layer, 'notes' => $notes]);

        return to_route('facets.edit', $facet)->with('status', 'facet-saved');
    }

    public function edit(Request $request, Facet $facet): Response
    {
        abort_unless($facet->isEditableBy($request->user()), 403);

        return $this->form($request, $facet, $facet->kind);
    }

    public function update(Request $request, Facet $facet): RedirectResponse
    {
        abort_unless($facet->isEditableBy($request->user()), 403);

        [$meta, $layer, $notes] = $this->validated($request, $facet->kind);

        DB::transaction(function () use ($facet, $meta, $layer, $notes) {
            $facet->update(Arr::except($meta, 'kind'));
            $facet->publishVersion(['layer' => $layer, 'notes' => $notes]);
        });

        return back()->with('status', 'facet-saved');
    }

    public function fork(Request $request, Facet $facet): RedirectResponse
    {
        $user = $request->user();
        abort_unless($facet->isVisibleTo($user), 404);

        $source = $facet->latestVersion;
        $copy = Facet::create([
            'user_id' => $user->id,
            'key' => Library::uniqueKey(Facet::query(), $facet->name, $user->id),
            'kind' => $facet->kind,
            'name' => $facet->name,
            'description' => $facet->description,
            'category' => $facet->category,
            'question' => $facet->question,
            'visibility' => 'private',
            'forked_from_id' => $source->id,
        ]);
        $copy->publishVersion(['layer' => $source->layer, 'notes' => "Forked from {$facet->handle} v{$source->version}"]);

        return to_route('facets.edit', $copy)->with('status', 'facet-forked');
    }

    public function destroy(Request $request, Facet $facet): RedirectResponse
    {
        abort_unless($facet->isEditableBy($request->user()) && ! $facet->isSystem(), 403);

        $versionIds = $facet->versions()->pluck('id');
        $inUse = DB::table('blueprint_version_facets')->whereIn('facet_version_id', $versionIds)->exists()
            || DB::table('blueprint_versions')->whereIn('base_version_id', $versionIds)->exists();

        if ($inUse) {
            return back()->withErrors(['facet' => 'Blueprints use this facet, so it stays. Make it private to hide it from others.']);
        }

        $facet->delete();

        return to_route('facets.index');
    }

    private function form(Request $request, ?Facet $facet, string $kind): Response
    {
        $user = $request->user();
        $layer = $facet?->latestVersion?->layer ?? ($kind === 'base' ? ['type' => 'project'] : []);

        return Inertia::render('Facets/Edit', [
            'facet' => $facet ? Library::facet($facet->load('owner'), $user) + [
                'versions' => $facet->versions()->get(['id', 'version', 'notes', 'created_at']),
            ] : null,
            'kind' => $kind,
            'values' => [
                'settings_yaml' => Layer::toYaml(Arr::except($layer, ['files', 'skills'])),
                'files' => collect($layer['files'] ?? [])->map(fn ($file, $path) => [
                    'path' => $path,
                    'content' => $file['content'] ?? '',
                    'template' => (bool) ($file['template'] ?? false),
                ])->values(),
                'skills' => collect($layer['skills'] ?? [])->map(fn ($entry, $name) => [
                    'name' => $name,
                    'entry' => $entry,
                ])->values(),
            ],
            'categories' => Facet::CATEGORIES,
            'library' => Skill::listedFor($user)->with(['owner', 'latestVersion'])->orderByRaw('user_id is not null')
                ->orderBy('position')->orderBy('key')->get()->map(fn ($s) => Library::skill($s, $user)),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: ?string}
     */
    private function validated(Request $request, string $kind): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', Rule::in(array_keys(Facet::CATEGORIES))],
            'question' => ['nullable', 'string', 'max:200'],
            'visibility' => ['required', Rule::in(Facet::VISIBILITIES)],
            'settings_yaml' => ['nullable', 'string'],
            'files' => ['array'],
            'files.*.path' => ['required', 'string', 'max:255', 'distinct'],
            'files.*.content' => ['nullable', 'string'],
            'files.*.template' => ['boolean'],
            'skills' => ['array'],
            'skills.*.name' => ['required', 'string', 'max:64', 'distinct'],
            'skills.*.skill_id' => ['nullable', 'integer'],
            'skills.*.entry' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        [$layer, $error] = Layer::fromYaml($data['settings_yaml'] ?? '');
        if ($error) {
            throw ValidationException::withMessages(['settings_yaml' => $error]);
        }
        if (array_intersect_key($layer, ['files' => 1, 'skills' => 1])) {
            throw ValidationException::withMessages(['settings_yaml' => 'Edit files and skills in their own sections.']);
        }

        foreach ($data['files'] ?? [] as $file) {
            $layer['files'][$file['path']] = array_filter([
                'content' => $file['content'] ?? '',
                'template' => ($file['template'] ?? false) ?: null,
            ], fn ($v) => $v !== null);
        }

        foreach ($data['skills'] ?? [] as $skill) {
            [$name, $entry] = $this->skillEntry($request, $skill);
            $layer['skills'][$name] = $entry;
        }

        if ($problems = Layer::problems($layer, $kind)) {
            throw ValidationException::withMessages(['settings_yaml' => implode("\n", $problems)]);
        }

        $meta = array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility'])) + [
            'kind' => $kind,
            'category' => $kind === 'base' ? null : ($data['category'] ?? null),
            'question' => $kind === 'base' ? null : ($data['question'] ?? null),
        ];

        return [$meta, $layer, $data['notes'] ?? null];
    }

    /**
     * A skill picked from the library travels by reference or copy under its
     * library name, which R matches against the SKILL.md frontmatter. An
     * entry already in the layer stays as it was.
     *
     * @return array{0: string, 1: ?array}
     */
    private function skillEntry(Request $request, array $skill): array
    {
        if (! empty($skill['skill_id'])) {
            $library = Skill::with('latestVersion')->find($skill['skill_id']);

            if (! $library || ! $library->isVisibleTo($request->user())) {
                throw ValidationException::withMessages(['skills' => "Skill \"{$skill['name']}\" is not available."]);
            }

            return [$library->key, $library->layerEntry()];
        }

        return [$skill['name'], $skill['entry'] ?? null];
    }
}
