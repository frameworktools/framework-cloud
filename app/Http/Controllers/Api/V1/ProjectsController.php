<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectsController extends Controller
{
    /**
     * The authenticated user's projects.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'projects' => $user->projects()->latest()->get(['name', 'slug', 'project_type', 'description', 'created_at']),
        ]);
    }

    /**
     * Register a project from the R package (framework::new() with a cloud
     * token). Returns a pull token so the project key can land in the
     * project's .env at creation time.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'project_type' => ['required', 'exists:blueprints,key'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = Str::slug($validated['name']);

        if ($user->projects()->where('slug', $slug)->exists()) {
            return response()->json([
                'message' => "You already have a project named '{$slug}' on framework.pub.",
            ], 422);
        }

        $project = $user->projects()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'project_type' => $validated['project_type'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'project' => $project->only(['name', 'slug', 'project_type']),
            'pull_token' => $project->issuePullToken('created-by-r'),
        ], 201);
    }

    private function user(Request $request): User
    {
        $tokenable = $request->user();

        abort_unless($tokenable instanceof User, 403, 'This endpoint requires a user token.');

        return $tokenable;
    }
}
