<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArtifactUpload;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\McpConnection;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Published artifacts over the API. A user token works across the account; a project token works
 * on its own project.
 *
 * An artifact belongs to one project, or to none: those published without a project go to the
 * account's automatic home (User::artifactHome()). Any artifact can also be linked to other
 * projects, which list it and may use it in reports. "project" names a project by its slug;
 * leave it out for artifacts with no project.
 *
 * Showing one artifact includes the newest version's content when it is text (a model's JSON, a
 * CSV table, Markdown), so an agent can write about the published results.
 *
 * Publishing takes the file in the JSON body, as text or base64, up to 10 MB. For larger files,
 * or files an agent has on disk, an upload link takes the raw file from curl instead.
 */
class ArtifactsController extends Controller
{
    /** Largest content returned inline, in bytes. */
    public const MAX_CONTENT_BYTES = 200_000;

    /** Largest file published from a JSON body, before base64. */
    public const MAX_PUBLISH_BYTES = 10 * 1024 * 1024;

    /** Largest file sent to an upload link, the same as any upload. */
    public const MAX_UPLOAD_BYTES = 50 * 1024 * 1024;

    private const TEXT_TYPES = ['application/json', 'text/csv', 'text/tab-separated-values', 'text/markdown', 'text/plain'];

    private const OPTIONS = ['slug', 'title', 'description', 'visibility', 'password', 'kind', 'entrypoint'];

    /** GET /artifacts, or /projects/{slug}/artifacts: the account's artifacts, or a project's own and linked ones. */
    public function index(Request $request, ?string $slug = null): JsonResponse
    {
        $slug ??= $request->query('project');
        $query = $slug !== null && $slug !== ''
            ? Document::availableTo($this->project($request, (string) $slug)->id)
            : Document::whereIn('project_id', $this->user($request)->projects()->select('id'));

        $documents = $query->whereNull('merged_into_id')->with(['project.user', 'linkedProjects:id,slug'])->orderByDesc('updated_at')->get();

        return response()->json(['artifacts' => $documents->map(fn (Document $d) => $this->summary($d))]);
    }

    public function show(Request $request, string $slugOrArtifact, ?string $artifact = null): JsonResponse
    {
        // /projects/{slug}/artifacts/{artifact}, or /artifacts/{artifact}?project=
        [$slug, $artifact] = $artifact === null ? [$request->query('project'), $slugOrArtifact] : [$slugOrArtifact, $artifact];
        $document = $this->find($request, $artifact, $slug);
        $version = $request->filled('version_number')
            ? ($document->findVersion($request->integer('version_number')) ?? abort(404, 'No such version.'))
            : $document->latestVersion();

        return response()->json($this->summary($document) + [
            'version' => $version->number,
            'versions' => $document->versions()->get()->map(fn (DocumentVersion $v) => [
                'number' => $v->number, 'url' => $v->url(), 'published_at' => $v->created_at?->toIso8601String(),
                'published_by' => $v->provenance['agent'] ?? null,
            ]),
        ] + $this->content($version));
    }

    /** POST /artifacts, or /projects/{slug}/artifacts: publish a file sent in the body. */
    public function store(Request $request, ?string $slug = null): JsonResponse
    {
        $project = $this->target($request, $slug ?? $request->input('project'));
        $data = $request->validate([
            'project' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filename' => ['required', 'string', 'max:255', 'not_regex:/[\\/\\\\]|^\\.+$/'],
            'content' => ['required_without:content_base64', 'prohibits:content_base64', 'string', 'max:'.self::MAX_PUBLISH_BYTES],
            'content_base64' => ['required_without:content', 'string', 'max:'.(int) ceil(self::MAX_PUBLISH_BYTES * 4 / 3 + 4)],
        ] + $this->optionRules());

        $bytes = $data['content'] ?? base64_decode($data['content_base64'], true);
        if ($bytes === false) {
            throw ValidationException::withMessages(['content_base64' => 'This is not valid base64.']);
        }
        if (strlen($bytes) > self::MAX_PUBLISH_BYTES) {
            throw ValidationException::withMessages(['content_base64' => 'Files over 10 MB need an upload link (artifacts/uploads).']);
        }

        $connection = $this->agent($request);
        $version = $this->publish($project, $data['filename'], $bytes, $data, $connection);

        return response()->json($this->published($version), 201);
    }

    /**
     * POST /artifacts/uploads: a one-time link that publishes the raw file sent to it, for a file
     * too large to send in a call, or one an agent has on disk. Good for 30 minutes.
     */
    public function createUpload(Request $request): JsonResponse
    {
        $project = $this->target($request, $request->input('project'));
        $data = $request->validate([
            'project' => ['sometimes', 'nullable', 'string', 'max:120'],
            'filename' => ['required', 'string', 'max:255', 'not_regex:/[\\/\\\\]|^\\.+$/'],
        ] + $this->optionRules());

        $token = Str::random(48);
        $upload = ArtifactUpload::create([
            'token_hash' => hash('sha256', $token),
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'mcp_connection_id' => $this->agent($request)?->id,
            'options' => array_intersect_key($data, array_flip([...self::OPTIONS, 'filename'])),
            'expires_at' => now()->addMinutes(30),
        ]);
        $url = route('api.uploads.store', ['token' => $token]);

        return response()->json([
            'upload_url' => $url,
            'expires_at' => $upload->expires_at->toIso8601String(),
            'max_bytes' => self::MAX_UPLOAD_BYTES,
            'command' => "curl -sS --fail-with-body -X PUT -H 'Content-Type: application/octet-stream' --data-binary @".escapeshellarg($data['filename'])." '".$url."'",
            'note' => 'Run the command from the folder with the file, or replace the path after @. The link works once.',
        ], 201);
    }

    /** PUT /uploads/{token}: the raw file for an upload link. The token is the permission. */
    public function receiveUpload(Request $request, string $token): JsonResponse
    {
        $upload = ArtifactUpload::where('token_hash', hash('sha256', $token))->with('project.user', 'connection')->first();
        abort_if($upload === null || $upload->used_at !== null || $upload->expires_at->isPast(), 404, 'This upload link has expired or was already used. Ask for a new one.');
        abort_unless($upload->project?->user?->canPublish(), 402, 'Publishing to '.config('app.name').' is part of a paid plan.');

        $bytes = $request->getContent();
        if ($bytes === '') {
            throw ValidationException::withMessages(['file' => 'Send the file as the request body.']);
        }
        if (strlen($bytes) > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages(['file' => 'Files over 50 MB are too large to publish.']);
        }

        // Claim the link before publishing, so a second request can't use it too
        $claimed = ArtifactUpload::whereKey($upload->id)->whereNull('used_at')->update(['used_at' => now()]);
        abort_if($claimed === 0, 404, 'This upload link was already used.');

        $options = $upload->options;
        try {
            $version = $this->publish($upload->project, $options['filename'], $bytes, $options, $upload->connection);
        } catch (\Throwable $exception) {
            // A failed publish leaves the link usable, so the file can be fixed and sent again
            $upload->forceFill(['used_at' => null])->save();
            throw $exception;
        }
        $upload->forceFill(['document_id' => $version->document_id])->save();

        return response()->json($this->published($version), 201);
    }

    /** DELETE /artifacts/{artifact}: move an artifact to the Trash, files and all, until restored. Needs an account token. */
    public function destroy(Request $request, string $artifact): JsonResponse
    {
        $this->user($request);
        $document = $this->find($request, $artifact, $request->query('project'), owned: $request->filled('project'));
        $document->delete();

        return response()->json(['deleted' => $document->slug, 'restore' => route('trash')]);
    }

    /** POST /projects/{slug}/linked-artifacts: list an artifact from elsewhere in the account in this project. */
    public function link(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);
        abort_if($project->is_home, 422, 'Choose a project to link to.');
        $data = $request->validate(['artifact' => ['required', 'string', 'max:120'], 'from_project' => ['sometimes', 'nullable', 'string', 'max:120']]);
        $document = $this->find($request, $data['artifact'], $data['from_project'] ?? null, owned: true);

        if ($document->project_id !== $project->id) {
            $document->linkedProjects()->syncWithoutDetaching([$project->id]);
        }

        return response()->json($this->summary($document->load('linkedProjects:id,slug')));
    }

    /** DELETE /projects/{slug}/linked-artifacts/{artifact}: stop listing it here. */
    public function unlink(Request $request, string $slug, string $artifact): JsonResponse
    {
        $project = $this->project($request, $slug);
        $document = $project->linkedDocuments()->where('documents.slug', $artifact)
            ->when($request->query('from_project'), fn ($q, $from) => $q->whereHas('project', fn ($p) => $p->where('slug', $from)))
            ->firstOrFail();
        $project->linkedDocuments()->detach($document->id);

        return response()->json($this->summary($document->load('linkedProjects:id,slug')));
    }

    /** @param  array<string, mixed>  $options */
    private function publish(Project $project, string $filename, string $bytes, array $options, ?McpConnection $connection): DocumentVersion
    {
        abort_unless($project->user?->canPublish(), 402, 'Publishing to '.config('app.name').' is part of a paid plan.');

        $bundle = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'zip' && filled($options['entrypoint'] ?? null);
        $path = tempnam(sys_get_temp_dir(), 'artifact-');
        file_put_contents($path, $bytes);
        $upload = new UploadedFile($path, $filename, null, null, true);

        $input = array_intersect_key($options, array_flip(self::OPTIONS));
        $input[$bundle ? 'bundle' : 'file'] = $upload;
        if (! $bundle) {
            unset($input['entrypoint']);
        }
        $slug = Str::slug($input['slug'] ?? pathinfo($bundle ? $input['entrypoint'] : $filename, PATHINFO_FILENAME)) ?: 'artifact';
        // New artifacts published this way start private
        if (! isset($input['visibility']) && ! Document::where('project_id', $project->id)->where('slug', $slug)->exists()) {
            $input['visibility'] = 'private';
        }
        if ($connection !== null) {
            $input['provenance'] = ['published_by' => 'agent', 'agent' => $connection->client_name, 'published_at' => now()->toIso8601String()];
        }

        try {
            return app(ArtifactPublisher::class)->publish($project, $input);
        } finally {
            @unlink($path);
        }
    }

    /** @return array<string, mixed> */
    private function published(DocumentVersion $version): array
    {
        $document = $version->document->load('project.user', 'linkedProjects:id,slug');

        return $this->summary($document) + ['version' => $version->number, 'version_url' => $version->url()];
    }

    /** @return array<string, array<int, mixed>> */
    private function optionRules(): array
    {
        return [
            'entrypoint' => ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:/\\.\\.|^\\/|\\\\/'],
        ] + array_intersect_key(ArtifactPublisher::rules(), array_flip(['slug', 'title', 'description', 'visibility', 'password', 'kind']));
    }

    /** The connected agent behind this request's token, if it is one. */
    private function agent(Request $request): ?McpConnection
    {
        $token = $request->user()?->currentAccessToken();

        return $token && isset($token->id)
            ? McpConnection::active()->where('personal_access_token_id', $token->id)->first()
            : null;
    }

    /** @return array<string, mixed> */
    private function summary(Document $document): array
    {
        return [
            'slug' => $document->slug,
            'title' => $document->title,
            'description' => $document->description,
            'kind' => $document->kind,
            'content_type' => $document->content_type,
            'visibility' => $document->visibility,
            'password_protected' => $document->isPasswordProtected(),
            'url' => $document->shareUrl(),
            'project' => $document->project?->is_home ? null : $document->project?->slug,
            'linked_projects' => $document->linkedProjects->pluck('slug')->values(),
            'latest_version' => $document->versions()->max('number') ?? 1,
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{content: ?string, content_truncated: bool} */
    private function content(DocumentVersion $version): array
    {
        $type = strtolower(trim(explode(';', (string) $version->content_type)[0]));
        $none = ['content' => null, 'content_truncated' => false];

        if (($version->document->driver ?? 'cloud') !== 'cloud' || $version->isBundle() || ! in_array($type, self::TEXT_TYPES, true)) {
            return $none;
        }

        $disk = Storage::disk($version->disk());
        if (! $version->disk_path || ! $disk->exists($version->entryPath())) {
            return $none;
        }

        $stream = $disk->readStream($version->entryPath());
        $text = (string) stream_get_contents($stream, self::MAX_CONTENT_BYTES + 1);
        fclose($stream);
        $truncated = strlen($text) > self::MAX_CONTENT_BYTES;

        return ['content' => mb_strcut($text, 0, self::MAX_CONTENT_BYTES, 'UTF-8'), 'content_truncated' => $truncated];
    }

    /**
     * An artifact by its slug. With a project: that project's own artifact, or one linked to it.
     * Without: one with no project, or else the only artifact in the account with that slug.
     * $owned keeps to the artifact's own project, for linking.
     */
    private function find(Request $request, string $artifact, ?string $slug, bool $owned = false): Document
    {
        if ($slug !== null && $slug !== '') {
            $project = $this->project($request, $slug);
            $query = $owned ? Document::where('project_id', $project->id) : Document::availableTo($project->id);

            return $query->where('slug', $artifact)->orderByRaw('case when project_id = ? then 0 else 1 end', [$project->id])
                ->with('project.user', 'linkedProjects:id,slug')->firstOrFail();
        }

        $user = $this->user($request);
        $matches = Document::whereIn('project_id', $user->projects()->select('id'))->where('slug', $artifact)
            ->with('project.user', 'linkedProjects:id,slug')->get();
        $home = $matches->first(fn (Document $d) => $d->project?->is_home);

        if ($home) {
            return $home;
        }
        abort_if($matches->isEmpty(), 404, 'No artifact has that name.');
        abort_if($matches->count() > 1, 409, 'Several projects have an artifact with that name. Pass project.');

        return $matches->first();
    }

    /** Where to publish: the named project, or the account's home when none is named. */
    private function target(Request $request, mixed $slug): Project
    {
        return is_string($slug) && $slug !== '' ? $this->project($request, $slug) : $this->user($request)->artifactHome();
    }

    private function user(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403, 'This needs a user token. A project token works on its own project only.');

        return $actor;
    }

    private function project(Request $request, string $slug): Project
    {
        $actor = $request->user();
        if ($actor instanceof Project) {
            abort_unless($actor->slug === $slug && $actor->tokenCan(Project::PULL_ABILITY), 403);

            return $actor;
        }
        abort_unless($actor instanceof User, 403);

        return $actor->projects()->where('slug', $slug)->firstOrFail();
    }
}
