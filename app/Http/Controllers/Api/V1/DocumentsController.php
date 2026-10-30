<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Support\BundleExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentsController extends Controller
{
    /**
     * Publish a rendered document to this project. Republishing the same
     * slug adds a new version under the same links; earlier versions stay
     * available until their owner deletes them.
     *
     * `slug` names the document within the project. `readable` turns on the
     * readable URL, /@{handle}/{project}/{slug}. Leaving it out keeps the
     * document's current setting (new documents start private).
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->user();
        abort_unless($project instanceof Project, 403, 'Publishing requires a project token.');
        abort_unless($request->user()->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks publish rights.');
        abort_unless(
            $project->user->canPublish(), 402,
            'Publishing to '.config('app.name').' is part of a paid plan. Ask about it in your account, or publish to your own bucket with a connection.',
        );

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
            'readable' => ['sometimes', 'boolean'],
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

        // Each publish stores its files under a new prefix, so earlier
        // versions keep their own copies
        if ($isBundle) {
            $prefix = 'documents/'.$project->id.'/'.Str::uuid();

            try {
                $stats = BundleExtractor::extract($upload, $disk, $prefix);
            } catch (\RuntimeException $e) {
                Storage::disk($disk)->deleteDirectory($prefix);
                abort(422, $e->getMessage());
            }

            if (! Storage::disk($disk)->exists($prefix.'/'.$validated['entrypoint'])) {
                Storage::disk($disk)->deleteDirectory($prefix);
                abort(422, 'The bundle does not contain the declared entrypoint.');
            }

            $stored = ['disk_path' => $prefix, 'entrypoint' => $validated['entrypoint'], 'size_bytes' => $stats['bytes']];
        } else {
            $path = $upload->storeAs('documents/'.$project->id, Str::uuid().'.html', $disk);
            $stored = ['disk_path' => $path, 'entrypoint' => null, 'size_bytes' => $upload->getSize()];
        }

        $stored['content_type'] = 'text/html; charset=utf-8';

        $version = DB::transaction(function () use ($document, $stored, $validated, $slug) {
            $document->fill($stored + [
                'key' => $document->key ?? Document::newKey(),
                'title' => $validated['title'] ?? $document->title ?? Str::headline($slug),
            ]);

            if (array_key_exists('readable', $validated)) {
                $document->visibility = $validated['readable'] ? 'public' : 'unlisted';
            }

            $document->save();

            $number = (int) $document->versions()->max('number') + 1;

            return $document->versions()->create($stored + ['number' => $number]);
        });

        $document->load('project.user');
        $readableUrl = $document->readableUrl();

        return response()->json([
            'slug' => $document->slug,
            'version' => $version->number,
            // The link to share: readable when available, else private
            'url' => $readableUrl ?? $document->publicUrl(),
            'private_url' => $document->publicUrl(),
            'readable_url' => $readableUrl,
            'version_url' => $readableUrl ? $document->readableUrl($version->number) : $version->url(),
            'raw_url' => $document->rawUrl(),
            'notice' => $document->isReadable() && ! $readableUrl
                ? 'Set a handle in Settings > Account to get a readable URL. The private link works now.'
                : null,
        ], 201);
    }
}
