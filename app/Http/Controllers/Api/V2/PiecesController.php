<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Facet;
use App\Models\Skill;
use App\Models\User;
use App\Support\ProjectDefaults;
use App\Support\Resolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        return response()->json(ProjectDefaults::for($this->user($request)));
    }

    /** GET /api/v2/skills: single skills, for --skill. */
    public function skills(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['skills' => Skill::visibleTo($user)->with('owner', 'latestVersion')
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
            ->map(fn (Skill $s) => [
                'key' => $s->handle,
                'name' => $s->name,
                'description' => $s->description,
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

        return response()->json((new Resolver($user))->resolve($this->choices($request))['envelope']);
    }

    /**
     * POST /api/v2/projects: make a cloud project from the same choices, and
     * hand back a project token so fw can link the local copy.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $choices = $this->choices($request, nameRequired: true);
        $resolver = new Resolver($user);
        $project = $resolver->createProject($choices['name'], $resolver->resolve($choices), $choices['description'] ?? null);

        return response()->json([
            'slug' => $project->slug,
            'name' => $project->name,
            'language' => $project->language,
            'url' => route('projects.show', $project->slug),
            'token' => $project->issuePullToken('fw'),
        ], 201);
    }

    private function choices(Request $request, bool $nameRequired = false): array
    {
        return $request->validate([
            'name' => [$nameRequired ? 'required' : 'nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'language' => ['nullable', 'string'],
            'blueprint' => ['nullable', 'string', 'max:200'],
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
