<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ScaffoldOnGitHub;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\GitHub;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\ProjectFiles;
use App\Services\GitHub\RepositoryTarget;
use App\Services\GitHub\Scaffolder;
use App\Support\ProjectLayers;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A project's GitHub repository through the API (and so the MCP): scaffold
 * it or retry a failed scaffold, link an existing repository, and read and
 * write its files.
 */
class ProjectRepositoryController extends Controller
{
    /** Inline writes: the JSON body's limit. Larger files go through an upload link. */
    public const MAX_INLINE_BYTES = 8 * 1024 * 1024;

    public const MAX_UPLOAD_BYTES = 50 * 1024 * 1024;

    /** GET /projects/{slug}/scaffold: the scaffold's state, asking GitHub when a run is under way. */
    public function scaffoldStatus(Request $request, string $slug, Scaffolder $scaffolder): JsonResponse
    {
        $project = $this->project($request, $slug);

        if ($project->scaffold_status === 'running' && $project->github_repo) {
            try {
                $scaffolder->refresh($project);
            } catch (RequestException|GitHubNotConnected) {
                // Report what is recorded
            }
        }

        return response()->json(self::scaffoldState($project->fresh()));
    }

    /**
     * POST /projects/{slug}/scaffold. With a repository whose scaffold failed:
     * run the workflow again. Without a repository: create one with
     * {github: {owner, name, private}} and scaffold it.
     */
    public function scaffold(Request $request, string $slug, Scaffolder $scaffolder): JsonResponse
    {
        $project = $this->project($request, $slug);

        if ($project->github_repo) {
            if ($project->scaffold_status !== 'failed') {
                throw ValidationException::withMessages(['project' => match ($project->scaffold_status) {
                    'done' => 'This project is already scaffolded on GitHub.',
                    null => 'This project is linked to an existing repository; there is nothing to scaffold.',
                    default => 'The scaffold is still running. Check it with projects_show.',
                }]);
            }

            try {
                if (str_starts_with((string) $project->scaffold_error, 'The scaffold workflow')) {
                    $scaffolder->retry($project);
                } else {
                    // It failed before the workflow ran: commit the layers and workflow again
                    $this->rescaffold($project);
                }
            } catch (RequestException $e) {
                throw ValidationException::withMessages(['github' => 'GitHub returned an error: '.($e->response->json('message') ?? $e->response->status())]);
            } catch (GitHubNotConnected $e) {
                throw ValidationException::withMessages(['github' => $e->getMessage()]);
            }

            return response()->json(self::scaffoldState($project->fresh()), 202);
        }

        $github = $request->validate([
            'github' => ['required', 'array'],
            'github.owner' => ['required', 'string', 'max:100'],
            'github.name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'not_in:.,..'],
            'github.private' => ['boolean'],
        ], ['github.required' => 'This project has no repository yet. Pass github with the owner (and name, private) to create one.'])['github'];

        if ($reason = Scaffolder::unavailableFor($project->language ?? 'r')) {
            throw ValidationException::withMessages(['github' => $reason]);
        }
        $github['name'] ??= $project->slug;
        $target = RepositoryTarget::for($project->user, $github, ProjectLayers::isSensitive(ProjectLayers::for($project)['layers'] ?? []));

        $project->update(['scaffold_status' => 'pending', 'scaffold_error' => null, 'scaffold_run_url' => null]);
        ScaffoldOnGitHub::dispatch($project, $target['org'], $target['name'], $target['private']);

        return response()->json([
            'repository' => ($target['org'] ?? $github['owner']).'/'.$target['name'],
        ] + self::scaffoldState($project->fresh()), 202);
    }

    /** POST /projects/{slug}/github: link the project to a repository that already exists. */
    public function link(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);
        $data = $request->validate([
            'repository' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9-]+\/[A-Za-z0-9._-]+$/'],
        ], ['repository.regex' => 'Name the repository as owner/name.']);

        if ($project->github_repo && strcasecmp($project->github_repo, $data['repository']) !== 0) {
            throw ValidationException::withMessages(['repository' => "This project is already linked to {$project->github_repo}."]);
        }
        if (in_array($project->scaffold_status, ['running'], true)) {
            throw ValidationException::withMessages(['repository' => 'A scaffold is running for this project.']);
        }

        $account = $project->user->githubAccount ?? throw ValidationException::withMessages(['github' => 'Connect GitHub in settings first.']);

        try {
            $repo = GitHub::for($account)->repository($data['repository']);
        } catch (RequestException $e) {
            throw ValidationException::withMessages(['repository' => $e->response->status() === 404
                ? 'GitHub has no such repository, or the Framework app cannot see it. Install the app on its owner with access to it.'
                : 'GitHub returned an error: '.($e->response->json('message') ?? $e->response->status())]);
        }

        if (! ($repo['permissions']['push'] ?? false)) {
            throw ValidationException::withMessages(['repository' => 'You cannot push to that repository through the Framework app.']);
        }

        $taken = Project::where('github_repo_id', $repo['id'])->where('id', '!=', $project->id)->first();
        if ($taken) {
            throw ValidationException::withMessages(['repository' => "Another project ({$taken->slug}) is linked to that repository."]);
        }

        $project->update([
            'github_repo' => $repo['full_name'],
            'github_repo_id' => $repo['id'],
            'github_private' => $repo['private'],
            'scaffold_status' => null,
            'scaffold_error' => null,
        ]);

        return response()->json([
            'slug' => $project->slug,
            'repository' => $repo['full_name'],
            'url' => $repo['html_url'],
            'private' => $repo['private'],
        ]);
    }

    /** GET /projects/{slug}/files: every file in the repository's default branch. */
    public function files(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);
        abort_unless((bool) $project->github_repo, 422, 'This project has no GitHub repository.');

        try {
            $tree = $this->github($project)->tree($project->github_repo);
        } catch (RequestException $e) {
            abort(502, 'GitHub returned an error: '.($e->response->json('message') ?? $e->response->status()));
        }

        return response()->json(['repository' => $project->github_repo] + $tree);
    }

    /** POST /projects/{slug}/files: write (and delete) files in one commit. */
    public function write(Request $request, string $slug, ProjectFiles $writer): JsonResponse
    {
        $project = $this->project($request, $slug);
        abort_if(strlen($request->getContent()) > self::MAX_INLINE_BYTES, 413, 'Send files larger than a few MB with projects_files_upload.');

        $data = $request->validate([
            'files' => ['array'],
            'files.*.path' => ['required', 'string', 'max:400'],
            'files.*.content' => ['present', 'nullable', 'string'],
            'files.*.encoding' => ['nullable', 'in:utf-8,base64'],
            'delete' => ['array'],
            'delete.*' => ['string', 'max:400'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $files = [];
        foreach ($data['files'] ?? [] as $i => $file) {
            $bytes = (string) ($file['content'] ?? '');
            if (($file['encoding'] ?? 'utf-8') === 'base64') {
                $bytes = base64_decode($bytes, true);
                if ($bytes === false) {
                    throw ValidationException::withMessages(["files.{$i}.content" => 'The content is not valid base64.']);
                }
            }
            $files[$file['path']] = $bytes;
        }

        return response()->json($this->onGitHub(fn () => $writer->commit($project, $files, $data['delete'] ?? [], $data['message'] ?? null)), 201);
    }

    /**
     * POST /projects/{slug}/files/uploads: a one-time link that commits the
     * raw file sent to it, to path. With unzip, a zip's files go under path
     * (the repository root when path is empty). Good for 30 minutes.
     */
    public function createUpload(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);
        ProjectFiles::repository($project);
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:400'],
            'unzip' => ['boolean'],
            'strip_top_folder' => ['boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $unzip = (bool) ($data['unzip'] ?? false);

        if (! $unzip && blank($data['path'] ?? null)) {
            throw ValidationException::withMessages(['path' => 'Pass the file\'s path in the repository, such as data/survey.csv, or unzip a zip.']);
        }
        if (filled($data['path'] ?? null)) {
            ProjectFiles::path($data['path']);
        }

        $token = Str::random(48);
        Cache::put('project-upload:'.hash('sha256', $token), [
            'project_id' => $project->id,
            'path' => trim((string) ($data['path'] ?? ''), '/'),
            'unzip' => $unzip,
            'strip_top_folder' => (bool) ($data['strip_top_folder'] ?? false),
            'message' => $data['message'] ?? null,
        ], now()->addMinutes(30));
        $url = route('api.project-uploads.store', ['token' => $token]);
        $file = $unzip ? 'project.zip' : basename((string) $data['path']);

        return response()->json([
            'upload_url' => $url,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'max_bytes' => self::MAX_UPLOAD_BYTES,
            'command' => "curl -sS --fail-with-body -X PUT -H 'Content-Type: application/octet-stream' --data-binary @".escapeshellarg($file)." '".$url."'",
            'note' => 'Replace the path after @ with the local file. The link works once and returns the commit. '
                .'If a sandbox blocks '.parse_url($url, PHP_URL_HOST).', tell the user to allow that host, or give them the command to run.',
        ], 201);
    }

    /** PUT /project-uploads/{token}: the raw file for a project upload link. The token is the permission. */
    public function receiveUpload(Request $request, string $token, ProjectFiles $writer): JsonResponse
    {
        $key = 'project-upload:'.hash('sha256', $token);
        $upload = Cache::pull($key);
        abort_if($upload === null, 404, 'This upload link has expired or was already used. Ask for a new one.');

        $project = Project::with('user.githubAccount')->find($upload['project_id']);
        abort_if($project === null, 404, 'The project no longer exists.');

        $bytes = $request->getContent();
        try {
            if ($bytes === '') {
                throw ValidationException::withMessages(['file' => 'Send the file as the request body.']);
            }
            if (strlen($bytes) > self::MAX_UPLOAD_BYTES) {
                throw ValidationException::withMessages(['file' => 'Files over 50 MB are too large. Split a large zip into several uploads.']);
            }

            if ($upload['unzip']) {
                $tmp = tempnam(sys_get_temp_dir(), 'fw-upload');
                file_put_contents($tmp, $bytes);
                try {
                    $files = ProjectFiles::unzip($tmp, $upload['path'], $upload['strip_top_folder']);
                } finally {
                    @unlink($tmp);
                }
            } else {
                $files = [$upload['path'] => $bytes];
            }

            return response()->json($this->onGitHub(fn () => $writer->commit($project, $files, [], $upload['message'])), 201);
        } catch (\Throwable $e) {
            // A failed upload leaves the link usable until it expires, so the file can be fixed and sent again
            Cache::put($key, $upload, now()->addMinutes(30));
            throw $e;
        }
    }

    /** @return array{scaffold_status: ?string, scaffold_error: ?string, scaffold_run_url: ?string, repository: ?string} */
    public static function scaffoldState(Project $project): array
    {
        return [
            'scaffold_status' => $project->scaffold_status,
            'scaffold_error' => $project->scaffold_error,
            'scaffold_run_url' => $project->scaffold_run_url,
            'repository' => $project->github_repo,
        ];
    }

    /** Commit the layers and the workflow again to a repository whose first scaffold never started. */
    private function rescaffold(Project $project): void
    {
        $github = $this->github($project);
        $envelope = ProjectLayers::for($project);

        $project->update(['scaffold_status' => 'running', 'scaffold_error' => null]);
        $github->putFile($project->github_repo, Scaffolder::LAYERS_PATH, ProjectLayers::toJson($envelope), 'Add Framework project layers');
        $github->putFile($project->github_repo, Scaffolder::WORKFLOW_PATH, Scaffolder::workflow($project->language ?? 'r'), 'Add Framework scaffold workflow');
    }

    private function github(Project $project): GitHub
    {
        return GitHub::for($project->user->githubAccount ?? throw ValidationException::withMessages(['github' => 'Connect GitHub in settings first.']));
    }

    private function onGitHub(callable $call): mixed
    {
        try {
            return $call();
        } catch (GitHubNotConnected $e) {
            throw ValidationException::withMessages(['github' => $e->getMessage()]);
        }
    }

    private function project(Request $request, string $slug): Project
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'This endpoint requires a user token.');

        return $user->projects()->where('slug', $slug)->first()
            ?? abort(404, "You have no project named '{$slug}'.");
    }
}
