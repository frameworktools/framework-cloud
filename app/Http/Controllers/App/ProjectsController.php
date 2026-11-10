<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Jobs\ScaffoldOnGitHub;
use App\Models\Blueprint;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Facet;
use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\Installations;
use App\Services\GitHub\Pages;
use App\Services\GitHub\PagesFailed;
use App\Services\GitHub\RepositoryTarget;
use App\Services\GitHub\Scaffolder;
use App\Services\Scaffolder\ProjectZip;
use App\Services\Scaffolder\ProjectZipFailed;
use App\Support\Catalog;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
use App\Support\ResolvedSelections;
use App\Support\Resolver;
use App\Support\Share;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            'project' => self::summary($project) + [
                'share' => Share::for($project),
                'tokens' => $project->tokens->sortByDesc('id')->values()
                    ->map(fn ($t) => $t->only(['id', 'name', 'created_at', 'last_used_at'])),
                'adopted_at' => $project->adopted_at,
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

    /** What the project list and the sidebar show for one project. */
    private static function summary(Project $p): array
    {
        return $p->only(['id', 'name', 'slug', 'project_type', 'language', 'description', 'created_at']) + [
            'active' => (bool) ($p->active ?? true),
            'public_download_url' => $p->publicDownloadUrl(),
            'github' => $p->github_repo ? [
                'repo' => $p->github_repo,
                'url' => $p->githubUrl(),
                'private' => $p->github_private,
                'status' => $p->scaffold_status,
                'run_url' => $p->scaffold_run_url,
                'error' => $p->scaffold_error,
                'pages_url' => $p->pages_url,
            ] : ($p->scaffold_status === 'failed' ? ['status' => 'failed', 'error' => $p->scaffold_error] : null),
            'blueprint' => $p->blueprintVersion ? [
                'id' => $p->blueprintVersion->blueprint_id,
                'handle' => $p->blueprintVersion->blueprint->handle,
                'name' => $p->blueprintVersion->blueprint->name,
                'version' => $p->blueprintVersion->version,
            ] : null,
        ];
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $account = $user->githubAccount;
        $defaults = ProjectDefaults::for($user);

        return Inertia::render('Projects/Create', [
            'blueprints' => collect(Catalog::blueprints($user))->map(fn (array $b) => $b + [
                'sensitive' => $b['choices']['settings']['sensitive'],
            ])->all(),
            'catalog' => fn () => Catalog::for($user),
            'defaults' => $defaults,
            'selected' => $request->integer('blueprint') ?: null,
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
            'name' => ['required', 'string', 'max:100'],
            'blueprint_id' => ['required', 'integer'],
            'language' => ['nullable', Rule::in(Project::LANGUAGES)],
            'workflow' => ['nullable', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            'description' => ['nullable', 'string', 'max:500'],
            'customized' => ['boolean'],
            'structure' => ['required_if_accepted:customized', 'nullable', 'string', 'max:200'],
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
            // On or off, or the project's own agents: {enabled, assistants: {claude: bool, ...}}
            'settings.ai' => ['nullable'],
            'settings.ai.enabled' => ['sometimes', 'boolean'],
            'settings.ai.assistants' => ['sometimes', 'array'],
            'settings.ai.assistants.*' => ['boolean'],
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

        $project = $resolver->createProject($validated['name'], $resolved, $validated['description'] ?? null, [
            'scaffold_status' => $github ? 'pending' : null,
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
        ]);
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
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'package_sets.*' => ['string', 'max:200'],
            'skill_sets' => ['nullable', 'array'],
            'skill_sets.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:200'],
            'settings' => ['nullable', 'array'],
            'settings.ai' => ['nullable'],
            'settings.ai.assistants' => ['sometimes', 'array'],
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
     * The resolver's choices for the New project form: the blueprint as it
     * is, or, customized, exactly the pieces shown. Lists that are present,
     * even empty, are the project's own.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private static function choicesFrom(array $validated, Blueprint $blueprint): array
    {
        return ($validated['customized'] ?? false)
            ? ['from' => $blueprint->handle] + Arr::only($validated, ['name', 'language', 'workflow', 'structure', 'tools', 'package_sets', 'skill_sets', 'skills', 'settings'])
            : ['blueprint' => $blueprint->handle] + Arr::only($validated, ['name', 'language', 'workflow']);
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

    /** Mark a project active or inactive; inactive projects leave the main Projects list. */
    public function setActive(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->listed()->findOrFail($project);
        $project->update(['active' => $request->validate(['active' => ['required', 'boolean']])['active']]);

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
     * Check where the repository goes before the project exists. The owner
     * must be an account where the app is installed, and a sensitive
     * project's repository is always private.
     *
     * @return array{org: ?string, name: string, private: bool}
     */
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
