<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectsController extends Controller
{
    /**
     * The authenticated user's projects.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'projects' => $user->projects()->listed()->latest()->get(['name', 'slug', 'project_type', 'language', 'description', 'active', 'created_at']),
        ]);
    }

    /**
     * Register a project. Resolved choices (blueprint, structure, tools, and so on) build it from
     * layers; the older name-and-type body, which older packages send, registers it by type.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->has('project_type')) {
            return app(PiecesController::class)->store($request);
        }

        return $this->register($request);
    }

    /**
     * DELETE /projects/{slug}: move a project to the Trash. Nothing is removed: its artifacts,
     * reports, collections, and tokens stop working while it is there, and work again when it
     * is restored. (Sanctum finds no project behind a token while the project is deleted.)
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $project = $this->user($request)->projects()->listed()->where('slug', $slug)->firstOrFail();
        $project->delete();

        return response()->json(['deleted' => $project->slug, 'restore' => route('trash')]);
    }

    /** PATCH /projects/{slug}: rename a project or change its description. Its slug, and so its links, stay. */
    public function update(Request $request, string $slug): JsonResponse
    {
        $project = $this->user($request)->projects()->listed()->where('slug', $slug)->firstOrFail();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
            'public_download' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('public_download', $validated)) {
            $project->setPublicDownload((bool) $validated['public_download']);
            unset($validated['public_download']);
        }
        $project->update($validated);

        return response()->json($project->only('slug', 'name', 'description', 'active') + [
            'url' => route('projects.show', $project->slug),
            'public_download_url' => $project->publicDownloadUrl(),
        ]);
    }

    /** framework::new() and framework.new() register by name and type, and get a pull token for .env. */
    private function register(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'project_type' => ['required', Rule::in(Project::TYPES)],
            'language' => ['nullable', Rule::in(Project::LANGUAGES)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($problem = Project::typeProblem($validated['language'] ?? 'r', $validated['project_type'])) {
            return response()->json(['message' => $problem], 422);
        }

        $slug = Str::slug($validated['name']);

        // A project in the Trash keeps its name, so it can be restored
        if ($user->projects()->withTrashed()->where('slug', $slug)->exists()) {
            return response()->json([
                'message' => "You already have a project named '{$slug}' on Framework Cloud.",
            ], 422);
        }

        $project = $user->projects()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'project_type' => $validated['project_type'],
            'language' => $validated['language'] ?? 'r',
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'project' => $project->only(['name', 'slug', 'project_type', 'language']),
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
