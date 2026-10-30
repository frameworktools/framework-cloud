<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Jobs\ScaffoldOnGitHub;
use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Project;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\Installations;
use App\Services\GitHub\Scaffolder;
use App\Support\Library;
use App\Support\ProjectLayers;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Projects', [
            'projects' => $user->projects()->latest()->with('blueprintVersion.blueprint.owner')->get()
                ->map(fn (Project $p) => $p->only(['id', 'name', 'slug', 'project_type', 'description', 'created_at']) + [
                    'github' => $p->github_repo ? [
                        'repo' => $p->github_repo,
                        'url' => $p->githubUrl(),
                        'private' => $p->github_private,
                        'status' => $p->scaffold_status,
                        'run_url' => $p->scaffold_run_url,
                        'error' => $p->scaffold_error,
                    ] : ($p->scaffold_status === 'failed' ? ['status' => 'failed', 'error' => $p->scaffold_error] : null),
                    'blueprint' => $p->blueprintVersion ? [
                        'id' => $p->blueprintVersion->blueprint_id,
                        'handle' => $p->blueprintVersion->blueprint->handle,
                        'name' => $p->blueprintVersion->blueprint->name,
                        'version' => $p->blueprintVersion->version,
                    ] : null,
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $account = $user->githubAccount;

        return Inertia::render('Projects/Create', [
            'blueprints' => Blueprint::listedFor($user)->with(Library::BLUEPRINT_WITH)
                ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')
                ->get()->filter(fn (Blueprint $b) => $b->latestVersion)
                ->map(fn (Blueprint $b) => Library::blueprint($b, $user) + [
                    'sensitive' => self::sensitive($b->latestVersion),
                    'fragments' => Library::blueprintVersion($b->latestVersion),
                ])->values(),
            'selected' => $request->integer('blueprint') ?: null,
            'github' => [
                'enabled' => Scaffolder::enabled(),
                'login' => $account?->login,
                'owners' => fn () => $account && Scaffolder::enabled() ? Installations::for($account) : [],
                'install_url' => Installations::installUrl(),
            ],
        ]);
    }

    /**
     * Projects pin the blueprint's current version, so later edits to the
     * blueprint never change what an existing project was made from.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'blueprint_id' => ['required', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
            'github' => ['nullable', 'array'],
            'github.enabled' => ['boolean'],
            'github.owner' => ['required_if_accepted:github.enabled', 'nullable', 'string', 'max:100'],
            'github.name' => ['required_if_accepted:github.enabled', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'not_in:.,..'],
            'github.private' => ['boolean'],
        ], [
            'github.name.regex' => 'Use letters, numbers, hyphens, underscores, and periods.',
        ]);

        $blueprint = Blueprint::with('latestVersion.base')->find($validated['blueprint_id']);

        if (! $blueprint?->isVisibleTo($request->user()) || ! $blueprint->latestVersion) {
            return back()->withErrors(['blueprint_id' => 'Choose a blueprint.']);
        }

        $slug = Str::slug($validated['name']);

        if ($request->user()->projects()->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'You already have a project with this name.']);
        }

        $github = ($validated['github']['enabled'] ?? false) ? $this->githubTarget($request, $validated['github'], $blueprint->latestVersion) : null;

        $project = $request->user()->projects()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'project_type' => $blueprint->latestVersion->base->layer['type'] ?? 'bare',
            'blueprint_version_id' => $blueprint->latestVersion->id,
            'description' => $validated['description'] ?? null,
            'scaffold_status' => $github ? 'pending' : null,
        ]);

        if ($github) {
            ScaffoldOnGitHub::dispatch($project, $github['org'], $github['name'], $github['private']);

            return to_route('projects');
        }

        // Set up locally: hand over the token now, since that is the next step
        return to_route('projects')->with([
            'token' => $project->issuePullToken(),
            'status' => 'project-token',
        ]);
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

    private function onGitHub(callable $call): RedirectResponse
    {
        try {
            $call();
        } catch (GitHubNotConnected $e) {
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
    private function githubTarget(Request $request, array $input, BlueprintVersion $version): array
    {
        $account = $request->user()->githubAccount;

        if (! Scaffolder::enabled()) {
            throw ValidationException::withMessages(['github.enabled' => 'GitHub scaffolding is not set up on this server.']);
        }
        if (! $account) {
            throw ValidationException::withMessages(['github.enabled' => 'Connect GitHub in settings first.']);
        }

        $owner = collect(Installations::for($account) ?? [])->firstWhere('login', $input['owner']);

        if (! $owner) {
            throw ValidationException::withMessages(['github.owner' => 'Install the Framework app on that account first.']);
        }

        $private = (bool) ($input['private'] ?? true);

        if (! $private && self::sensitive($version)) {
            throw ValidationException::withMessages(['github.private' => 'This blueprint handles restricted data, so its repository must be private.']);
        }

        return [
            'org' => $owner['type'] === 'Organization' ? $owner['login'] : null,
            'name' => $input['name'],
            'private' => $private,
        ];
    }

    private static function sensitive(BlueprintVersion $version): bool
    {
        return ProjectLayers::isSensitive([
            ...$version->fragments()->map(fn ($f) => ['layer' => $f->layer])->all(),
            ['layer' => $version->layer ?? []],
        ]);
    }

    public function issueToken(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);

        return back()->with([
            'token' => $project->issuePullToken(),
            'status' => 'project-token',
        ]);
    }

    public function destroy(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);
        $project->tokens()->delete();
        $project->delete();

        return back();
    }
}
