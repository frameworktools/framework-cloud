<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\Scaffolder;
use App\Services\Scaffolder\ProjectZip;
use App\Services\Scaffolder\ProjectZipFailed;
use App\Support\Layer;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
use App\Support\Share;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The layers pull: a project's layers envelope (resources/schema/layers.v1.json),
 * the same document the cloud commits as .framework/layers.json. Tools
 * merge the layers themselves.
 */
class ProjectLayersController extends Controller
{
    /**
     * GET /api/v1/projects/pull, with a project token. The owner's projects
     * folder rides in the Framework-Projects-Dir header, percent-encoded,
     * not the body: layers.v1 allows no extra keys, and the body is
     * committed to the project as .framework/layers.json.
     */
    public function pull(Request $request): JsonResponse
    {
        $project = $request->user();

        abort_unless($project instanceof Project, 403, 'This endpoint requires a project token.');
        abort_unless($project->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks the project:pull ability.');

        $response = $this->envelope($project);
        $dir = $project->user ? ProjectDefaults::projectsDir($project->user) : null;

        return $dir === null ? $response : $response->header(ProjectDefaults::PROJECTS_DIR_HEADER, ProjectDefaults::headerValue($dir));
    }

    /**
     * PUT /api/v1/project/spec, with the project's token: fw sends the
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

    /** GET /api/v1/projects/{slug}, with a user token: what fw project show prints. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug)->loadMissing('blueprintVersion.blueprint.owner');

        // A run under way: ask GitHub, in case its webhook was missed
        if ($project->scaffold_status === 'running' && $project->github_repo && $project->updated_at?->lt(now()->subMinute())) {
            try {
                app(Scaffolder::class)->refresh($project);
            } catch (RequestException|GitHubNotConnected) {
                // Report what is recorded
            }
        }
        $version = $project->blueprintVersion;

        return response()->json([
            'slug' => $project->slug,
            'name' => $project->name,
            'description' => $project->description,
            'language' => $project->language ?? 'r',
            'type' => $project->project_type,
            'status' => $project->status ?? 'active',
            'active' => $project->active,
            'public_download_url' => $project->publicDownloadUrl(),
            // Short link and QR code (SVG, PNG) for slides; null while private
            'share' => Share::for($project),
            'adopted' => $project->adopted_at !== null,
            'blueprint' => $version ? ['key' => $version->blueprint->handle, 'version' => $version->version] : null,
            'github' => $project->github_repo ? [
                'repository' => $project->github_repo,
                'url' => "https://github.com/{$project->github_repo}",
                'private' => (bool) $project->github_private,
                'scaffold_status' => $project->scaffold_status,
                'scaffold_error' => $project->scaffold_error,
                'scaffold_run_url' => $project->scaffold_run_url,
                // Set while GitHub Pages publishes the repository's published/ folder
                'pages_url' => $project->pages_url,
            ] : null,
            // Also set while a repository is being created, before github is
            'scaffold' => ProjectRepositoryController::scaffoldState($project),
            'spec_received_at' => $project->spec_received_at?->toIso8601String(),
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/projects/{slug}/download, with a user token: the project
     * built by the scaffolder, as a zip with the project under {slug}/.
     */
    public function download(Request $request, string $slug, ProjectZip $zips): JsonResponse|BinaryFileResponse
    {
        $project = $this->project($request, $slug);

        if (! ProjectZip::enabled()) {
            return response()->json(['message' => 'This server is not set up to build downloads.'], 404);
        }

        try {
            $path = $zips->build($project);
        } catch (ProjectZipFailed $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->download($path, "{$project->slug}.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** GET /api/v1/projects/{slug}/layers, with a user token. */
    public function layers(Request $request, string $slug): JsonResponse
    {
        return $this->envelope($this->project($request, $slug));
    }

    /**
     * POST /api/v1/projects/{slug}/pull-tokens, with a user token. Each call
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
