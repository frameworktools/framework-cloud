<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deleted projects, reports, and skills, by you or a connected agent, until
 * you restore them. A deleted project's artifacts, reports, and collections
 * stop opening, and open again when it is restored.
 */
class TrashController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $projectIds = Project::withTrashed()->where('user_id', $user->id)->pluck('id');

        $items = collect()
            ->concat(Project::onlyTrashed()->where('user_id', $user->id)->where('is_home', false)->get()
                ->map(fn (Project $p) => ['type' => 'project', 'id' => $p->id, 'name' => $p->name, 'detail' => $p->slug, 'deleted_at' => $p->deleted_at]))
            ->concat(Report::onlyTrashed()->whereIn('project_id', $projectIds)->with(['project' => fn ($q) => $q->withTrashed()])->get()
                ->map(fn (Report $r) => ['type' => 'report', 'id' => $r->id, 'name' => $r->title, 'detail' => $r->project?->is_home ? null : $r->project?->name, 'deleted_at' => $r->deleted_at]))
            ->concat(Skill::onlyTrashed()->where('user_id', $user->id)->get()
                ->map(fn (Skill $s) => ['type' => 'skill', 'id' => $s->id, 'name' => $s->name, 'detail' => $s->key, 'deleted_at' => $s->deleted_at]))
            ->sortByDesc('deleted_at')->values()
            ->map(fn (array $item) => $item + ['deleted' => $item['deleted_at']->diffForHumans(), 'deleted_iso' => $item['deleted_at']->toIso8601String()]);

        return Inertia::render('Trash', ['items' => $items]);
    }

    public function restore(Request $request, string $type, int $id): RedirectResponse
    {
        $user = $request->user();

        match ($type) {
            'project' => Project::onlyTrashed()->where('user_id', $user->id)->findOrFail($id)->restore(),
            'skill' => Skill::onlyTrashed()->where('user_id', $user->id)->findOrFail($id)->restore(),
            'report' => $this->restoreReport($user->id, $id),
            default => abort(404),
        };

        return back()->with('status', 'restored');
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
