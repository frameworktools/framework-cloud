<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notebooks and reports a user has published from their projects with
 * framework::publish(). Publishing happens from R; here people see,
 * share, and take them down.
 */
class DocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Published', [
            'canPublish' => $request->user()->canPublish(),
            'documents' => $this->documents($request)->with('project:id,name,slug')->latest('updated_at')->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title ?? $d->slug,
                    'slug' => $d->slug,
                    'url' => $d->publicUrl(),
                    'views' => $d->views,
                    'size_bytes' => $d->size_bytes,
                    'bundle' => $d->entrypoint !== null,
                    'published_at' => $d->created_at,
                    'updated_at' => $d->updated_at,
                    'project' => $d->project->only(['id', 'name', 'slug']),
                ]),
        ]);
    }

    public function destroy(Request $request, int $document): RedirectResponse
    {
        $document = $this->documents($request)->findOrFail($document);
        $disk = Storage::disk(config('framework.documents_disk'));

        $document->entrypoint !== null
            ? $disk->deleteDirectory($document->disk_path)
            : $disk->delete($document->disk_path);

        $document->delete();

        return back()->with('status', 'document-deleted');
    }

    private function documents(Request $request)
    {
        return Document::whereIn('project_id', $request->user()->projects()->select('id'));
    }
}
