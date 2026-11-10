<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Report;
use App\Support\ArtifactPublisher;
use App\Support\PublishDrivers;
use App\Support\PublishingQuota;
use App\Support\Share;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'storage' => ['used' => PublishingQuota::used($request->user()), 'limit' => PublishingQuota::limit()],
            'handle' => $request->user()->handle,
            // Publishing here can also go to no project: the account's automatic home, listed last
            'projects' => [
                ...$request->user()->projects()->listed()->orderBy('name')->get(['id', 'name', 'slug']),
                ...($request->user()->canPublish() ? [['id' => $request->user()->artifactHome()->id, 'name' => 'No project', 'slug' => null, 'is_home' => true]] : []),
            ],
            'documents' => $this->documents($request)->with(['project:id,user_id,name,slug,is_home', 'project.user:id,handle', 'versions', 'linkedProjects:id,name,slug'])
                ->latest('updated_at')->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'driver' => $d->driver ?? 'cloud', 'external_url' => $d->external_url,
                    'location' => PublishDrivers::label($d->driver ?? 'cloud'),
                    'kind' => $d->kind, 'visibility' => $d->visibility,
                    'description' => $d->description,
                    'original_filename' => $d->original_filename,
                    'password_protected' => $d->isPasswordProtected(),
                    'merged_into_id' => $d->merged_into_id,
                    'merged_at' => $d->merged_at,
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
                    'linked_projects' => $d->linkedProjects->map->only('id', 'name', 'slug')->values(),
                    'project' => $d->project->is_home ? ['id' => $d->project->id, 'name' => 'No project', 'slug' => null, 'is_home' => true] : $d->project->only(['id', 'name', 'slug']),
                    'versions' => ($d->versions->isEmpty() ? collect([$d->latestVersion()]) : $d->versions)->map(fn (DocumentVersion $v) => [
                        'number' => $v->number,
                        'kind' => $v->kind,
                        'provenance' => $v->provenance,
                        'url' => $d->readableUrl($v->number) ?? $v->url(),
                        'size_bytes' => $v->size_bytes,
                        'created_at' => $v->created_at,
                    ]),
                ]),
        ]);
    }

    /** One artifact's own page: preview, versions, sharing, and settings, outside its project. */
    public function show(Request $request, int $document): Response
    {
        $document = $this->documents($request)->with(['project.user', 'versions', 'linkedProjects:id,name,slug'])->findOrFail($document);
        $versions = $document->versions->isEmpty() ? collect([$document->latestVersion()]) : $document->versions;
        $reports = Report::whereIn('project_id', [$document->project_id, ...$document->linkedProjects->pluck('id')])->get(['id', 'title', 'draft_blocks'])
            ->filter(fn (Report $report) => collect($report->draft_blocks ?? [])->contains(fn ($block) => (int) ($block['document_id'] ?? 0) === $document->id));

        return Inertia::render('Artifacts/Show', [
            'handle' => $request->user()->handle,
            'artifact' => [
                ...$document->only('driver', 'external_url', 'id', 'kind', 'slug', 'description', 'visibility', 'views', 'size_bytes', 'original_filename', 'merged_into_id', 'merged_at'),
                'capabilities' => PublishDrivers::capabilities($document->driver ?? 'cloud'),
                'location' => PublishDrivers::label($document->driver ?? 'cloud'),
                'title' => $document->title ?? $document->slug,
                'password_protected' => $document->isPasswordProtected(),
                'readable' => $document->isReadable(),
                'url' => $document->shareUrl(), 'private_url' => $document->publicUrl(), 'readable_url' => $document->readableUrl(),
                'bundle' => $document->entrypoint !== null,
                'published_at' => $document->created_at, 'updated_at' => $document->updated_at,
                'merged_into' => $document->merged_into_id ? Document::find($document->merged_into_id)?->only('id', 'title') : null,
            ],
            'project' => $document->project->only('id', 'name', 'slug', 'is_home'),
            // Its short link and QR code, unless it's private
            'share' => Share::for($document),
            'linkedProjects' => $document->linkedProjects->sortBy('name')->values()->map->only('id', 'name', 'slug'),
            'linkableProjects' => $request->user()->projects()->listed()->whereKeyNot($document->project_id)
                ->whereNotIn('id', $document->linkedProjects->pluck('id'))->orderBy('name')->get(['id', 'name', 'slug']),
            'versions' => $versions->map(fn (DocumentVersion $v) => [
                'external_url' => $v->external_url, 'number' => $v->number, 'kind' => $v->kind, 'size_bytes' => $v->size_bytes, 'created_at' => $v->created_at,
                'original_filename' => $v->original_filename,
                'url' => $document->readableUrl($v->number) ?? $v->url(),
                'merged_from' => $v->provenance['source_document_id'] ?? null ? ['title' => Document::find($v->provenance['source_document_id'])?->title, 'version' => $v->provenance['source_version'] ?? null] : null,
            ])->values(),
            'usedIn' => [
                ...CollectionItem::where('document_id', $document->id)->with('collection:id,title')->get()
                    ->map(fn ($item) => ['type' => 'Collection', 'title' => $item->collection->title, 'href' => '/collections/'.$item->collection_id.'/edit']),
                ...$reports->map(fn (Report $report) => ['type' => 'Report', 'title' => $report->title, 'href' => '/reports/'.$report->id.'/edit'])->values(),
            ],
            'mergeSources' => Document::where('project_id', $document->project_id)->where('driver', 'cloud')->whereKeyNot($document->id)
                // A password-protected source can't be merged, so it isn't offered
                ->whereNull('merged_into_id')->whereNull('merged_at')->whereNull('password_hash')->orderBy('title')->get(['id', 'title', 'slug'])
                ->map(fn ($d) => ['id' => $d->id, 'title' => $d->title ?? $d->slug]),
        ]);
    }

    /** List this artifact in another project, which may then use it in reports and collections. */
    public function link(Request $request, int $document): RedirectResponse
    {
        $document = $this->documents($request)->findOrFail($document);
        $project = $request->user()->projects()->listed()->findOrFail($request->validate(['project_id' => ['required', 'integer']])['project_id']);
        if ($project->id !== $document->project_id) {
            $document->linkedProjects()->syncWithoutDetaching([$project->id]);
        }

        return back();
    }

    public function unlink(Request $request, int $document, int $project): RedirectResponse
    {
        $this->documents($request)->findOrFail($document)->linkedProjects()->detach($project);

        return back();
    }

    public function store(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);
        abort_unless($request->user()->canPublish(), 402, 'Publishing artifacts is part of a paid plan.');
        app(ArtifactPublisher::class)->publish($project, $request->validate(ArtifactPublisher::rules()));

        return redirect()->route('published')->with('status', 'artifact-published');
    }

    public function update(Request $request, int $document): RedirectResponse
    {
        $validated = $request->validate([
            'readable' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::in(['public', 'unlisted', 'private'])],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:200'],
        ]);
        DB::transaction(function () use ($request, $document, $validated) {
            $document = $this->lockedDocument($request, $document);
            if (($document->driver ?? 'cloud') !== 'cloud' && array_intersect(array_keys($validated), ['readable', 'visibility', 'password'])) {
                throw ValidationException::withMessages(['visibility' => 'Access is managed at the publishing destination.']);
            }
            if (array_key_exists('readable', $validated) || array_key_exists('visibility', $validated)) {
                $validated['visibility'] = Document::visibilityFor($document->visibility, $validated);
                unset($validated['readable']);
            }
            if (array_key_exists('password', $validated)) {
                // Legacy files may already be public on an independent content host.
                // Reject the change rather than claiming those URLs are protected.
                if (filled($validated['password']) && $document->hasUnprotectedLegacyContent()) {
                    throw ValidationException::withMessages(['password' => 'This artifact has legacy versions outside managed private storage. Remove those versions before adding a password.']);
                }
                $validated['password_hash'] = filled($validated['password']) ? Hash::make($validated['password']) : null;
                unset($validated['password']);
            }
            $document->update($validated);
        });

        return back()->with('status', 'document-updated');
    }

    public function merge(Request $request, int $document): RedirectResponse
    {
        $target = $this->documents($request)->where('driver', 'cloud')->findOrFail($document);
        $validated = $request->validate(['source_id' => ['required', 'integer']]);
        $source = $this->documents($request)->where('driver', 'cloud')->findOrFail($validated['source_id']);
        app(ArtifactPublisher::class)->merge($target, $source);

        return back()->with('status', 'artifact-merged');
    }

    public function destroy(Request $request, int $document): RedirectResponse
    {
        // To the Trash, files and all, until restored or deleted forever there
        DB::transaction(fn () => $this->lockedDocument($request, $document)->delete());

        // From the artifact's own page there is nothing to go back to
        return $request->query('then') === 'list'
            ? redirect()->route('published')->with('status', 'document-deleted')
            : back()->with('status', 'document-deleted');
    }

    /** Deleting the newest version brings back the previous publication. The deleted one waits in the Trash. */
    public function destroyVersion(Request $request, int $document, int $number): RedirectResponse
    {
        // To the Trash with its files. The only version takes the artifact with it.
        DB::transaction(function () use ($request, $document, $number) {
            $document = $this->lockedDocument($request, $document);
            $version = $document->findVersion($number) ?? abort(404);
            if ($document->versions()->count() <= 1) {
                $document->delete();
            } else {
                $version->delete();
                $newest = $document->versions()->first();
                $document->update($newest->only(ArtifactPublisher::VERSION_FIELDS));
            }
        });

        if ($request->query('then') === 'list' && ! $this->documents($request)->whereKey($document)->exists()) {
            return redirect()->route('published')->with('status', 'document-deleted');
        }

        return back()->with('status', 'version-deleted');
    }

    /** Call inside a transaction; publishing and merging take this same lock. */
    private function lockedDocument(Request $request, int $id): Document
    {
        $projectId = $this->documents($request)->findOrFail($id)->project_id;
        Project::whereKey($projectId)->lockForUpdate()->firstOrFail();

        return $this->documents($request)->findOrFail($id);
    }

    private function documents(Request $request)
    {
        return Document::whereIn('project_id', $request->user()->projects()->select('id'));
    }
}
