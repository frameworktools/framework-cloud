<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Facet;
use App\Models\Project;
use App\Models\Skill;
use App\Support\GitignoreTemplate;
use App\Support\Layer;
use App\Support\Library;
use App\Support\PackageCategories;
use App\Support\PackageSetLayer;
use App\Support\ProjectDefaults;
use App\Support\SkillTags;
use App\Support\StructureLayout;
use App\Support\StructurePreview;
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

        $pieces = Facet::listedFor($user)->where('kind', $kind)->with(['owner', 'latestVersion'])
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get();

        // Package sets by language, each with its packages
        if ($kind === 'package_set') {
            return Inertia::render('Pieces/PackageSets', [
                'sets' => $pieces->map(fn (Facet $f) => Library::facet($f, $user) + [
                    'packages' => self::setPackages($f),
                    'tags' => PackageCategories::setTags($f->key, $f->category),
                ]),
                'language' => $request->query('language', ProjectDefaults::chosenLanguage($user) ?? 'r'),
                'categories' => PackageCategories::setTagOptions(),
            ]);
        }

        // Skill sets with each skill to read, and the packages they need apart
        if ($kind === 'skill_set') {
            $skills = Skill::listedFor($user)->with('owner', 'latestVersion')->orderByRaw('user_id is not null')->get();

            return Inertia::render('Pieces/SkillSets', [
                'sets' => $pieces->map(fn (Facet $f) => Library::facet($f, $user) + [
                    'skills' => collect($f->latestVersion?->catalogLayer()['skills'] ?? [])->map(function ($entry, $key) use ($skills, $f) {
                        // Framework's copy for a packaged skill, else the owner's
                        $skill = ($entry['package'] ?? false) ? $skills->first(fn (Skill $s) => $s->key === $key && $s->isSystem())
                            : ($skills->first(fn (Skill $s) => $s->key === $key && $s->user_id === $f->user_id) ?? $skills->firstWhere('key', $key));

                        return [
                            'key' => (string) $key,
                            'id' => $skill?->id,
                            'name' => $skill?->name ?? (string) $key,
                            'description' => $skill?->description,
                            'summary' => $skill?->summary,
                            'descriptions' => (object) ($skill?->descriptions() ?? []),
                            'language' => $skill?->language,
                            'set_languages' => array_values(array_filter(['r', 'python', 'stata'], fn ($language) => array_key_exists($key, $f->latestVersion->catalogLayer($language)['skills'] ?? []))),
                        ];
                    })->values(),
                    'packages' => self::setPackages($f),
                    'packages_by_language' => collect(['r', 'python', 'stata'])->mapWithKeys(fn ($language) => [$language => collect($f->latestVersion->catalogLayer($language)['packages']['default_packages'] ?? [])->map(fn ($entry, $name) => ['name' => $name, 'source' => $entry['source'] ?? 'cran'])->values()]),
                ]),
                'categories' => SkillTags::categories(),
                'language' => SkillsController::preferredLanguage($user),
            ]);
        }

        // Structures show their folders and files, each with what it renders
        return Inertia::render('Pieces/Structures', [
            'structures' => $pieces->map(fn (Facet $f) => Library::facet($f, $user) + [
                'preview' => StructurePreview::of($f->latestVersion?->layer ?? []),
                'gitignore_content' => $f->latestVersion?->layer['git']['gitignore_content'] ?? '',
            ]),
        ]);
    }

    public function create(Request $request, string $kind): Response
    {
        return $this->form($request, null, $kind);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $user = $request->user();
        [$meta, $layer, $notes, $manualPackages] = $this->validated($request, $kind);

        $facet = Facet::create($meta + [
            'user_id' => $user->id,
            'kind' => $kind,
            'key' => Library::uniqueKey(Facet::query(), $meta['name'], $user->id),
        ]);
        $facet->publishVersion(['layer' => $layer, 'manual_packages' => $manualPackages, 'notes' => $notes]);

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

        [$meta, $layer, $notes, $manualPackages] = $this->validated($request, $kind);
        $variant = $request->input('variant');
        $languageLayers = $facet->latestVersion->language_layers;
        if ($kind === 'skill_set' && $languageLayers) {
            abort_unless(is_string($variant) && in_array($variant, $facet->language ?? Project::LANGUAGES, true), 422);
            foreach (array_keys($facet->latestVersion->layer['skills'] ?? []) as $key) {
                $layer['skills'][$key] ??= null;
            }
            $languageLayers[$variant] = $layer;
            $layer = $facet->latestVersion->layer;
            $meta['language'] = $facet->language;
        }

        DB::transaction(function () use ($facet, $meta, $layer, $notes, $languageLayers, $manualPackages) {
            $facet->update($meta);
            $facet->publishVersion(['layer' => $layer, 'language_layers' => $languageLayers, 'manual_packages' => $manualPackages, 'notes' => $notes]);
        });

        return back()->with('status', 'facet-saved');
    }

    /**
     * A set's packages as rows of the defaults list.
     *
     * @return list<array{name: string, source: string, version: ?string, repo: ?string}>
     */
    private static function setPackages(Facet $facet): array
    {
        return $facet->latestVersion ? PackageSetLayer::rows($facet->latestVersion) : [];
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
            'requirements' => $facet->requirements,
            'category' => $facet->category,
            'language' => $facet->language,
            'visibility' => 'private',
            'forked_from_id' => $source->id,
        ]);
        $copy->publishVersion(['layer' => $source->layer, 'language_layers' => $source->language_layers, 'manual_packages' => $source->manual_packages, 'notes' => "Copied from {$facet->handle} v{$source->version}"]);

        return to_route(self::PATHS[$kind].'.edit', ['facet' => $copy, 'copy' => 1, 'language' => $request->input('language')])->with('status', 'facet-forked');
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
        $variant = null;
        if ($kind === 'skill_set' && $facet?->latestVersion?->language_layers) {
            $asked = $request->query('language', SkillsController::preferredLanguage($user));
            $variant = in_array($asked, $facet->language ?? Project::LANGUAGES, true) ? $asked : ($facet->language[0] ?? 'r');
        }
        $layer = ($variant ? $facet->latestVersion->catalogLayer($variant) : $facet?->latestVersion?->layer) ?? ($kind === 'structure' ? ['type' => 'project'] : []);

        $settings = Arr::except($layer, ['files', 'skills', 'packages']);
        Arr::forget($settings, 'git.gitignore_content');
        if (($settings['git'] ?? null) === []) {
            unset($settings['git']);
        }

        $preferredLanguage = $request->query('language', SkillsController::preferredLanguage($user));
        $preferredLanguage = in_array($preferredLanguage, Project::LANGUAGES, true) ? $preferredLanguage : SkillsController::preferredLanguage($user);

        return Inertia::render('Pieces/Edit', [
            'variant' => $variant,
            'kind' => $kind,
            'path' => self::PATHS[$kind],
            'language' => $variant ?? $preferredLanguage,
            'facet' => $facet ? Library::facet($facet->load('owner'), $user) + [
                'language' => $facet->language,
                'versions' => $facet->versions()->get(['id', 'version', 'notes', 'created_at']),
            ] : null,
            'values' => [
                ...($kind === 'structure' ? StructureLayout::choices($layer) : []),
                'settings_yaml' => Layer::toYaml($settings),
                'package_rows' => $kind === 'package_set' && $facet ? self::setPackages($facet) : [],
                'gitignore_content' => $layer['git']['gitignore_content'] ?? ($facet ? '' : GitignoreTemplate::for('project')),
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
            'library' => $kind === 'skill_set' ? Skill::listedFor($user)->with(['owner', 'latestVersion', 'tags'])->orderByRaw('user_id is not null')
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
            // A package set installs packages for one language, so it names it
            'language' => $kind === 'package_set' ? ['required', Rule::in(Project::LANGUAGES)] : ['nullable', Rule::in(Project::LANGUAGES)],
            'requirements' => ['nullable', 'string', 'max:1000'],
            'settings_yaml' => ['nullable', 'string'],
            'gitignore_content' => ['nullable', 'string', 'max:65536'],
            'packages' => ['nullable', 'string', 'max:5000'],
            'package_rows' => ['sometimes', 'array', 'max:300'],
            'package_rows.*.name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z0-9._-]*$/', 'distinct:ignore_case'],
            'package_rows.*.source' => ['required', Rule::in(['cran', 'bioc', 'github', 'pypi', 'manual'])],
            'package_rows.*.repo' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'package_rows.*.version' => ['nullable', 'string', 'max:100'],
            'package_rows.*.ref' => ['nullable', 'string', 'max:200'],
            'files' => ['array'],
            'files.*.path' => ['required', 'string', 'max:255', 'distinct'],
            'files.*.content' => ['nullable', 'string'],
            'files.*.template' => ['boolean'],
            'skills' => ['array'],
            'skills.*.name' => ['required', 'string', 'max:64', 'distinct'],
            'skills.*.skill_id' => ['nullable', 'integer'],
            'skills.*.entry' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],
            'folders' => ['sometimes', 'array', 'max:500'],
            'folders.*.path' => ['required', 'string', 'max:255'],
            'folders.*.original_path' => ['nullable', 'string', 'max:255'],
            'framework_locations' => ['sometimes', 'array'],
            'framework_locations.functions' => ['nullable', 'string', 'max:255'],
            'framework_locations.cache' => ['nullable', 'string', 'max:255'],
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
            if (array_key_exists('folders', $data)) {
                $layer = StructureLayout::apply($layer, $data['folders'], $data['framework_locations'] ?? []);
            }

            if (array_key_exists('gitignore_content', $data)) {
                $layer['git']['gitignore_content'] = $data['gitignore_content'] ?? '';
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

        $manualPackages = null;
        if ($kind === 'package_set' && array_key_exists('package_rows', $data)) {
            $language = $data['language'];
            $allowedSources = match ($language) {
                'r' => ['cran', 'bioc', 'github'], 'python' => ['pypi', 'github'], 'stata' => ['manual'],
            };
            foreach ($data['package_rows'] as $i => $row) {
                if (! in_array($row['source'], $allowedSources, true) || ($row['source'] === 'github' && empty($row['repo']))) {
                    throw ValidationException::withMessages(["package_rows.{$i}.source" => 'Choose a valid source and repository for this language.']);
                }
            }
            if ($language === 'stata') {
                $manualPackages = array_column($data['package_rows'], 'name');
            } else {
                $layer['packages']['default_packages'] = ProjectDefaults::packageLayer($data['package_rows']);
                foreach ($data['package_rows'] as $row) {
                    if ($row['source'] === 'github' && filled($row['ref'] ?? null)) {
                        $layer['packages']['default_packages'][$row['name']]['ref'] = $row['ref'];
                    }
                }
            }
        } elseif ($kind === 'package_set' && ($data['language'] ?? '') === 'stata') {
            $manualPackages = array_keys($this->packages($data['packages'] ?? '', 'r'));
        } elseif (in_array($kind, ['package_set', 'skill_set'], true) && ($packages = $this->packages($data['packages'] ?? '', $data['language'] ?? 'r'))) {
            $layer['packages'] = ['default_packages' => $packages];
        }
        if ($kind === 'package_set') {
            $layer = PackageSetLayer::installOnly($layer);
            if (empty($layer['packages']['default_packages']) && empty($manualPackages)) {
                throw ValidationException::withMessages([array_key_exists('package_rows', $data) ? 'package_rows' : 'packages' => 'Add at least one package.']);
            }
        }

        if ($problems = Layer::problems($layer, $kind)) {
            throw ValidationException::withMessages([$kind === 'structure' ? 'settings_yaml' : 'packages' => implode("\n", $problems)]);
        }

        $meta = array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility'])) + [
            'requirements' => $kind === 'package_set' && filled($data['requirements'] ?? null) ? trim($data['requirements']) : null,
            'language' => $kind === 'structure' || blank($data['language'] ?? null) ? null : [$data['language']],
        ];

        return [$meta, $layer, $data['notes'] ?? null, $manualPackages];
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

            $language = $request->input('language') ?: 'r';
            if (! $library->fits($language)) {
                throw ValidationException::withMessages(['skills' => "{$library->name} is not available for ".Project::LANGUAGE_NAMES[$language].'.']);
            }

            return [$library->key, $library->layerEntry($language)];
        }

        return [$skill['name'], $skill['entry'] ?? null];
    }
}
