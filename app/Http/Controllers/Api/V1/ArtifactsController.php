<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A project's published artifacts, read over the API. A user token reads any of the user's
 * projects; a project token reads its own. Showing one artifact includes the newest version's
 * content when it is text (a model's JSON, a CSV table, Markdown), so an agent can write about the
 * published results.
 */
class ArtifactsController extends Controller
{
    /** Largest content returned inline, in bytes. */
    public const MAX_CONTENT_BYTES = 200_000;

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
            ]),
        ] + $this->content($version));
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
