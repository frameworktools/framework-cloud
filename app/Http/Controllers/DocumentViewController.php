<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Support\BundleExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public pages for published documents.
 *
 * Private links: /d/{key} (newest) and /d/{key}/versions/{number}.
 * Readable links, for public documents only:
 * /@{handle}/{project}/{slug} and .../versions/{number}.
 *
 * The page is a title bar with the version list around an iframe of the
 * content, which loads from /d/{key}/.../raw or the documents domain.
 */
class DocumentViewController extends Controller
{
    public function show(string $key): Response
    {
        return $this->page($this->byKey($key));
    }

    public function showVersion(string $key, int $number): Response
    {
        return $this->page($this->byKey($key), $number);
    }

    public function readable(string $handle, string $project, string $slug): Response
    {
        return $this->page($this->byReadable($handle, $project, $slug), readable: true);
    }

    public function readableVersion(string $handle, string $project, string $slug, int $number): Response
    {
        return $this->page($this->byReadable($handle, $project, $slug), $number, readable: true);
    }

    public function raw(string $key): StreamedResponse|RedirectResponse
    {
        return $this->serveEntry($this->byKey($key)->latestVersion());
    }

    public function versionRaw(string $key, int $number): StreamedResponse|RedirectResponse
    {
        return $this->serveEntry($this->version($this->byKey($key), $number));
    }

    /**
     * Bundle assets. The entry page references files relatively
     * (analysis_files/libs/...), which resolve next to its raw URL.
     */
    public function asset(string $key, string $path): StreamedResponse|RedirectResponse
    {
        return $this->serveAsset($this->byKey($key)->latestVersion(), $path);
    }

    public function versionAsset(string $key, int $number, string $path): StreamedResponse|RedirectResponse
    {
        return $this->serveAsset($this->version($this->byKey($key), $number), $path);
    }

    private function page(Document $document, ?int $number = null, bool $readable = false): Response
    {
        $version = $number ? $this->version($document, $number) : $document->latestVersion();
        $versions = $document->versions()->get(['number', 'created_at']);
        $latest = $versions->first()?->number ?? 1;

        // Version links stay in the form the reader arrived with
        $link = fn (int $n) => $n === $latest
            ? ($readable ? $document->readableUrl() : $document->publicUrl())
            : ($readable ? $document->readableUrl($n) : route('documents.version', ['key' => $document->key, 'number' => $n]));

        $document->increment('views');

        return response()->view('documents.show', [
            'document' => $document,
            'project' => $document->project,
            'version' => $version,
            'latest' => $latest,
            'versions' => $versions->map(fn ($v) => ['number' => $v->number, 'date' => $v->created_at, 'url' => $link($v->number)]),
        ]);
    }

    private function byKey(string $key): Document
    {
        return Document::where('key', $key)->with('project.user')->firstOrFail();
    }

    private function byReadable(string $handle, string $project, string $slug): Document
    {
        $user = User::where('handle', $handle)->firstOrFail();

        return Document::where('slug', $slug)
            ->where('visibility', 'public')
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id)->where('slug', $project))
            ->with('project.user')
            ->firstOrFail();
    }

    private function version(Document $document, int $number): DocumentVersion
    {
        return $document->findVersion($number) ?? abort(404);
    }

    private function serveEntry(DocumentVersion $version): StreamedResponse|RedirectResponse
    {
        // Content lives on the documents domain in production
        if (config('framework.documents_base_url')) {
            return redirect()->away($version->contentUrl());
        }

        $disk = Storage::disk(config('framework.documents_disk'));
        abort_unless($disk->exists($version->entryPath()), 404);

        return $disk->response($version->entryPath(), $version->document->slug.'.html', [
            'Content-Type' => $version->content_type,
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function serveAsset(DocumentVersion $version, string $path): StreamedResponse|RedirectResponse
    {
        abort_unless($version->isBundle(), 404);
        abort_if(str_contains($path, '..') || str_starts_with($path, '/'), 404);

        if ($base = config('framework.documents_base_url')) {
            return redirect()->away(rtrim($base, '/').'/'.$version->disk_path.'/'.$path);
        }

        $disk = Storage::disk(config('framework.documents_disk'));
        $full = $version->disk_path.'/'.$path;
        abort_unless($disk->exists($full), 404);

        return $disk->response($full, basename($path), [
            'Content-Type' => BundleExtractor::contentType($path),
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
