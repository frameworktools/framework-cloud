<?php

namespace App\Http\Controllers\Api\V1;

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
    /** GET /api/v1/structures, /tools, /package-sets, or /skill-sets. ?language= filters. */
    public function index(Request $request, string $kind): JsonResponse
    {
        $user = $this->user($request);
        $language = $request->query('language');

        $pieces = Facet::visibleTo($user)->where('kind', $kind)->with('owner', 'latestVersion')
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
            ->filter(fn (Facet $f) => ! $language || ! $f->language || in_array($language, $f->language, true))
            ->map(fn (Facet $f) => $this->piece($f, is_string($language) ? $language : null))
            ->values();

        return response()->json([str($kind)->plural()->value() => $pieces]);
    }

    /**
     * GET /api/v1/defaults: what the user's new projects start with, read
     * from their default blueprints, in the shape older packages expect.
     */
    public function defaults(Request $request): JsonResponse
    {
        $user = $this->user($request);

        // projects_dir is personal, not from a blueprint, so it stays out of the revision
        return response()->json([...ProjectDefaults::for($user), 'projects_dir' => ProjectDefaults::projectsDir($user)])->header('Framework-Defaults-Revision', ProjectDefaults::revision($user));
    }

    /** GET /api/v1/skills: single skills, for --skill. */
    public function skills(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $language = $request->query('language');

        return response()->json(['skills' => Skill::visibleTo($user)->with('owner', 'latestVersion')
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
            // ?language= keeps skills for that language and skills for any language
            ->filter(fn (Skill $s) => ! is_string($language) || $language === '' || $s->fits($language))
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
     * POST /api/v1/resolve: the layers envelope for a set of choices, without
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
     * POST /api/v1/projects: make a cloud project from the same choices, and
     * hand back a project token so fw can link the local copy.
     */
    /**
     * POST /api/v1/projects: create a project from choices. With github
     * ({owner, name, private}), the project is also scaffolded in a new
     * repository; GET /api/v1/projects/{slug} reports how that went.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $extra = $request->validate([
            // fw project link --create: the folder's own slug, and that it already existed
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'adopted' => ['boolean'],
        ], ['slug.regex' => 'Use lowercase letters, numbers, and single hyphens.']);
        $attributes = array_filter([
            'slug' => $extra['slug'] ?? null,
            'adopted_at' => ($extra['adopted'] ?? false) ? now() : null,
        ]);

        if ($request->has('spec')) {
            return $this->storeFromSpec($request, $user, $attributes);
        }

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
        ] + $attributes);

        if ($target) {
            ScaffoldOnGitHub::dispatch($project, $target['org'], $target['name'], $target['private']);
        }

        return response()->json([
            'slug' => $project->slug,
            'name' => $project->name,
            'language' => $project->language,
            'url' => route('projects.show', $project->slug),
            'adopted' => $project->adopted_at !== null,
            'token' => $project->issuePullToken('fw'),
            'github' => $target ? [
                'repository' => ($target['org'] ?? $github['owner']).'/'.$target['name'],
                'scaffold_status' => 'pending',
            ] : null,
        ], 201);
    }

    /**
     * POST /api/v1/projects with {"spec": {...}}: register a project that
     * already exists on someone's computer (fw project link --create). The
     * project's one layer is its own resolved spec, so the cloud matches the
     * folder exactly; the person's defaults are not merged in.
     */
    private function storeFromSpec(Request $request, User $user, array $attributes): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 2 * 1024 * 1024, 413, 'The spec is larger than 2 MB.');
        // Decoded as objects, so empty maps in the spec stay maps
        $spec = json_decode($request->getContent(), false)->spec ?? null;
        if (! is_object($spec)) {
            throw ValidationException::withMessages(['spec' => 'The spec must be a JSON object.']);
        }
        if ($problems = Layer::specProblems($spec)) {
            throw ValidationException::withMessages(['spec' => $problems]);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $name = $data['name'] ?? (is_string($spec->name ?? null) ? $spec->name : null);
        if (! is_string($name) || trim($name) === '') {
            throw ValidationException::withMessages(['name' => 'Give the project a name, or put one in the spec.']);
        }

        $language = is_string($spec->language ?? null) ? $spec->language : 'r';
        if (! in_array($language, Project::LANGUAGES, true)) {
            throw ValidationException::withMessages(['spec' => "The spec's language must be one of: ".implode(', ', Project::LANGUAGES).'.']);
        }
        // The spec's type is a label; one Framework doesn't know shows as bare
        $type = is_string($spec->type ?? null) && in_array($spec->type, Project::TYPES, true) ? $spec->type : 'bare';

        $resolver = new Resolver($user);
        $slug = $resolver->availableSlug($name, $attributes['slug'] ?? null);
        unset($attributes['slug']);

        $layer = json_decode(json_encode($spec), true);
        unset($layer['spec_version'], $layer['name']);
        $envelope = ['name' => $name, 'spec_version' => 1, 'layers' => [['kind' => 'project', 'key' => $slug, 'layer' => $layer]], 'source' => ['origin' => 'cli']];

        $project = $user->projects()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'language' => $language,
            'project_type' => $type,
            // Pulls and the project page read the layers; the spec is what fw last sent
            'resolved' => Layer::envelopeProblems($envelope) === [] ? json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'spec' => json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'spec_received_at' => now(),
        ] + $attributes);

        return response()->json([
            'slug' => $project->slug,
            'name' => $project->name,
            'language' => $project->language,
            'type' => $project->project_type,
            'adopted' => $project->adopted_at !== null,
            'url' => route('projects.show', $project->slug),
            'token' => $project->issuePullToken('fw'),
            'github' => null,
        ], 201);
    }

    /**
     * GET /api/v1/destinations: the ways this server can hand over a new
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
            'package_overrides' => ['nullable', 'array'],
            'package_overrides.*' => ['boolean'],
            'package_auto_attach' => ['nullable', 'array'],
            'package_auto_attach.*' => ['boolean'],
            'skill_sets' => ['nullable', 'array'],
            'skill_sets.*' => ['string', 'max:200'],
            'excluded_skills' => ['nullable', 'array'],
            'excluded_skills.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:200'],
            'settings' => ['nullable', 'array'],
            'settings.sensitive' => ['boolean'],
            'settings.git' => ['boolean'],
            'settings.hooks' => ['nullable', 'array'],
            'settings.hooks.*' => ['in:'.implode(',', ProjectDefaults::HOOKS)],
            'settings.ai' => ['nullable'],
        ]);
    }

    private function piece(Facet $facet, ?string $language = null): array
    {
        $layer = $facet->latestVersion?->catalogLayer($language) ?? [];

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
