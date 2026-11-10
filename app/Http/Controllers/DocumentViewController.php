<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Support\ArtifactContentAccess;
use App\Support\ArtifactPreview;
use App\Support\BundleExtractor;
use App\Support\ChartPage;
use App\Support\ContentHost;
use App\Support\ContentWorker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
    public function show(string $key): Response|RedirectResponse
    {
        return $this->page($this->byKey($key));
    }

    public function showVersion(string $key, int $number): Response|RedirectResponse
    {
        return $this->page($this->byKey($key), $number);
    }

    public function readable(string $handle, string $project, string $slug): Response|RedirectResponse
    {
        return $this->page($this->byReadable($handle, $project, $slug), readable: true);
    }

    public function readableVersion(string $handle, string $project, string $slug, int $number): Response|RedirectResponse
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

    /** Bearer access stays in the path so relative module and fetch URLs inherit it. */
    public function content(string $key, int $number, string $access, string $path = ''): StreamedResponse|RedirectResponse
    {
        $version = $this->version($this->byKey($key), $number);
        abort_unless(ArtifactContentAccess::accepts($version, $access), 403);
        abort_if($version->usesExternalUrl() || ($version->document->driver ?? 'cloud') !== 'cloud', 404);
        // Links issued before the Worker served files: send them to it
        if (ContentWorker::enabled($version)) {
            abort_if(str_contains($path, '..') || str_contains($path, "\0"), 404);

            return redirect()->away(ContentWorker::url($version, $version->isBundle() && $path !== '' ? $path : null))
                ->header('Cache-Control', 'private, no-store');
        }
        // A single file, under its own name (any name works; the signed access names the version)
        if (! $version->isBundle()) {
            return $this->serveFile($version);
        }
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0") || str_starts_with($path, '/'), 404);
        $disk = Storage::disk($version->disk());
        $full = $version->disk_path.'/'.$path;
        if ($path === '' || $disk->directoryExists($full)) {
            $entry = $path === '' ? $version->entrypoint : rtrim($path, '/').'/index.html';
            abort_unless($disk->fileExists($version->disk_path.'/'.$entry), 404);

            return redirect()->route('documents.content', [
                'key' => $key, 'number' => $number, 'access' => $access, 'path' => $entry,
            ])->header('Cache-Control', 'private, no-store');
        }
        abort_unless($disk->fileExists($full), 404);

        return $disk->response($full, basename($path), [
            'Content-Type' => BundleExtractor::contentType($path),
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox allow-scripts allow-downloads; '.ContentHost::frameAncestors(),
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function unlock(Request $request, string $key): RedirectResponse
    {
        $document = $this->byKey($key);
        $validated = $request->validate(['password' => ['required', 'string', 'max:200'], 'version' => ['sometimes', 'nullable', 'integer', 'min:1']]);
        if (! $document->isPasswordProtected() || ! Hash::check($validated['password'], $document->password_hash)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
        $request->session()->put($document->passwordSessionKey(), $document->password_hash);

        return redirect()->to(isset($validated['version']) ? $this->version($document, $validated['version'])->url() : $document->publicUrl());
    }

    public function download(string $key): StreamedResponse|RedirectResponse
    {
        return $this->serveDownload($this->byKey($key)->latestVersion());
    }

    public function versionDownload(string $key, int $number): StreamedResponse|RedirectResponse
    {
        return $this->serveDownload($this->version($this->byKey($key), $number));
    }

    private function serveDownload(DocumentVersion $version): StreamedResponse|RedirectResponse
    {
        abort_unless(($version->document->driver ?? 'cloud') === 'cloud', 404);
        abort_unless($version->document->canRead(), 403, 'Enter the artifact password on its page.');
        $disk = Storage::disk($version->disk());
        $path = $version->source_path ?? $version->entryPath();
        abort_unless($disk->exists($path), 404);
        $filename = $version->source_path
            ? ($version->original_filename ?? $version->document->slug.'.zip')
            : ($version->isBundle() ? basename($version->entrypoint) : ($version->original_filename ?? $version->document->slug.'.html'));

        $type = $version->source_path ? 'application/zip' : $version->content_type;
        // Counted here, before the file leaves through the Worker or the app
        // Counted without touching updated_at, which means a content change
        Document::whereKey($version->document_id)->toBase()->increment('downloads');
        // The Worker sends the bytes; the app only checks and signs
        if (ContentWorker::enabled($version)) {
            return redirect()->away(ContentWorker::download($path, $filename, (string) $type))->header('Cache-Control', 'private, no-store');
        }

        return $disk->download($path, $filename, [
            'Content-Type' => $version->source_path ? 'application/zip' : $version->content_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * A Vega-Lite chart, drawn in an opaque sandbox: a specification can run Vega expressions and name data
     * URLs, so it gets no access to this origin and no network beyond the pinned chart libraries.
     */
    public function chart(Request $request, string $key, int $number, string $access): Response|RedirectResponse
    {
        $version = $this->version($this->byKey($key), $number);
        abort_unless(ArtifactContentAccess::accepts($version, $access), 403);
        abort_unless(ArtifactPreview::isVegaLite($version->original_filename) && ! $version->isBundle() && ! $version->usesExternalUrl(), 404);
        // Links issued before chart pages were stored: send them to the stored page
        if (ContentWorker::enabled($version) && ChartPage::ensure($version)) {
            return redirect()->away(ContentWorker::chart($version, ChartPage::path($version)).($request->boolean('dark') ? '?dark=1' : ''))
                ->header('Cache-Control', 'private, no-store');
        }
        $html = ChartPage::render($version, $request->boolean('dark')) ?? abort(404);

        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => ChartPage::CSP.'; '.ContentHost::frameAncestors(),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function page(Document $document, ?int $number = null, bool $readable = false): Response|RedirectResponse
    {
        abort_if($document->visibility === 'private' && ! $document->canRead(), 404);
        if (($document->driver ?? 'cloud') !== 'cloud') {
            $url = $number ? $document->findVersion($number)?->external_url : $document->external_url;
            abort_unless($url, 404, 'This publication has no browser URL.');

            return redirect()->away($url);
        }
        if (! $document->canRead()) {
            return response()->view('documents.password', ['document' => $document, 'number' => $number], 200)->header('Cache-Control', 'private, no-store');
        }

        $version = $number ? $this->version($document, $number) : $document->latestVersion();
        $versions = $document->versions()->get(['number', 'created_at']);
        $latest = $versions->first()?->number ?? 1;

        // Version links stay in the form the reader arrived with
        $link = fn (int $n) => $n === $latest
            ? ($readable ? $document->readableUrl() : $document->publicUrl())
            : ($readable ? $document->readableUrl($n) : route('documents.version', ['key' => $document->key, 'number' => $n]));

        Document::whereKey($document->id)->toBase()->increment('views');

        return response()->view('documents.show', [
            'document' => $document,
            'project' => $document->project,
            'version' => $version,
            'latest' => $latest,
            'preview' => ArtifactPreview::forVersion($version),
            'versions' => $versions->map(fn ($v) => ['number' => $v->number, 'date' => $v->created_at, 'url' => $link($v->number)]),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function byKey(string $key): Document
    {
        return Document::where('key', $key)->whereHas('project')->with('project.user')->firstOrFail();
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
        abort_unless(($version->document->driver ?? 'cloud') === 'cloud', 404);
        abort_unless($version->document->canRead(), 403, 'Enter the artifact password on its page.');
        if ($version->usesExternalUrl()) {
            return redirect()->away($version->contentUrl());
        }

        // With a content host, files open there, never on the app
        if (ContentHost::enabled() || ($version->isBundle() && dirname($version->entrypoint) !== '.')) {
            return redirect()->away($version->contentUrl());
        }

        return $this->serveFile($version);
    }

    /** One stored file in the opaque sandbox: inline when a browser shows it, else a download. */
    private function serveFile(DocumentVersion $version): StreamedResponse
    {
        $disk = Storage::disk($version->disk());
        abort_unless($disk->exists($version->entryPath()), 404);

        return $disk->response($version->entryPath(), $version->original_filename ?? $version->document->slug.'.html', [
            'Content-Type' => $version->content_type,
            ...ContentHost::frameOptions(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox allow-scripts allow-downloads; '.ContentHost::frameAncestors(),
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ], (str_starts_with($version->content_type, 'text/') || str_starts_with($version->content_type, 'image/') || in_array($version->content_type, ['application/pdf', 'application/xhtml+xml'])) ? 'inline' : 'attachment');
    }

    private function serveAsset(DocumentVersion $version, string $path): StreamedResponse|RedirectResponse
    {
        abort_unless(($version->document->driver ?? 'cloud') === 'cloud', 404);
        abort_unless($version->document->canRead(), 403, 'Enter the artifact password on its page.');
        abort_unless($version->isBundle(), 404);
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/'), 404);

        if ($version->usesExternalUrl()) {
            $base = config('framework.documents_base_url');

            return redirect()->away(rtrim($base, '/').'/'.$version->disk_path.'/'.$path);
        }
        if (ContentWorker::enabled($version)) {
            return redirect()->away(ContentWorker::url($version, $path));
        }
        if (ContentHost::enabled()) {
            return redirect()->away(ContentHost::route('documents.content', [
                'key' => $version->document->key, 'number' => $version->number,
                'access' => ArtifactContentAccess::issue($version), 'path' => $path,
            ]));
        }

        $disk = Storage::disk($version->disk());
        $full = $version->disk_path.'/'.$path;
        abort_unless($disk->exists($full), 404);

        return $disk->response($full, basename($path), [
            'Content-Type' => BundleExtractor::contentType($path),
            ...ContentHost::frameOptions(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox allow-scripts allow-downloads; '.ContentHost::frameAncestors(),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
