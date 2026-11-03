<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Report;
use App\Models\ReportVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ReportViewController extends Controller
{
    public function show(string $key)
    {
        return $this->page($this->byKey($key));
    }

    public function showVersion(string $key, int $number)
    {
        return $this->page($this->byKey($key), $number);
    }

    public function preview(Request $request, int $report)
    {
        $report = Report::whereIn('project_id', $request->user()->projects()->select('id'))->with('project')->findOrFail($report);
        $version = new ReportVersion(['title' => $report->title, 'description' => $report->description, 'blocks' => $report->draft_blocks]);
        $version->created_at = $report->updated_at;

        return $this->render($report, $version, preview: true);
    }

    public function unlock(Request $request, string $key)
    {
        $report = $this->byKey($key);
        abort_if($report->visibility === 'private', 404);
        $data = $request->validate(['password' => ['required', 'string', 'max:200'], 'version' => ['sometimes', 'nullable', 'integer', 'min:1']]);
        if (! $report->password_hash || ! Hash::check($data['password'], $report->password_hash)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
        $version = isset($data['version']) ? $report->versions()->where('number', $data['version'])->firstOrFail() : null;
        $request->session()->put($report->passwordSessionKey(), $report->accessStamp());

        return redirect()->to($version ? $version->url() : $report->shareUrl());
    }

    private function byKey(string $key): Report
    {
        return Report::where('key', $key)->whereHas('project')->with('project')->firstOrFail();
    }

    private function page(Report $report, ?int $number = null)
    {
        abort_if($report->visibility === 'private' && ! $report->isOwner(), 404);
        $version = $number === null ? $report->versions()->first() : $report->versions()->where('number', $number)->first();
        abort_unless($version, 404);
        if (! $report->canRead()) {
            return response()->view('reports.password', ['report' => $report, 'number' => $number])->header('Cache-Control', 'private, no-store');
        }

        return $this->render($report, $version);
    }

    private function render(Report $report, ReportVersion $version, bool $preview = false)
    {
        $owner = $report->isOwner();
        $blocks = collect($version->blocks)->map(function ($block) use ($report, $owner, $preview) {
            if ($block['type'] === 'text') {
                return $block;
            }
            $document = Document::where('project_id', $report->project_id)->with('project.user')->find($block['document_id']);
            $number = $block['version_number'];
            // Draft previews can follow current content; published versions never can.
            $artifactVersion = $document ? ($number === null && $preview ? $document->latestVersion() : ($number === null ? null : $document->findVersion($number))) : null;
            $shared = $document && ($owner || $document->visibility === 'public'
                || ($report->visibility === 'unlisted' && $document->visibility === 'unlisted' && $block['share_unlisted']));
            $available = $artifactVersion !== null && $shared;

            return $block + ['artifact_title' => $block['artifact_title'] ?? ($document?->title ?? $document?->slug),
                'artifact_kind' => $block['artifact_kind'] ?? $artifactVersion?->kind, 'document' => $available ? $document : null, 'available' => $available,
                'url' => $available ? $artifactVersion->url() : null,
                'password_protected' => $available && $document->isPasswordProtected(),
                'can_read' => $available && $document->canRead()];
        });

        return response()->view('reports.show', ['report' => $report, 'version' => $version, 'blocks' => $blocks,
            'preview' => $preview, 'project' => $report->project,
            'versions' => $report->versions()->get()->map(fn ($v) => ['number' => $v->number, 'url' => $v->url(), 'date' => $v->created_at])])->header('Cache-Control', 'private, no-store');
    }
}
