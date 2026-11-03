<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Jobs\ScaffoldOnGitHub;
use App\Models\Facet;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use App\Services\GitHub\Installations;
use App\Services\GitHub\RepositoryTarget;
use App\Services\GitHub\Scaffolder;
use App\Services\Scaffolder\ProjectZip;
use App\Support\Layer;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
use App\Support\Resolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The pieces a project is made from, for fw, the R and Python packages, and
 * agents: project structures, tools, package sets, skill sets, and skills.
 * Each list holds Framework's pieces, the user's own, and shared ones.
 */
class PiecesController extends Controller
{
    /** GET /api/v2/structures, /tools, /package-sets, or /skill-sets. ?language= filters. */
    public function index(Request $request, string $kind): JsonResponse
    {
        $user = $this->user($request);
        $language = $request->query('language');

        $pieces = Facet::visibleTo($user)->where('kind', $kind)->with('owner', 'latestVersion')
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
            ->filter(fn (Facet $f) => ! $language || ! $f->language || in_array($language, $f->language, true))
            ->map(fn (Facet $f) => $this->piece($f))
            ->values();

        return response()->json([str($kind)->plural()->value() => $pieces]);
    }

    /** GET /api/v2/defaults: what the user's new projects start with. */
    public function defaults(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(ProjectDefaults::for($user))->header('Framework-Defaults-Revision', ProjectDefaults::revision($user));
    }

    /** GET /api/v2/skills: single skills, for --skill. */
    public function skills(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['skills' => Skill::visibleTo($user)->with('owner', 'latestVersion')
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
            ->map(fn (Skill $s) => [
                'key' => $s->handle,
                'skill_id' => $s->sync_id,
                'name' => $s->name,
                'description' => $s->description,
                // The languages it's for (none means any), as the resolver applies them
                'language' => $s->language,
                'version' => $s->latestVersion?->version,
                'system' => $s->isSystem(),
                'visibility' => $s->visibility,
            ])->values()]);
    }

    /**
     * POST /api/v2/resolve: the layers envelope for a set of choices, without
     * creating a project. fw new hands it to R or Python, or scaffolds Stata.
     */
    public function resolve(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $resolved = (new Resolver($user))->resolve($this->choices($request));

        // Clients cache the default envelope by this revision and print the notices
        return response()->json(Layer::toJsonValue($resolved['envelope']))
            ->header('Framework-Defaults-Revision', ProjectDefaults::revision($user))
            ->withHeaders($resolved['notices'] ? ['Framework-Notice' => $resolved['notices']] : []);
    }

    /**
     * POST /api/v2/projects: make a cloud project from the same choices, and
     * hand back a project token so fw can link the local copy.
     */
    /**
     * POST /api/v2/projects: create a project from choices. With github
     * ({owner, name, private}), the project is also scaffolded in a new
     * repository; GET /api/v2/projects/{slug} reports how that went.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $choices = $this->choices($request, nameRequired: true);
        $github = $request->validate([
            'github' => ['nullable', 'array'],
            'github.owner' => ['required_with:github', 'string', 'max:100'],
            'github.name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'not_in:.,..'],
            'github.private' => ['boolean'],
        ], ['github.name.regex' => 'Use letters, numbers, hyphens, underscores, and periods.'])['github'] ?? null;

        $resolver = new Resolver($user);
        $resolved = $resolver->resolve($choices);

        $target = null;
        if ($github) {
            if ($reason = Scaffolder::unavailableFor($resolved['language'])) {
                throw ValidationException::withMessages(['github' => $reason]);
            }
            $github['name'] ??= str($choices['name'])->slug()->value();
            $target = RepositoryTarget::for($user, $github, ProjectLayers::isSensitive($resolved['envelope']['layers']));
        }

        $project = $resolver->createProject($choices['name'], $resolved, $choices['description'] ?? null, [
            'scaffold_status' => $target ? 'pending' : null,
        ]);

        if ($target) {
            ScaffoldOnGitHub::dispatch($project, $target['org'], $target['name'], $target['private']);
        }

        return response()->json([
            'slug' => $project->slug,
            'name' => $project->name,
            'language' => $project->language,
            'url' => route('projects.show', $project->slug),
            'token' => $project->issuePullToken('fw'),
            'github' => $target ? [
                'repository' => ($target['org'] ?? $github['owner']).'/'.$target['name'],
                'scaffold_status' => 'pending',
            ] : null,
        ], 201);
    }

    /**
     * GET /api/v2/destinations: the ways this server can hand over a new
     * project besides building it on the person's computer, so fw can ask.
     */
    public function destinations(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $account = $user->githubAccount;
        $owners = $account && Scaffolder::enabled() ? Installations::for($account) : [];

        return response()->json([
            'download' => [
                'available' => ProjectZip::enabled(),
            ],
            'github' => [
                'available' => Scaffolder::enabled(),
                'login' => $account?->login,
                // null when GitHub could not be reached
                'owners' => $owners === null ? null : collect($owners)->map(fn (array $o) => ['login' => $o['login'], 'type' => $o['type']])->values()->all(),
                'install_url' => Installations::installUrl(),
                'connect_url' => url('/account/github'),
                'unavailable' => (object) collect(Project::LANGUAGES)->mapWithKeys(fn (string $l) => [$l => Scaffolder::unavailableFor($l)])->filter()->all(),
            ],
        ]);
    }

    private function choices(Request $request, bool $nameRequired = false): array
    {
        return $request->validate([
            'name' => [$nameRequired ? 'required' : 'nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'language' => ['nullable', 'string'],
            'blueprint' => ['nullable', 'string', 'max:200'],
            'structure' => ['nullable', 'string', 'max:200'],
            'workflow' => ['nullable', 'in:'.implode(',', ProjectDefaults::WORKFLOW_KEYS)],
            'notebook_format' => ['nullable', 'in:rmarkdown'],
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
            'settings.ai' => ['nullable'],
        ]);
    }

    private function piece(Facet $facet): array
    {
        $layer = $facet->latestVersion?->layer ?? [];

        return array_filter([
            'key' => $facet->handle,
            'name' => $facet->name,
            'description' => $facet->description,
            'language' => $facet->language,
            'requires' => $facet->requires ?: null,
            'type' => $facet->kind === 'structure' ? ($layer['type'] ?? 'bare') : null,
            'packages' => in_array($facet->kind, ['package_set', 'skill_set'], true) ? array_keys($layer['packages']['default_packages'] ?? []) : null,
            'skills' => $facet->kind === 'skill_set' ? array_keys($layer['skills'] ?? []) : null,
            'version' => $facet->latestVersion?->version,
            'system' => $facet->isSystem(),
            'visibility' => $facet->visibility,
        ], fn ($v) => $v !== null);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'This endpoint requires a user token.');

        return $user;
    }
}
