<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\Layer;
use App\Support\ProjectLayers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pull v2: a project's layers envelope (resources/schema/layers.v1.json),
 * the same document the cloud commits as .framework/layers.json. Tools
 * merge the layers themselves.
 */
class ProjectsController extends Controller
{
    /** GET /api/v2/projects/pull, with a project token. */
    public function pull(Request $request): JsonResponse
    {
        $project = $request->user();

        abort_unless($project instanceof Project, 403, 'This endpoint requires a project token.');
        abort_unless($project->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks the project:pull ability.');

        return $this->envelope($project);
    }

    /**
     * PUT /api/v2/project/spec, with the project's token: fw sends the
     * project's .framework/spec.json. Only the latest is kept.
     */
    public function updateSpec(Request $request): JsonResponse
    {
        $project = $request->user();

        abort_unless($project instanceof Project, 403, 'This endpoint requires a project token.');
        abort_unless($project->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks the project:pull ability.');
        abort_if(strlen($request->getContent()) > 2 * 1024 * 1024, 413, 'The spec is larger than 2 MB.');

        $body = json_decode($request->getContent(), false);
        $spec = is_object($body) ? ($body->spec ?? null) : null;

        if (! is_object($spec)) {
            return response()->json(['message' => 'Send the spec as {"spec": {...}}.', 'errors' => ['spec' => ['The spec must be a JSON object.']]], 422);
        }

        if ($problems = Layer::specProblems($spec)) {
            return response()->json(['message' => 'The spec is not valid.', 'errors' => ['spec' => $problems]], 422);
        }

        $json = json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $changed = $project->spec === null || json_decode($project->spec, true) != json_decode($json, true);

        $project->forceFill(['spec' => $json, 'spec_received_at' => now()])->save();

        return response()->json([
            'received_at' => $project->spec_received_at->toIso8601String(),
            'spec_version' => $spec->spec_version,
            'changed' => $changed,
        ]);
    }

    /** GET /api/v2/projects/{slug}, with a user token: what fw project show prints. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug)->loadMissing('blueprintVersion.blueprint.owner');
        $version = $project->blueprintVersion;

        return response()->json([
            'slug' => $project->slug,
            'name' => $project->name,
            'description' => $project->description,
            'language' => $project->language ?? 'r',
            'type' => $project->project_type,
            'blueprint' => $version ? ['key' => $version->blueprint->handle, 'version' => $version->version] : null,
            'github' => $project->github_repo ? [
                'repository' => $project->github_repo,
                'url' => "https://github.com/{$project->github_repo}",
                'private' => (bool) $project->github_private,
                'scaffold_status' => $project->scaffold_status,
                // Set while GitHub Pages publishes the repository's published/ folder
                'pages_url' => $project->pages_url,
            ] : null,
            'spec_received_at' => $project->spec_received_at?->toIso8601String(),
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ]);
    }

    /** GET /api/v2/projects/{slug}/layers, with a user token. */
    public function layers(Request $request, string $slug): JsonResponse
    {
        return $this->envelope($this->project($request, $slug));
    }

    /**
     * POST /api/v2/projects/{slug}/pull-tokens, with a user token. Each call
     * issues a new project token for a local checkout's .env. Reading the
     * layers never issues one.
     */
    public function issuePullToken(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'token' => $project->issuePullToken($validated['name'] ?? 'pull'),
            'url' => rtrim((string) config('app.url'), '/'),
        ], 201);
    }

    private function envelope(Project $project): JsonResponse
    {
        return response()->json(Layer::toJsonValue(ProjectLayers::for($project)));
    }

    private function project(Request $request, string $slug): Project
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403, 'This endpoint requires a user token.');

        return $user->projects()->where('slug', $slug)->first()
            ?? abort(404, "You have no project named '{$slug}'.");
    }
}
