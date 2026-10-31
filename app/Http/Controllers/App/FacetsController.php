<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Facet;
use App\Models\Project;
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

/**
 * The pieces people build and share: project structures, package sets, and
 * skill sets, each at its own URL (/structures, /package-sets, /skill-sets).
 * They are stored as typed facets. Framework writes tools and settings, so
 * those have no pages.
 */
class FacetsController extends Controller
{
    /** URL segment for each kind people can make */
    public const PATHS = ['structure' => 'structures', 'package_set' => 'package-sets', 'skill_set' => 'skill-sets'];

    public function index(Request $request, string $kind): Response
    {
        $user = $request->user();

        return Inertia::render('Pieces/Index', [
            'kind' => $kind,
            'path' => self::PATHS[$kind],
            'pieces' => Facet::listedFor($user)->where('kind', $kind)->with(['owner', 'latestVersion'])
                ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')
                ->get()->map(fn ($f) => Library::facet($f, $user)),
        ]);
    }

    public function create(Request $request, string $kind): Response
    {
        return $this->form($request, null, $kind);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $user = $request->user();
        [$meta, $layer, $notes] = $this->validated($request, $kind);

        $facet = Facet::create($meta + [
            'user_id' => $user->id,
            'kind' => $kind,
            'key' => Library::uniqueKey(Facet::query(), $meta['name'], $user->id),
        ]);
        $facet->publishVersion(['layer' => $layer, 'notes' => $notes]);

        return to_route(self::PATHS[$kind].'.edit', $facet)->with('status', 'facet-saved');
    }

    public function edit(Request $request, Facet $facet, string $kind): Response
    {
        abort_unless($facet->kind === $kind && $facet->isEditableBy($request->user()), 403);

        return $this->form($request, $facet, $kind);
    }

    public function update(Request $request, Facet $facet, string $kind): RedirectResponse
    {
        abort_unless($facet->kind === $kind && $facet->isEditableBy($request->user()), 403);

        [$meta, $layer, $notes] = $this->validated($request, $kind);

        DB::transaction(function () use ($facet, $meta, $layer, $notes) {
            $facet->update($meta);
            $facet->publishVersion(['layer' => $layer, 'notes' => $notes]);
        });

        return back()->with('status', 'facet-saved');
    }

    public function fork(Request $request, Facet $facet, string $kind): RedirectResponse
    {
        $user = $request->user();
        abort_unless($facet->kind === $kind && $facet->isVisibleTo($user), 404);

        $source = $facet->latestVersion;
        $copy = Facet::create([
            'user_id' => $user->id,
            'key' => Library::uniqueKey(Facet::query(), $facet->name, $user->id),
            'kind' => $facet->kind,
            'name' => $facet->name,
            'description' => $facet->description,
            'language' => $facet->language,
            'visibility' => 'private',
            'forked_from_id' => $source->id,
        ]);
        $copy->publishVersion(['layer' => $source->layer, 'notes' => "Copied from {$facet->handle} v{$source->version}"]);

        return to_route(self::PATHS[$kind].'.edit', $copy)->with('status', 'facet-forked');
    }

    public function destroy(Request $request, Facet $facet, string $kind): RedirectResponse
    {
        abort_unless($facet->kind === $kind && $facet->isEditableBy($request->user()) && ! $facet->isSystem(), 403);

        $versionIds = $facet->versions()->pluck('id');
        $inUse = DB::table('blueprint_version_facets')->whereIn('facet_version_id', $versionIds)->exists()
            || DB::table('blueprint_versions')->whereIn('base_version_id', $versionIds)->exists()
            || Project::whereNotNull('composition')->get(['composition'])->contains(fn ($p) => $versionIds->intersect([$p->composition['structure_version_id'] ?? 0, ...($p->composition['piece_version_ids'] ?? [])])->isNotEmpty());

        if ($inUse) {
            return back()->withErrors(['facet' => 'Blueprints or projects use this, so it stays. Make it private to hide it from others.']);
        }

        $facet->delete();

        return to_route(self::PATHS[$kind].'.index');
    }

    private function form(Request $request, ?Facet $facet, string $kind): Response
    {
        $user = $request->user();
        $layer = $facet?->latestVersion?->layer ?? ($kind === 'structure' ? ['type' => 'project'] : []);

        return Inertia::render('Pieces/Edit', [
            'kind' => $kind,
            'path' => self::PATHS[$kind],
            'facet' => $facet ? Library::facet($facet->load('owner'), $user) + [
                'language' => $facet->language,
                'versions' => $facet->versions()->get(['id', 'version', 'notes', 'created_at']),
            ] : null,
            'values' => [
                'settings_yaml' => Layer::toYaml(Arr::except($layer, ['files', 'skills', 'packages'])),
                'packages' => collect($layer['packages']['default_packages'] ?? [])->map(fn ($spec, $name) => ($spec['source'] ?? null) === 'github' && ! empty($spec['repo']) ? $spec['repo'] : $name)->sort(SORT_NATURAL | SORT_FLAG_CASE)->implode("\n"),
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
            'library' => $kind === 'skill_set' ? Skill::listedFor($user)->with(['owner', 'latestVersion'])->orderByRaw('user_id is not null')
                ->orderBy('position')->orderBy('key')->get()->map(fn ($s) => Library::skill($s, $user)) : [],
        ]);
    }

    /**
     * Each kind holds only its own part: a structure its settings and files,
     * a package set its packages, and a skill set its skills and the
     * packages they need.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: ?string}
     */
    private function validated(Request $request, string $kind): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(Facet::VISIBILITIES)],
            'language' => ['nullable', Rule::in(Project::LANGUAGES)],
            'settings_yaml' => ['nullable', 'string'],
            'packages' => ['nullable', 'string', 'max:5000'],
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

        $layer = [];

        if ($kind === 'structure') {
            [$layer, $error] = Layer::fromYaml($data['settings_yaml'] ?? '');
            if ($error) {
                throw ValidationException::withMessages(['settings_yaml' => $error]);
            }
            if (array_intersect_key($layer, ['files' => 1, 'skills' => 1, 'packages' => 1])) {
                throw ValidationException::withMessages(['settings_yaml' => 'A project structure holds folders, settings, and files. Put packages in a package set and skills in a skill set.']);
            }

            foreach ($data['files'] ?? [] as $file) {
                $layer['files'][$file['path']] = array_filter([
                    'content' => $file['content'] ?? '',
                    'template' => ($file['template'] ?? false) ?: null,
                ], fn ($v) => $v !== null);
            }
        }

        if ($kind === 'skill_set') {
            foreach ($data['skills'] ?? [] as $skill) {
                [$name, $entry] = $this->skillEntry($request, $skill);
                $layer['skills'][$name] = $entry;
            }
        }

        if (in_array($kind, ['package_set', 'skill_set'], true) && ($packages = $this->packages($data['packages'] ?? '', $data['language'] ?? 'r'))) {
            $layer['packages'] = ['default_packages' => $packages];
        }

        if ($kind === 'package_set' && empty($layer['packages'])) {
            throw ValidationException::withMessages(['packages' => 'List at least one package.']);
        }

        if ($problems = Layer::problems($layer, $kind)) {
            throw ValidationException::withMessages([$kind === 'structure' ? 'settings_yaml' : 'packages' => implode("\n", $problems)]);
        }

        $meta = array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility'])) + [
            'language' => $kind === 'structure' || blank($data['language'] ?? null) ? null : [$data['language']],
        ];

        return [$meta, $layer, $data['notes'] ?? null];
    }

    /**
     * One package per line. R packages come from CRAN and attach on
     * scaffold, and a line may name a GitHub repository (owner/name) instead.
     * Python packages come from PyPI.
     *
     * @return array<string, array{source: string, auto_attach: bool}>
     */
    private function packages(string $text, string $language): array
    {
        $packages = [];

        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^(?:[A-Za-z0-9-]+\/)?[A-Za-z][A-Za-z0-9._-]*$/', $line)) {
                throw ValidationException::withMessages(['packages' => "\"{$line}\" is not a package name."]);
            }

            $name = str_contains($line, '/') ? substr($line, strpos($line, '/') + 1) : $line;
            $packages[$name] = match (true) {
                $language === 'python' => ['source' => 'pypi', 'auto_attach' => false],
                str_contains($line, '/') => ['source' => 'github', 'repo' => $line, 'auto_attach' => true],
                default => ['source' => 'cran', 'auto_attach' => true],
            };
        }

        return $packages;
    }

    /**
     * A skill picked from Skills travels by reference or copy under its key,
     * which R matches against the SKILL.md frontmatter. An entry already in
     * the layer stays as it was.
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
