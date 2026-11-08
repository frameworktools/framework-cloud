<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Support\ArtifactChoices;
use App\Support\ReportPublisher;
use App\Support\ReportText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function indexForSlug(Request $request, string $slug)
    {
        return $this->index($request, $request->user()->projects()->where('slug', $slug)->firstOrFail()->id);
    }

    public function index(Request $request, ?int $project = null)
    {
        $project = $project === null ? null : $request->user()->projects()->findOrFail($project);

        return Inertia::render('Reports/Index', [
            'project' => $project?->only('id', 'name', 'slug'),
            'projects' => $request->user()->projects()->listed()->orderBy('name')->get(['id', 'name', 'slug']),
            'canPublish' => $request->user()->canPublish(),
            'reports' => Report::whereIn('project_id', $request->user()->projects()->select('id'))->when($project, fn ($query) => $query->where('project_id', $project->id))->with(['versions', 'project:id,name,slug,is_home'])->latest('updated_at')->get()->map(fn ($report) => $this->data($report) + ['project' => $report->project->only('id', 'name', 'slug', 'is_home')]),
        ]);
    }

    /** POST /projects/{project}/reports, or POST /reports for a report with no project */
    public function store(Request $request, ?int $project = null)
    {
        $project = $project === null ? $request->user()->artifactHome() : $request->user()->projects()->findOrFail($project);
        $report = app(ReportPublisher::class)->create($project, $request->validate(ReportPublisher::rules(true)));

        return redirect()->route('reports.edit', ['report' => $report->id]);
    }

    public function edit(Request $request, int $report)
    {
        $report = $this->owned($request, $report)->load(['project', 'versions']);
        $artifacts = ArtifactChoices::for($report->project_id);

        return Inertia::render('Reports/Edit', [
            'project' => $report->project->only('id', 'name', 'slug', 'is_home'),
            'report' => $this->data($report), 'artifacts' => $artifacts,
            'canPublish' => $request->user()->canPublish(),
        ]);
    }

    public function update(Request $request, int $report)
    {
        $report = $this->owned($request, $report);
        app(ReportPublisher::class)->saveDraft($report, $request->validate(ReportPublisher::rules()));

        return back()->with('status', 'report-saved');
    }

    public function publish(Request $request, int $report)
    {
        $report = $this->owned($request, $report);
        abort_unless($request->user()->canPublish(), 402, 'Publishing reports is part of a paid plan.');
        app(ReportPublisher::class)->publish($report, $request->validate(ReportPublisher::rules()));

        return back()->with('status', 'report-published');
    }

    public function destroy(Request $request, int $report)
    {
        $report = $this->owned($request, $report);
        $project = $report->project_id;
        DB::transaction(function () use ($report) {
            Project::whereKey($report->project_id)->lockForUpdate()->firstOrFail();
            $report->refresh()->delete();
        });

        return redirect()->route('reports.index', ['project' => $project]);
    }

    private function owned(Request $request, int $id): Report
    {
        return Report::whereIn('project_id', $request->user()->projects()->select('id'))->findOrFail($id);
    }

    private function data(Report $report): array
    {
        return [...$report->only('id', 'project_id', 'title', 'description', 'slug', 'key', 'visibility', 'draft_blocks', 'next_version_number'),
            'draft_blocks' => collect($report->draft_blocks)->map(fn ($block) => $block['type'] === 'text' ? [...$block, 'text' => ReportText::html($block), 'format' => 'html'] : $block)->all(),
            'url' => $report->shareUrl(), 'password_protected' => (bool) $report->password_hash,
            'versions' => $report->versions->map(fn (ReportVersion $version) => ['number' => $version->number, 'title' => $version->title,
                'description' => $version->description, 'url' => $version->url(), 'created_at' => $version->created_at])];
    }
}
