<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Jobs\ScaffoldOnGitHub;
use App\Jobs\UpdateOnGitHub;
use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Facet;
use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use App\Models\User;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\Installations;
use App\Services\GitHub\Pages;
use App\Services\GitHub\PagesFailed;
use App\Services\GitHub\RepositoryTarget;
use App\Services\GitHub\Scaffolder;
use App\Services\Scaffolder\ProjectZip;
use App\Services\Scaffolder\ProjectZipFailed;
use App\Support\BlueprintInput;
use App\Support\Catalog;
use App\Support\Connections;
use App\Support\Layer;
use App\Support\Library;
use App\Support\ProjectData;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
use App\Support\ResolvedSelections;
use App\Support\Resolver;
use App\Support\Share;
use App\Support\Templates;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectsController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        return $this->workspace($request, 'Projects');
    }

    public function dashboard(Request $request): Response|RedirectResponse
    {
        return $this->workspace($request, 'Dashboard');
    }

    private function workspace(Request $request, string $component): Response|RedirectResponse
    {
        // A new account starts with guided setup, once
        if (! $request->user()->setup_completed_at) {
            return redirect()->route('welcome');
        }

        $projectIds = $request->user()->projects()->select('id');

        return Inertia::render($component, [
            'projects' => $request->user()->projects()->listed()->latest()->with('blueprintVersion.blueprint')->get()
                ->map(fn (Project $p) => self::summary($p)),
            'counts' => [
                'projects' => $request->user()->projects()->listed()->count(),
                'artifacts' => Document::whereIn('project_id', $projectIds)->count(),
                'collections' => Collection::whereIn('project_id', $projectIds)->count(),
                'reports' => Report::whereIn('project_id', $projectIds)->count(),
            ],
            'canPublish' => $request->user()->canPublish(),
            'recentArtifacts' => Document::whereIn('project_id', $projectIds)->with(['project.user'])->withCount('versions')
                ->latest('updated_at')->orderByDesc('id')->limit(6)->get()->map(fn (Document $document) => [
                    ...$document->only('id', 'kind', 'visibility', 'updated_at'),
                    'title' => $document->title ?? $document->slug, 'url' => ($document->driver ?? 'cloud') === 'cloud' ? $document->shareUrl() : $document->external_url,
                    'management_url' => '/artifacts/'.$document->id,
                    'password_protected' => $document->isPasswordProtected(),
                    'version_count' => max(1, $document->versions_count),
                    'project' => $document->project->only('id', 'name', 'slug'),
                ]),
            'recentCollections' => Collection::whereIn('project_id', $projectIds)->with('project')->withCount('items')
                ->latest('updated_at')->orderByDesc('id')->limit(4)->get()->map(fn ($collection) => [
                    ...$collection->only('id', 'title', 'description', 'visibility', 'updated_at'),
                    'url' => $collection->shareUrl(), 'management_url' => '/collections/'.$collection->id.'/edit',
                    'password_protected' => filled($collection->password_hash), 'item_count' => $collection->items_count,
                    'project' => $collection->project->only('id', 'name', 'slug'),
                ]),
            'recentReports' => Report::whereIn('project_id', $projectIds)->with('project')->withCount('versions')
                ->latest('updated_at')->orderByDesc('id')->limit(4)->get()->map(fn ($report) => [
                    ...$report->only('id', 'title', 'description', 'visibility', 'updated_at'),
                    'url' => $report->versions_count ? $report->shareUrl() : null,
                    'management_url' => '/reports/'.$report->id.'/edit',
                    'password_protected' => filled($report->password_hash), 'version_count' => $report->versions_count,
                    'project' => $report->project->only('id', 'name', 'slug'),
                ]),
        ]);
    }

    /** A project's pages, in the order the sidebar lists them. */
    public const SECTIONS = ['overview', 'setup', 'github', 'published', 'computers'];

    public function show(Request $request, string $slug, string $section = 'overview'): Response
    {
        $project = $request->user()->projects()->where('slug', $slug)
            ->with('blueprintVersion.blueprint', 'tokens', 'documents.versions', 'linkedDocuments.versions', 'linkedDocuments.project:id,user_id,name,slug,is_home', 'linkedDocuments.project.user:id,handle')->firstOrFail();

        return Inertia::render('Projects/Show', [
            'section' => $section,
            'downloads' => ProjectZip::enabled(),
            'canPublish' => $request->user()->canPublish(),
            'setup' => fn () => $section === 'setup' ? self::setup($project) : null,
            // Creating the repository from the project page, when it has none yet
            'githubSetup' => fn () => in_array($section, ['overview', 'github'], true) && ! $project->github_repo ? self::githubSetup($request->user(), $project) : null,
            'project' => self::summary($project) + [
                'share' => Share::for($project),
                'tokens' => $project->tokens->sortByDesc('id')->values()
                    ->map(fn ($t) => $t->only(['id', 'name', 'created_at', 'last_used_at'])),
                'adopted_at' => $project->adopted_at,
                // The data catalog its computers pull with pull_data_registry(), read-only here
                'data_registry' => $section === 'overview' ? ProjectData::for($project)['entries'] : [],
                'spec' => $project->spec ? [
                    'received_at' => $project->spec_received_at,
                    'json' => json_encode(json_decode($project->spec), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ] : null,
                // Its own artifacts and those linked to it from elsewhere in the account
                'documents' => $project->documents->concat($project->linkedDocuments)->sortByDesc('updated_at')->values()
                    ->map(fn (Document $d) => [
                        'linked_from' => $d->project_id !== $project->id ? ($d->project?->is_home ? 'No project' : $d->project?->name) : null,
                        'id' => $d->id,
                        'title' => $d->title ?? $d->slug,
                        'url' => ($d->driver ?? 'cloud') === 'cloud' ? ($d->readableUrl() ?? $d->shareUrl()) : $d->external_url,
                        'readable' => $d->isReadable(),
                        'kind' => $d->kind,
                        'visibility' => $d->visibility,
                        'views' => $d->views,
                        'size_bytes' => $d->size_bytes,
                        'password_protected' => (bool) $d->password_hash,
                        'versions' => $d->versions->count(),
                        'updated_at' => $d->updated_at,
                    ]),
            ],
        ]);
    }

    /**
     * What the project is made of, read from the layers its computers
     * receive: the structure, tools, and sets with their versions, the
     * skills, and the settings that result.
     */
    private static function setup(Project $project): array
    {
        $layers = collect(ProjectLayers::for($project)['layers']);
        $handles = $layers->whereIn('kind', ['base', 'facet'])->pluck('key');
        $facets = Facet::with('owner')->whereIn('key', $handles->map(fn ($h) => Str::afterLast($h, '/')))->get()
            ->keyBy(fn (Facet $f) => $f->handle);

        $skills = $layers->flatMap(fn (array $l) => array_keys(array_filter($l['layer']['skills'] ?? [], fn ($v) => $v !== null && $v !== false)))->unique()->values();
        $titles = Skill::whereIn('key', $skills)->pluck('name', 'key');
        $last = fn (string $path, mixed $default) => $layers->reverse()->map(fn (array $l) => Arr::get($l['layer'], $path))->first(fn ($v) => $v !== null) ?? $default;

        return [
            'customized' => (bool) $project->composition,
            // Language layers such as framework/jupyter-r belong to their tool and aren't listed
            'pieces' => $layers->whereIn('kind', ['base', 'facet'])->filter(fn (array $l) => $facets->has($l['key']))
                ->map(fn (array $l) => [
                    'kind' => $facets[$l['key']]->kind,
                    'id' => $facets[$l['key']]->id,
                    'name' => $facets[$l['key']]->name,
                    'handle' => $l['key'],
                    'version' => $l['version'] ?? null,
                ])->values(),
            'skills' => $skills->map(fn (string $key) => ['key' => $key, 'name' => $titles[$key] ?? $key]),
            'settings' => [
                'sensitive' => ProjectLayers::isSensitive($layers->all()),
                'git' => (bool) $last('git.use_git', true),
                'agents' => (bool) $last('ai.enabled', true),
                'hooks' => array_keys(array_filter($layers->reduce(fn (array $carry, array $l) => array_merge($carry, $l['layer']['git']['hooks'] ?? []), []))),
            ],
        ];
    }

    /**
     * What the Create on GitHub form needs: whether this server and the
     * person can scaffold, where the app is installed, and whether the
     * project's repository must be private.
     *
     * @return array<string, mixed>
     */
    private static function githubSetup(User $user, Project $project): array
    {
        $account = $user->githubAccount;
        $enabled = Scaffolder::enabled();

        return [
            'enabled' => $enabled,
            'unavailable' => $enabled ? Scaffolder::unavailableFor($project->language ?? 'r') : null,
            'login' => $account?->login,
            'owners' => $account && $enabled ? Installations::for($account) : [],
            'install_url' => Installations::installUrl(),
            'sensitive' => ProjectLayers::isSensitive(ProjectLayers::for($project)['layers'] ?? []),
        ];
    }

    /** What the project list and the sidebar show for one project. */
    private static function summary(Project $p): array
    {
        return $p->only(['id', 'name', 'slug', 'project_type', 'language', 'description', 'created_at']) + [
            'status' => $p->status ?? 'active',
            'active' => $p->active,
            'public_download_url' => $p->publicDownloadUrl(),
            'github' => $p->github_repo ? [
                'repo' => $p->github_repo,
                'url' => $p->githubUrl(),
                'private' => $p->github_private,
                'status' => $p->scaffold_status,
                'run_url' => $p->scaffold_run_url,
                'error' => $p->scaffold_error,
                'pages_url' => $p->pages_url,
                // Update on GitHub: offered once the setup changed after it last went to the repository
                'update_available' => in_array($p->scaffold_status, ['done', null], true) && $p->setup_updated_at
                    && (! $p->github_setup_applied_at || $p->github_setup_applied_at->lt($p->setup_updated_at)),
                'update' => $p->github_update,
                // Before the job creates the repository, and when creating it failed
            ] : (in_array($p->scaffold_status, ['pending', 'running', 'failed'], true) ? ['status' => $p->scaffold_status, 'error' => $p->scaffold_error] : null),
            'blueprint' => $p->blueprintVersion ? [
                'id' => $p->blueprintVersion->blueprint_id,
                'handle' => $p->blueprintVersion->blueprint->handle,
                'name' => $p->blueprintVersion->blueprint->name,
                'version' => $p->blueprintVersion->version,
            ] : null,
        ];
    }

    /** The New project page's steps: a blueprint to start from, the project's choices, and a review. */
    public const STAGES = ['start', 'project', 'review'];

    public function create(Request $request): Response
    {
        $user = $request->user();
        $account = $user->githubAccount;
        $defaults = ProjectDefaults::for($user);

        $blueprints = Catalog::blueprints($user);
        $versions = Blueprint::with('latestVersion')->whereIn('id', array_column($blueprints, 'id'))->get()->mapWithKeys(fn (Blueprint $b) => [$b->id => $b->latestVersion]);

        return Inertia::render('Projects/Create', [
            'blueprints' => collect($blueprints)->map(fn (array $b) => array_replace($b, [
                'sensitive' => $b['choices']['settings']['sensitive'],
                'choices' => self::settingChoices($versions[$b['id']] ?? null, $b['choices']['extra_yaml'] ?? '') + $b['choices'],
            ]))->values()->all(),
            'catalog' => fn () => Catalog::for($user),
            'ggplotThemes' => ProjectDefaults::GGPLOT_THEMES,
            'quartoChoices' => ProjectDefaults::QUARTO_CHOICES,
            'defaults' => $defaults,
            'selected' => $request->integer('blueprint') ?: null,
            // The step and choice tab the page opens on; a blueprint link opens on the project's choices
            'stage' => in_array($request->query('stage'), self::STAGES, true) ? $request->query('stage') : ($request->integer('blueprint') ? 'project' : 'start'),
            'tab' => is_string($request->query('tab')) ? Str::limit($request->query('tab'), 40, '') : null,
            // A language and workflow to start with, as the Blueprints page passes them
            'start' => [
                'language' => in_array($request->query('language'), Project::LANGUAGES, true) ? $request->query('language') : null,
                'workflow' => in_array($request->query('workflow'), ProjectDefaults::WORKFLOW_KEYS, true) ? $request->query('workflow') : null,
                // How to set it up, as the homepage's Download a ZIP and Start from GitHub links pass it
                'destination' => in_array($request->query('destination'), ['download', 'github', 'local'], true) ? $request->query('destination') : null,
            ],
            'downloads' => ProjectZip::enabled(),
            // Languages GitHub can't scaffold here, and why
            'githubUnavailable' => collect(Project::LANGUAGES)->mapWithKeys(fn (string $l) => [$l => Scaffolder::unavailableFor($l)])->filter()->all(),
            'github' => [
                'enabled' => Scaffolder::enabled(),
                'login' => $account?->login,
                'owners' => fn () => $account && Scaffolder::enabled() ? Installations::for($account) : [],
                'install_url' => Installations::installUrl(),
            ],
        ]);
    }

    /**
     * A project starts from a blueprint, optionally with pieces changed. An
     * unchanged blueprint is pinned by version, so later edits to it never
     * change what an existing project was made from; a changed one records
     * its exact pieces.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            ...self::projectRules(),
            // Also save these choices as a personal blueprint, in the blueprint builder's format
            'new_blueprint' => ['nullable', 'array'],
            // download: a zip of the built project; local: a token for setting it up on a computer
            'destination' => ['nullable', Rule::in(['local', 'download', 'github'])],
            'github' => ['nullable', 'array'],
            'github.enabled' => ['boolean'],
            'github.owner' => ['required_if_accepted:github.enabled', 'nullable', 'string', 'max:100'],
            'github.name' => ['required_if_accepted:github.enabled', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'not_in:.,..'],
            'github.private' => ['boolean'],
        ], [
            'github.name.regex' => 'Use letters, numbers, hyphens, underscores, and periods.',
        ]);

        $blueprint = Blueprint::with('owner')->find($validated['blueprint_id']);

        if (! $blueprint?->isVisibleTo($request->user()) || ! $blueprint->latestVersion) {
            return back()->withErrors(['blueprint_id' => 'Choose a blueprint.']);
        }

        // Checked before anything is made, so a problem with the blueprint creates neither
        $newBlueprint = filled($validated['new_blueprint'] ?? null) ? self::blueprintInput($request, $validated['new_blueprint']) : null;

        $resolver = new Resolver($request->user());
        $resolved = $resolver->resolve(self::choicesFrom($validated, $blueprint));

        if (($validated['github']['enabled'] ?? false) && ($reason = Scaffolder::unavailableFor($resolved['language']))) {
            return back()->withErrors(['github.enabled' => $reason]);
        }

        if (($validated['destination'] ?? null) === 'download' && ! ProjectZip::enabled()) {
            return back()->withErrors(['destination' => 'This server is not set up to build downloads.']);
        }

        $sensitive = ProjectLayers::isSensitive($resolved['envelope']['layers']);
        $github = ($validated['github']['enabled'] ?? false) ? RepositoryTarget::for($request->user(), $validated['github'], $sensitive) : null;

        if ($newBlueprint) {
            self::saveBlueprint($request->user(), $newBlueprint);
        }

        $project = $resolver->createProject($validated['name'], $resolved, $validated['description'] ?? null, [
            'scaffold_status' => $github ? 'pending' : null,
            'choices' => self::storedChoices($validated),
        ]);

        if ($github) {
            ScaffoldOnGitHub::dispatch($project, $github['org'], $github['name'], $github['private']);

            return to_route('projects.show', $project->slug);
        }

        // Download: the project page starts the download, and says how to connect the folder later
        if (($validated['destination'] ?? null) === 'download') {
            return to_route('projects.show', $project->slug)->with('status', 'project-download');
        }

        // Set up locally: hand over the token now, on the page that explains it
        return to_route('projects.section', [$project->slug, 'computers'])->with([
            'token' => $project->issuePullToken(),
            'token_language' => $project->language,
            'status' => 'project-token',
            'project_created' => true,
        ]);
    }

    /**
     * The New project form's fields for a project: its name and description,
     * the blueprint and language, and the choices it changed.
     *
     * @return array<string, list<mixed>>
     */
    private static function projectRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'blueprint_id' => ['required', 'integer'],
            'language' => ['nullable', Rule::in(Project::LANGUAGES)],
            'workflow' => ['nullable', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            'description' => ['nullable', 'string', 'max:500'],
            'customized' => ['boolean'],
            'structure' => ['required_if_accepted:customized', 'nullable', 'string', 'max:200'],
            'notebook_format' => ['nullable', Rule::in(['quarto', 'rmarkdown', 'jupyter'])],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
            'settings.hooks' => ['nullable', 'array'],
            'settings.hooks.*' => [Rule::in(ProjectDefaults::HOOKS)],
            ...self::settingRules(),
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'package_sets.*' => ['string', 'max:200'],
            'package_overrides' => ['nullable', 'array'],
            'package_overrides.*' => ['boolean'],
            'package_auto_attach' => ['nullable', 'array'],
            'package_auto_attach.*' => ['boolean'],
            // Packages pushed with push_setup(); the resolver checks each entry
            'package_definitions' => ['nullable', 'array'],
            'package_definitions.*' => ['nullable', 'array'],
            'skill_sets' => ['nullable', 'array'],
            'skill_sets.*' => ['string', 'max:200'],
            'excluded_skills' => ['nullable', 'array'],
            'excluded_skills.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:200'],
            'settings' => ['nullable', 'array'],
            'settings.sensitive' => ['boolean'],
            'settings.git' => ['boolean'],
            // On or off, or the project's own agents: {enabled, assistants: {claude: bool, ...}}
            'settings.ai' => ['nullable'],
            'settings.ai.enabled' => ['sometimes', 'boolean'],
            'settings.ai.assistants' => ['sometimes', 'array'],
            'settings.ai.assistants.*' => ['boolean'],
        ];
    }

    /** The choices to keep on the project, so its edit page opens on them. */
    public static function storedChoices(array $validated): array
    {
        return ['customized' => (bool) ($validated['customized'] ?? false)] + Arr::only($validated, [
            'workflow', 'notebook_format', 'structure', 'tools', 'package_sets', 'package_overrides', 'package_auto_attach', 'package_definitions', 'data_entries',
            'skill_sets', 'skills', 'excluded_skills', 'settings', 'extra_yaml', 'scaffold', 'quarto', 'ide', 'publish_destination', 'connections', 'templates',
        ]);
    }

    /**
     * GET /projects/{slug}/edit: the New project form, opened on the
     * project's name, blueprint, language, and choices.
     */
    public function edit(Request $request, string $slug): Response
    {
        $project = $request->user()->projects()->listed()->where('slug', $slug)->with('blueprintVersion')->firstOrFail();
        $create = $this->create($request);
        $blueprintId = $project->blueprintVersion?->blueprint_id;

        return $create->with([
            'editing' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'name' => $project->name,
                'description' => $project->description,
                'language' => $project->language ?? 'r',
                'blueprint_id' => $blueprintId,
                'choices' => $project->choices,
                // Changed pieces from before choices were kept: the form can't show them
                'unknown_choices' => (bool) $project->composition && ! $project->choices,
                'github_repo' => $project->github_repo,
            ],
            'selected' => $blueprintId,
            'stage' => in_array($request->query('stage'), self::STAGES, true) ? $request->query('stage') : 'project',
        ]);
    }

    /**
     * PUT /projects/{id}: a new name and description, and, with
     * update_setup, the setup resolved again from the form's choices. Tokens,
     * setup commands, and downloads use the new setup from then on. A GitHub
     * repository keeps its files.
     */
    public function update(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->listed()->findOrFail($project);
        $validated = $request->validate([...self::projectRules(), 'update_setup' => ['boolean']]);

        if (! ($validated['update_setup'] ?? false)) {
            $envelope = $project->resolved ? json_decode($project->resolved, true) : null;
            $project->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                // The name names files such as the .Rproj
                'resolved' => $envelope ? json_encode(['name' => $validated['name']] + $envelope, JSON_UNESCAPED_SLASHES) : null,
            ]);

            return to_route('projects.show', $project->slug)->with('status', 'project-updated');
        }

        $blueprint = Blueprint::with('owner')->find($validated['blueprint_id']);
        if (! $blueprint?->isVisibleTo($request->user()) || ! $blueprint->latestVersion) {
            return back()->withErrors(['blueprint_id' => 'Choose a blueprint.']);
        }

        // The data registry changes only through push_data_registry(); the form keeps it
        if (isset($project->choices['data_entries'])) {
            $validated['data_entries'] = $project->choices['data_entries'];
        }
        $resolved = (new Resolver($request->user()))->resolve(self::choicesFrom($validated, $blueprint));
        $project->update(Resolver::setupAttributes($validated['name'], $resolved) + [
            'description' => $validated['description'] ?? null,
            'choices' => self::storedChoices($validated),
            'setup_updated_at' => now(),
        ]);

        return to_route('projects.show', $project->slug)->with('status', 'project-updated');
    }

    /**
     * POST /projects/preview: what the New project form would create, resolved
     * exactly as creating it would: the blueprint with your defaults, or the
     * pieces chosen for this project. The form shows this and starts its
     * customization from it.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blueprint_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', Rule::in(Project::LANGUAGES)],
            'workflow' => ['nullable', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            'customized' => ['boolean'],
            'structure' => ['nullable', 'string', 'max:200'],
            'notebook_format' => ['nullable', Rule::in(['quarto', 'rmarkdown', 'jupyter'])],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
            'settings.hooks' => ['nullable', 'array'],
            'settings.hooks.*' => [Rule::in(ProjectDefaults::HOOKS)],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'package_sets.*' => ['string', 'max:200'],
            'package_overrides' => ['nullable', 'array'],
            'package_overrides.*' => ['boolean'],
            'package_auto_attach' => ['nullable', 'array'],
            'package_auto_attach.*' => ['boolean'],
            'package_definitions' => ['nullable', 'array'],
            'package_definitions.*' => ['nullable', 'array'],
            'skill_sets' => ['nullable', 'array'],
            'skill_sets.*' => ['string', 'max:200'],
            'excluded_skills' => ['nullable', 'array'],
            'excluded_skills.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:200'],
            'settings' => ['nullable', 'array'],
            // As store() validates them, so the preview resolves what creating would
            'settings.sensitive' => ['boolean'],
            'settings.git' => ['boolean'],
            'settings.ai' => ['nullable'],
            'settings.ai.enabled' => ['sometimes', 'boolean'],
            'settings.ai.assistants' => ['sometimes', 'array'],
            'settings.ai.assistants.*' => ['boolean'],
            ...self::settingRules(),
        ]);
        $blueprint = Blueprint::with('owner', 'latestVersion.facetVersions.facet')->find($validated['blueprint_id']);
        abort_unless($blueprint?->isVisibleTo($request->user()) && $blueprint->latestVersion, 404);

        try {
            $resolved = (new Resolver($request->user()))->resolve(self::choicesFrom(['name' => filled($validated['name'] ?? null) ? $validated['name'] : 'project'] + $validated, $blueprint));
        } catch (ValidationException $e) {
            return response()->json(['problem' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(ResolvedSelections::for($request->user(), $resolved, $blueprint->latestVersion, $validated['customized'] ?? false));
    }

    /**
     * A blueprint version's scaffold, Quarto, editor, publishing, and connection settings
     * as a project from it gets them: the version's own values over
     * Framework's, as creating the project from it as it is would apply them.
     * Choices that move into these settings leave the extra settings YAML.
     *
     * @return array<string, mixed>
     */
    private static function settingChoices(?BlueprintVersion $version, string $extraYaml): array
    {
        $layer = $version?->layer ?? [];
        [$extra] = Layer::fromYaml($extraYaml);

        return [
            'scaffold' => ProjectDefaults::scaffold((array) ($layer['scaffold'] ?? [])),
            'quarto' => ProjectDefaults::quarto((array) ($layer['quarto'] ?? [])),
            'ide' => BlueprintInput::ide(ProjectDefaults::ide($version)) ?? ProjectDefaults::IDE,
            'publish_destination' => BlueprintInput::destination($layer) ?? '',
            'connections' => Connections::split($layer)[0],
            'templates' => Templates::split($layer)[0],
            'extra_yaml' => is_array($extra) ? Layer::toYaml(BlueprintInput::withoutSettings($extra)) : $extraYaml,
        ];
    }

    /**
     * Rules for a project's scaffold, Quarto, editor, and publishing
     * choices, as the blueprint builder checks them.
     *
     * @return array<string, list<mixed>>
     */
    private static function settingRules(): array
    {
        $rules = [
            'scaffold' => ['sometimes', 'nullable', 'array'],
            'scaffold.seed_on_scaffold' => ['boolean'],
            'scaffold.seed' => ['nullable', 'regex:/^-?\d{1,9}$/'],
            'scaffold.set_theme_on_scaffold' => ['boolean'],
            'scaffold.ggplot_theme' => ['nullable', Rule::in(ProjectDefaults::GGPLOT_THEMES)],
            'scaffold.source_all_functions' => ['boolean'],
            'quarto' => ['sometimes', 'nullable', 'array'],
            'quarto.html' => ['array'],
            'quarto.revealjs' => ['array'],
            'ide' => ['sometimes', 'nullable', Rule::in(BlueprintInput::IDE_CHOICES)],
            'publish_destination' => ['sometimes', 'nullable', Rule::in(['', ...BlueprintInput::DESTINATIONS])],
            ...Connections::rules(),
            ...Templates::rules(),
        ];
        foreach (ProjectDefaults::QUARTO as $format => $values) {
            foreach ($values as $key => $default) {
                $choices = ProjectDefaults::QUARTO_CHOICES["{$format}.{$key}"]
                    ?? ProjectDefaults::QUARTO_CHOICES[str_ends_with($key, 'transition') ? 'transition' : $key] ?? [];
                $rules["quarto.{$format}.{$key}"] = match (true) {
                    is_bool($default) => ['boolean'],
                    is_int($default) => ['integer', 'between:1,6'],
                    default => [Rule::in($choices)],
                };
            }
        }

        return $rules;
    }

    /**
     * The resolver's choices for the New project form: the blueprint as it
     * is, or, customized, exactly the pieces shown. Lists that are present,
     * even empty, are the project's own.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function choicesFrom(array $validated, Blueprint $blueprint): array
    {
        // Pushed packages are the project's own, over the blueprint or the pieces chosen
        return ($validated['customized'] ?? false)
            ? ['from' => $blueprint->handle] + Arr::only($validated, ['name', 'language', 'workflow', 'notebook_format', 'structure', 'tools', 'package_sets', 'package_overrides', 'package_auto_attach', 'package_definitions', 'data_entries', 'skill_sets', 'skills', 'excluded_skills', 'settings', 'extra_yaml', 'scaffold', 'quarto', 'ide', 'publish_destination', 'connections', 'templates'])
            : ['blueprint' => $blueprint->handle] + Arr::only($validated, ['name', 'language', 'workflow', 'package_definitions', 'data_entries']);
    }

    /**
     * Blueprint choices from the New project form, validated as the blueprint
     * builder validates them. Problems are reported under new_blueprint.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, mixed> as BlueprintInput::validated() returns them
     */
    private static function blueprintInput(Request $request, array $input): array
    {
        $sub = Request::create('/blueprints', 'POST', $input);
        $sub->setUserResolver(fn () => $request->user());

        try {
            return BlueprintInput::validated($sub);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ["new_blueprint.{$key}" => $messages])->all());
        }
    }

    /** @param  array<int, mixed>  $input  from blueprintInput() */
    private static function saveBlueprint(User $user, array $input): Blueprint
    {
        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = $input;

        return DB::transaction(function () use ($user, $meta, $base, $facetVersionIds, $layer, $notes, $compatibility) {
            $blueprint = Blueprint::create($meta + [
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $meta['name'], $user->id),
                'is_system' => false,
            ]);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes, $compatibility);

            return $blueprint;
        });
    }

    /**
     * GET /projects/{id}/download: the project built by the scaffolder, as a
     * zip. Built fresh each time, so it always matches the project's layers.
     */
    public function download(Request $request, int $project, ProjectZip $zips): RedirectResponse|BinaryFileResponse
    {
        $project = $request->user()->projects()->findOrFail($project);

        try {
            $path = $zips->build($project);
        } catch (ProjectZipFailed $e) {
            return to_route('projects.show', $project->slug)->withErrors(['download' => $e->getMessage()]);
        }

        return response()->download($path, "{$project->slug}.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** Set where a project stands: active, triaged, completed, or inactive. Only active ones make the main list. */
    public function setStatus(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->listed()->findOrFail($project);
        $project->update($request->validate(['status' => ['required', Rule::in(Project::STATUSES)]]));

        return back();
    }

    /** Turn the public download link on or off. */
    public function setPublicDownload(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->listed()->findOrFail($project);
        $project->setPublicDownload((bool) $request->validate(['on' => ['required', 'boolean']])['on']);

        return back();
    }

    /** Ask GitHub how the scaffold run went, in case a webhook was missed. */
    public function refreshScaffold(Request $request, int $project, Scaffolder $scaffolder): RedirectResponse
    {
        $project = $request->user()->projects()->whereNotNull('github_repo')->findOrFail($project);

        return $this->onGitHub(fn () => $scaffolder->refresh($project));
    }

    public function retryScaffold(Request $request, int $project, Scaffolder $scaffolder): RedirectResponse
    {
        $project = $request->user()->projects()->whereNotNull('github_repo')->where('scaffold_status', 'failed')->findOrFail($project);

        return $this->onGitHub(fn () => $scaffolder->retry($project));
    }

    public function enablePages(Request $request, int $project, Pages $pages): RedirectResponse
    {
        $project = $request->user()->projects()->where('scaffold_status', 'done')->whereNotNull('github_repo')->findOrFail($project);

        return $this->onGitHub(fn () => $pages->enable($project));
    }

    public function disablePages(Request $request, int $project, Pages $pages): RedirectResponse
    {
        $project = $request->user()->projects()->whereNotNull('pages_url')->findOrFail($project);

        return $this->onGitHub(fn () => $pages->disable($project));
    }

    private function onGitHub(callable $call): RedirectResponse
    {
        try {
            $call();
        } catch (GitHubNotConnected|PagesFailed $e) {
            return back()->withErrors(['github' => $e->getMessage()]);
        } catch (RequestException $e) {
            return back()->withErrors(['github' => 'GitHub returned an error: '.($e->response->json('message') ?? $e->response->status())]);
        }

        return back();
    }

    /**
     * POST /projects/{id}/scaffold: create a repository for a project that
     * has none and scaffold it there, checked as the New project form checks
     * it. The owner must be an account where the app is installed, and a
     * sensitive project's repository is always private.
     */
    public function scaffold(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->listed()->findOrFail($project);

        if ($project->github_repo) {
            return back()->withErrors(['github' => "This project already has a repository: {$project->github_repo}."]);
        }
        if (in_array($project->scaffold_status, ['pending', 'running'], true)) {
            return back()->withErrors(['github' => 'The repository is still being created.']);
        }

        $github = $request->validate([
            'github' => ['required', 'array'],
            'github.owner' => ['required', 'string', 'max:100'],
            'github.name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'not_in:.,..'],
            'github.private' => ['boolean'],
        ], [
            'github.name.regex' => 'Use letters, numbers, hyphens, underscores, and periods.',
        ])['github'];

        if ($reason = Scaffolder::unavailableFor($project->language ?? 'r')) {
            return back()->withErrors(['github.enabled' => $reason]);
        }

        $target = RepositoryTarget::for($request->user(), $github, ProjectLayers::isSensitive(ProjectLayers::for($project)['layers'] ?? []));

        $project->update(['scaffold_status' => 'pending', 'scaffold_error' => null, 'scaffold_run_url' => null]);
        ScaffoldOnGitHub::dispatch($project, $target['org'], $target['name'], $target['private']);

        return back();
    }

    /**
     * POST /projects/{id}/github/update: propose Framework's regenerated
     * files in a pull request, built on the queue. The app must be
     * installed on the repository's owner.
     */
    public function updateGitHub(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->listed()->whereNotNull('github_repo')->findOrFail($project);

        if (in_array($project->scaffold_status, ['pending', 'running', 'failed'], true)) {
            return back()->withErrors(['github' => 'Finish creating the repository first.']);
        }
        if (in_array($project->github_update['status'] ?? null, ['pending', 'running'], true)) {
            return back()->withErrors(['github' => 'An update is already under way.']);
        }
        if (! ProjectZip::enabled()) {
            return back()->withErrors(['github' => 'This server is not set up to build project files.']);
        }
        $account = $request->user()->githubAccount;
        if (! $account) {
            return back()->withErrors(['github' => 'Connect GitHub in settings first.']);
        }
        $owner = Str::before($project->github_repo, '/');
        if (! collect(Installations::for($account) ?? [])->contains(fn (array $o) => strcasecmp($o['login'], $owner) === 0)) {
            return back()->withErrors(['github' => "Install the Framework app on {$owner} first."]);
        }

        $project->update(['github_update' => ['status' => 'pending']]);
        UpdateOnGitHub::dispatch($project);

        return back();
    }

    /** Issue a project token, shown once on the page that asked for it. */
    public function issueToken(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);

        return back()->with([
            'token' => $project->issuePullToken(),
            'token_language' => $project->language,
            'status' => 'project-token',
        ]);
    }

    /** Revoke one of a project's tokens, such as one a computer no longer needs. */
    public function revokeToken(Request $request, int $project, int $token): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);
        $project->tokens()->whereKey($token)->delete();

        return back(303);
    }

    public function destroy(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);
        // To the Trash: its tokens stop working while it is there and work again when it is restored
        $project->delete();

        return to_route('projects');
    }
}
