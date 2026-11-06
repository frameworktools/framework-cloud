<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Support\ReportPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Reports over the API: an ordered page of text and published artifacts, the same reports the web
 * editor builds. Text blocks are Markdown, or sanitized HTML with "format": "html". Artifact blocks
 * name a published artifact by "artifact" (its slug) or "document_id". A user token works on any of
 * the user's projects; a project token works on its own project.
 */
class ReportsController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);

        return response()->json(['reports' => Report::where('project_id', $project->id)->orderByDesc('updated_at')->get()->map(fn (Report $r) => $this->summary($r))]);
    }

    public function show(Request $request, string $slug, string $report): JsonResponse
    {
        return response()->json($this->detail($this->report($this->project($request, $slug), $report)));
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $project = $this->project($request, $slug);
        $data = $this->input($request, $project, creating: true);
        $publisher = app(ReportPublisher::class);
        $report = $publisher->create($project, Arr::except($data, ['publish']));
        if ($request->boolean('publish')) {
            $this->mayPublish($project);
            $publisher->publish($report);
        }

        return response()->json($this->detail($report->refresh()), 201);
    }

    public function update(Request $request, string $slug, string $report): JsonResponse
    {
        $project = $this->project($request, $slug);
        $report = $this->report($project, $report);
        $publisher = app(ReportPublisher::class);
        $publisher->saveDraft($report, Arr::except($this->input($request, $project), ['publish']));
        if ($request->boolean('publish')) {
            $this->mayPublish($project);
            $publisher->publish($report);
        }

        return response()->json($this->detail($report->refresh()));
    }

    public function publish(Request $request, string $slug, string $report): JsonResponse
    {
        $project = $this->project($request, $slug);
        $report = $this->report($project, $report);
        $this->mayPublish($project);
        app(ReportPublisher::class)->publish($report, Arr::except($this->input($request, $project), ['publish']));

        return response()->json($this->detail($report->refresh()));
    }

    /** DELETE: move a report to the Trash. Its links stop opening until it is restored. */
    public function destroy(Request $request, string $slug, string $report): JsonResponse
    {
        // Deleting takes the account's own token, never a project token from a project's .env
        abort_unless($request->user() instanceof User, 403, 'Deleting a report needs your account token.');
        $project = $this->project($request, $slug);
        $report = $this->report($project, $report);
        $report->delete();

        return response()->json(['deleted' => $report->slug, 'restore' => route('trash')]);
    }

    /** The project, for a user token on one of its projects or a project token on its own. */
    private function project(Request $request, string $slug): Project
    {
        $actor = $request->user();
        if ($actor instanceof Project) {
            abort_unless($actor->slug === $slug && $actor->tokenCan(Project::PULL_ABILITY), 403);

            return $actor;
        }
        abort_unless($actor instanceof User, 403);

        return $actor->projects()->where('slug', $slug)->firstOrFail();
    }

    private function report(Project $project, string $report): Report
    {
        return Report::where('project_id', $project->id)
            ->where(fn ($query) => $query->where('slug', $report)->orWhere('key', $report))
            ->firstOrFail();
    }

    private function mayPublish(Project $project): void
    {
        abort_unless($project->user?->canPublish(), 402, 'Publishing reports is part of a paid plan.');
    }

    /**
     * The request as ReportPublisher takes it. "markdown" alone makes a one-block report; blocks
     * without an id get one; an artifact block may name its artifact by slug.
     */
    private function input(Request $request, Project $project, bool $creating = false): array
    {
        $data = $request->validate(ReportPublisher::rules($creating) + [
            'markdown' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'publish' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('markdown', $data)) {
            if (array_key_exists('blocks', $data)) {
                throw ValidationException::withMessages(['markdown' => 'Send markdown or blocks, not both.']);
            }
            $data['blocks'] = [['type' => 'text', 'text' => $data['markdown'] ?? '']];
            unset($data['markdown']);
        }
        if (isset($data['blocks'])) {
            $data['blocks'] = $this->blocks($project, $data['blocks']);
        }

        return $data;
    }

    private function blocks(Project $project, array $blocks): array
    {
        $used = collect($blocks)->pluck('id')->filter(fn ($id) => is_string($id))->flip();
        $next = 1;

        return collect($blocks)->values()->map(function ($block, int $index) use ($project, $used, &$next) {
            if (! is_array($block)) {
                return $block;
            }
            if (! isset($block['id'])) {
                while ($used->has('b'.$next)) {
                    $next++;
                }
                $block['id'] = 'b'.$next++;
            }
            if (($block['type'] ?? null) === 'artifact' && ! isset($block['document_id']) && is_string($block['artifact'] ?? null)) {
                // The project's own artifact first, then one linked to it
                $document = Document::availableTo($project->id)->where('slug', $block['artifact'])
                    ->orderByRaw('case when project_id = ? then 0 else 1 end', [$project->id])->first();
                if (! $document) {
                    throw ValidationException::withMessages(["blocks.$index.artifact" => 'No published artifact in this project has the slug “'.$block['artifact'].'”.']);
                }
                $block['document_id'] = $document->id;
            }

            return Arr::except($block, ['artifact']);
        })->all();
    }

    private function summary(Report $report): array
    {
        $latest = $report->versions()->max('number');

        return [
            'id' => $report->id, 'slug' => $report->slug, 'title' => $report->title, 'description' => $report->description,
            'visibility' => $report->visibility, 'password' => $report->password_hash !== null,
            'url' => $report->shareUrl(), 'latest_version' => $latest ? (int) $latest : null,
            'version_url' => $latest ? route('reports.version', ['key' => $report->key, 'number' => $latest]) : null,
            'updated_at' => $report->updated_at?->toIso8601String(),
        ];
    }

    private function detail(Report $report): array
    {
        return $this->summary($report) + [
            'blocks' => $report->draft_blocks,
            'versions' => $report->versions()->orderBy('number')->get()->map(fn ($version) => [
                'number' => $version->number, 'title' => $version->title,
                'url' => route('reports.version', ['key' => $report->key, 'number' => $version->number]),
                'published_at' => $version->created_at?->toIso8601String(),
            ]),
        ];
    }
}
