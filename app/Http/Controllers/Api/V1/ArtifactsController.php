<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
 * A project's published artifacts, read over the API. A user token reads any of the user's
 * projects; a project token reads its own. Showing one artifact includes the newest version's
 * content when it is text (a model's JSON, a CSV table, Markdown), so an agent can write about the
 * published results.
 *
 * Publishing takes the file in the JSON body, as text or base64, so an agent that has a saved
 * file (an HTML notebook, a PNG figure, a CSV table, a model's JSON) can publish it without the
 * packages. A zip with an HTML entrypoint publishes a rendered bundle, such as a Quarto site.
 */
class ArtifactsController extends Controller
{
    /** Largest content returned inline, in bytes. */
    public const MAX_CONTENT_BYTES = 200_000;

    /** Largest file published from a JSON body, before base64. */
    public const MAX_PUBLISH_BYTES = 10 * 1024 * 1024;

    private const TEXT_TYPES = ['application/json', 'text/csv', 'text/tab-separated-values', 'text/markdown', 'text/plain'];

    public function index(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);
        $documents = Document::where('project_id', $project->id)->whereNull('merged_into_id')
            ->with('project.user')->orderByDesc('updated_at')->get();

        return response()->json(['artifacts' => $documents->map(fn (Document $d) => $this->summary($d))]);
    }

    public function show(Request $request, string $slug, string $artifact): JsonResponse
    {
        $project = $this->project($request, $slug);
        $document = Document::where('project_id', $project->id)->where('slug', $artifact)->with('project.user')->firstOrFail();
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

    public function store(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);
        abort_unless($project->user?->canPublish(), 402, 'Publishing to '.config('app.name').' is part of a paid plan.');

        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255', 'not_regex:/[\\/\\\\]|^\\.+$/'],
            'content' => ['required_without:content_base64', 'prohibits:content_base64', 'string', 'max:'.(self::MAX_PUBLISH_BYTES)],
            'content_base64' => ['required_without:content', 'string', 'max:'.(int) ceil(self::MAX_PUBLISH_BYTES * 4 / 3 + 4)],
            'entrypoint' => ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:/\\.\\.|^\\/|\\\\/'],
        ] + array_intersect_key(ArtifactPublisher::rules(), array_flip(['slug', 'title', 'description', 'visibility', 'password', 'kind'])));

        $bytes = isset($data['content']) ? $data['content'] : base64_decode($data['content_base64'], true);
        if ($bytes === false) {
            throw ValidationException::withMessages(['content_base64' => 'This is not valid base64.']);
        }
        if (strlen($bytes) > self::MAX_PUBLISH_BYTES) {
            throw ValidationException::withMessages(['content_base64' => 'Files over 10 MB need the R, Python, or Stata package, or fw publish.']);
        }

        $bundle = strtolower(pathinfo($data['filename'], PATHINFO_EXTENSION)) === 'zip' && filled($data['entrypoint'] ?? null);
        $path = tempnam(sys_get_temp_dir(), 'artifact-');
        file_put_contents($path, $bytes);
        $upload = new UploadedFile($path, $data['filename'], null, null, true);

        $slug = Str::slug($data['slug'] ?? pathinfo($bundle ? $data['entrypoint'] : $data['filename'], PATHINFO_FILENAME)) ?: 'artifact';
        $input = array_intersect_key($data, array_flip(['slug', 'title', 'description', 'visibility', 'password', 'kind', 'entrypoint']));
        $input[$bundle ? 'bundle' : 'file'] = $upload;
        if (! $bundle) {
            unset($input['entrypoint']);
        }
        // New artifacts published this way start private
        if (! isset($input['visibility']) && ! Document::where('project_id', $project->id)->where('slug', $slug)->exists()) {
            $input['visibility'] = 'private';
        }
        $agent = $this->agent($request);
        if ($agent !== null) {
            $input['provenance'] = ['published_by' => 'agent', 'agent' => $agent->client_name, 'published_at' => now()->toIso8601String()];
        }

        try {
            $version = app(ArtifactPublisher::class)->publish($project, $input);
        } finally {
            @unlink($path);
        }

        $document = $version->document->load('project.user');

        return response()->json($this->summary($document) + ['version' => $version->number, 'version_url' => $version->url()], 201);
    }

    /** The connected agent behind this request's token, if it is one. */
    private function agent(Request $request): ?McpConnection
    {
        $token = $request->user()?->currentAccessToken();

        return $token && isset($token->id)
            ? McpConnection::active()->where('personal_access_token_id', $token->id)->first()
            : null;
    }

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
