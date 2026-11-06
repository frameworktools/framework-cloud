<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use App\Support\ArtifactPublisher;
use App\Support\TrashPurge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deleted projects, artifacts, versions, reports, and skills, by you or a
 * connected agent, until you restore them. Nothing is removed until then: a
 * deleted project's artifacts, reports, collections, and tokens stop working,
 * and work again when it is restored. Artifacts and versions keep their files,
 * which count toward storage until deleted forever.
 */
class TrashController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        // What has outlived the Trash goes now, even where no scheduler runs
        TrashPurge::run($user->id);
        $projectIds = Project::withTrashed()->where('user_id', $user->id)->pluck('id');

        $items = collect()
            ->concat(Project::onlyTrashed()->where('user_id', $user->id)->where('is_home', false)->get()
                ->map(fn (Project $p) => ['type' => 'project', 'id' => $p->id, 'name' => $p->name, 'detail' => $p->slug, 'deleted_at' => $p->deleted_at]))
            ->concat(Report::onlyTrashed()->whereIn('project_id', $projectIds)->with(['project' => fn ($q) => $q->withTrashed()])->get()
                ->map(fn (Report $r) => ['type' => 'report', 'id' => $r->id, 'name' => $r->title, 'detail' => $r->project?->is_home ? null : $r->project?->name, 'deleted_at' => $r->deleted_at]))
            ->concat(Document::onlyTrashed()->whereIn('project_id', $projectIds)->with(['project' => fn ($q) => $q->withTrashed()])->get()
                ->map(fn (Document $d) => ['type' => 'artifact', 'id' => $d->id, 'name' => $d->title ?? $d->slug, 'detail' => $d->project?->is_home ? null : $d->project?->name, 'deleted_at' => $d->deleted_at, 'size_bytes' => $d->size_bytes]))
            ->concat(DocumentVersion::onlyTrashed()->whereHas('document', fn ($q) => $q->whereIn('project_id', $projectIds))->with('document')->get()
                ->map(fn (DocumentVersion $v) => ['type' => 'version', 'id' => $v->id, 'name' => ($v->document->title ?? $v->document->slug), 'detail' => 'Version '.$v->number, 'deleted_at' => $v->deleted_at, 'size_bytes' => $v->size_bytes]))
            ->concat(Skill::onlyTrashed()->where('user_id', $user->id)->get()
                ->map(fn (Skill $s) => ['type' => 'skill', 'id' => $s->id, 'name' => $s->name, 'detail' => $s->key, 'deleted_at' => $s->deleted_at]))
            ->sortByDesc('deleted_at')->values()
            ->map(fn (array $item) => $item + ['deleted' => $item['deleted_at']->diffForHumans(), 'deleted_iso' => $item['deleted_at']->toIso8601String()]);

        return Inertia::render('Trash', ['items' => $items, 'keepDays' => TrashPurge::days()]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        $user = $request->user();

        match ($type) {
            'project' => Project::onlyTrashed()->where('user_id', $user->id)->findOrFail($id)->restore(),
            'skill' => Skill::onlyTrashed()->where('user_id', $user->id)->findOrFail($id)->restore(),
            'report' => $this->restoreReport($user->id, $id),
            'artifact' => $this->restoreArtifact($user->id, $id),
            'version' => $this->restoreVersion($user->id, $id),
            default => abort(404),
        };

        return back()->with('status', 'restored');
    }

    /** Delete an artifact or version forever, with its files. Projects, reports, and skills stay restorable. */
    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        $userId = $request->user()->id;

        DB::transaction(function () use ($type, $id, $userId) {
            // The models remove the files once this commits
            if ($type === 'artifact') {
                Document::onlyTrashed()->whereHas('project', fn ($q) => $q->withTrashed()->where('user_id', $userId))->findOrFail($id)->forceDelete();

                return;
            }
            DocumentVersion::onlyTrashed()->whereHas('document', fn ($q) => $q->whereHas('project', fn ($p) => $p->withTrashed()->where('user_id', $userId)))->findOrFail($id)->forceDelete();
        });

        return back()->with('status', 'deleted-forever');
    }

    private function restoreArtifact(int $userId, int $id): void
    {
        $document = Document::onlyTrashed()->whereHas('project', fn ($q) => $q->withTrashed()->where('user_id', $userId))->findOrFail($id);
        if ($document->project()->withTrashed()->first()?->trashed()) {
            throw ValidationException::withMessages(['restore' => 'Restore its project first.']);
        }
        $document->restore();
    }

    /** A version comes back, and the artifact shows its newest version again. */
    private function restoreVersion(int $userId, int $id): void
    {
        $version = DocumentVersion::onlyTrashed()->whereHas('document', fn ($q) => $q->whereHas('project', fn ($p) => $p->withTrashed()->where('user_id', $userId)))->findOrFail($id);
        DB::transaction(function () use ($version) {
            $version->restore();
            $document = $version->document;
            $document->update($document->versions()->first()->only(ArtifactPublisher::VERSION_FIELDS));
        });
    }

    private function restoreReport(int $userId, int $id): void
    {
        $report = Report::onlyTrashed()->whereHas('project', fn ($q) => $q->withTrashed()->where('user_id', $userId))->findOrFail($id);
        if ($report->project()->withTrashed()->first()?->trashed()) {
            throw ValidationException::withMessages(['restore' => 'Restore its project first.']);
        }
        $report->restore();
    }
}
