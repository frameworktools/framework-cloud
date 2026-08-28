<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentsController extends Controller
{
    /**
     * Publish a rendered document to this project. Republishing the same
     * slug replaces the content in place (same URL, views preserved).
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->user();
        abort_unless($project instanceof Project, 403, 'Publishing requires a project token.');
        abort_unless($request->user()->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks publish rights.');

        $validated = $request->validate([
            // Either a single self-contained HTML file...
            'file' => [
                'required_without:bundle', 'file',
                'max:51200', // operational upload bound, not a plan limit
                'mimetypes:text/html,application/xhtml+xml,text/plain',
            ],
            // ...or a zip of a full render (entry HTML + asset directories)
            'bundle' => [
                'required_without:file', 'file',
                'max:51200', // operational upload bound, not a plan limit
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
            ],
            'entrypoint' => ['required_with:bundle', 'string', 'max:255', 'not_regex:/\.\.|^\//'],
            'slug' => ['sometimes', 'string', 'max:120'],
            'title' => ['sometimes', 'string', 'max:200'],
        ]);

        $disk = config('framework.documents_disk');
        $isBundle = isset($validated['bundle']);
        $upload = $isBundle ? $validated['bundle'] : $validated['file'];

        $sourceName = $isBundle
            ? ($validated['entrypoint'] ?? $upload->getClientOriginalName())
            : $upload->getClientOriginalName();
        $slug = Str::slug($validated['slug'] ?? pathinfo($sourceName, PATHINFO_FILENAME));

        $document = Document::firstOrNew([
            'project_id' => $project->id,
            'slug' => $slug ?: 'document',
        ]);

        // Store the NEW content first; only then remove the old, so a failed
        // upload never strands an existing document
        $oldPath = $document->exists ? $document->disk_path : null;
        $oldWasBundle = $document->entrypoint !== null;

        if ($isBundle) {
            $prefix = 'documents/'.$project->id.'/'.Str::uuid();

            try {
                $stats = \App\Support\BundleExtractor::extract($upload, $disk, $prefix);
            } catch (\RuntimeException $e) {
                Storage::disk($disk)->deleteDirectory($prefix);
                abort(422, $e->getMessage());
            }

            if (! Storage::disk($disk)->exists($prefix.'/'.$validated['entrypoint'])) {
                Storage::disk($disk)->deleteDirectory($prefix);
                abort(422, 'The bundle does not contain the declared entrypoint.');
            }

            $document->fill([
                'disk_path' => $prefix,
                'entrypoint' => $validated['entrypoint'],
                'size_bytes' => $stats['bytes'],
            ]);
        } else {
            $path = $upload->storeAs('documents/'.$project->id, Str::uuid().'.html', $disk);
            $document->fill([
                'disk_path' => $path,
                'entrypoint' => null,
                'size_bytes' => $upload->getSize(),
            ]);
        }

        // Replace stored bytes; the key (and therefore the URL) stays stable
        if ($oldPath) {
            $oldWasBundle
                ? Storage::disk($disk)->deleteDirectory($oldPath)
                : Storage::disk($disk)->delete($oldPath);
        }

        $document->fill([
            'key' => $document->key ?? Document::newKey(),
            'title' => $validated['title'] ?? $document->title ?? Str::headline($slug),
            'content_type' => 'text/html; charset=utf-8',
        ])->save();

        return response()->json([
            'slug' => $document->slug,
            'url' => $document->publicUrl(),
            'raw_url' => $document->rawUrl(),
        ], 201);
    }
}
