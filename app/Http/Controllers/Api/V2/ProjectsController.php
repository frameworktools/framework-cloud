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
