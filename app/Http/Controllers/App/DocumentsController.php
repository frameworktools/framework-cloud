<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notebooks and reports a user has published from their projects with
 * framework::publish(). Publishing happens from R; here people see, share,
 * and manage them: readable links, versions, and taking them down.
 */
class DocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Published', [
            'canPublish' => $request->user()->canPublish(),
            'handle' => $request->user()->handle,
            'documents' => $this->documents($request)->with(['project:id,user_id,name,slug', 'project.user:id,handle', 'versions'])
                ->latest('updated_at')->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title ?? $d->slug,
                    'slug' => $d->slug,
                    'url' => $d->shareUrl(),
                    'private_url' => $d->publicUrl(),
                    'readable' => $d->isReadable(),
                    'readable_url' => $d->readableUrl(),
                    'views' => $d->views,
                    'size_bytes' => $d->size_bytes,
                    'bundle' => $d->entrypoint !== null,
                    'published_at' => $d->created_at,
                    'updated_at' => $d->updated_at,
                    'project' => $d->project->only(['id', 'name', 'slug']),
                    'versions' => $d->versions->map(fn (DocumentVersion $v) => [
                        'number' => $v->number,
                        'url' => $d->readableUrl($v->number) ?? $v->url(),
                        'size_bytes' => $v->size_bytes,
                        'created_at' => $v->created_at,
                    ]),
                ]),
        ]);
    }

    /** Turn the readable URL on or off. The private link always works. */
    public function update(Request $request, int $document): RedirectResponse
    {
        $document = $this->documents($request)->findOrFail($document);
        $validated = $request->validate(['readable' => ['required', 'boolean']]);

        $document->update(['visibility' => $validated['readable'] ? 'public' : 'unlisted']);

        return back()->with('status', 'document-updated');
    }

    public function destroy(Request $request, int $document): RedirectResponse
    {
        $document = $this->documents($request)->findOrFail($document);

        $versions = $document->versions()->get();
        $versions->isEmpty() ? $document->latestVersion()->deleteFiles() : $versions->each->deleteFiles();
        $document->delete();

        return back()->with('status', 'document-deleted');
    }

    /**
     * Delete one version. Deleting the newest makes the one before it
     * current; deleting the only version takes the document down.
     */
    public function destroyVersion(Request $request, int $document, int $number): RedirectResponse
    {
        $document = $this->documents($request)->findOrFail($document);
        $version = $document->versions()->where('number', $number)->firstOrFail();

        if ($document->versions()->count() === 1) {
            return $this->destroy($request, $document->id);
        }

        $version->deleteFiles();
        $version->delete();

        $newest = $document->versions()->first();
        $document->update($newest->only(['disk_path', 'entrypoint', 'content_type', 'size_bytes']));

        return back()->with('status', 'version-deleted');
    }

    private function documents(Request $request)
    {
        return Document::whereIn('project_id', $request->user()->projects()->select('id'));
    }
}
