<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentViewController extends Controller
{
    /**
     * Public wrapper page: title bar + iframe of the document + raw link.
     * This is where view analytics accrue.
     */
    public function show(string $key): Response
    {
        $document = Document::where('key', $key)->firstOrFail();
        $document->increment('views');

        return response()->view('documents.show', [
            'document' => $document,
            'project' => $document->project,
        ]);
    }

    /**
     * The document itself, served for the iframe and for "view raw". For
     * bundles this is the entry HTML; its relative asset references resolve
     * to /d/{key}/{path}, handled by asset() below.
     */
    public function raw(string $key): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $document = Document::where('key', $key)->firstOrFail();

        // Content lives on the R2 custom domain in production
        if (config('framework.documents_base_url')) {
            return redirect()->away($document->contentUrl());
        }

        $disk = config('framework.documents_disk');

        $path = $document->entrypoint
            ? $document->disk_path.'/'.$document->entrypoint
            : $document->disk_path;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, $document->slug.'.html', [
            'Content-Type' => $document->content_type,
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Bundle assets: the entry page at /d/{key}/raw references files
     * relatively (analysis_files/libs/...), which the browser resolves to
     * /d/{key}/{path} — served here from the bundle prefix.
     */
    public function asset(string $key, string $path): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $document = Document::where('key', $key)->firstOrFail();

        abort_if($document->entrypoint === null, 404);
        abort_if(str_contains($path, '..') || str_starts_with($path, '/'), 404);

        if ($base = config('framework.documents_base_url')) {
            return redirect()->away(rtrim($base, '/').'/'.$document->disk_path.'/'.$path);
        }

        $disk = config('framework.documents_disk');
        $full = $document->disk_path.'/'.$path;

        abort_unless(Storage::disk($disk)->exists($full), 404);

        return Storage::disk($disk)->response($full, basename($path), [
            'Content-Type' => \App\Support\BundleExtractor::contentType($path),
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
