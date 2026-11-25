<?php

namespace App\Support;

use App\Models\CollectionItem;
use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flattening keeps an artifact's newest version as version 1 and deletes the
 * rest for good, files and all, Trash included. Numbers are never reused: the
 * next publish continues after the highest old number, and links to an old
 * number go to the latest version. Collections and reports pinned to any
 * version now pin version 1, the content they last could show.
 */
class ArtifactFlattener
{
    /** @return array{removed: int} */
    public function flatten(Document $document): array
    {
        if (($document->driver ?? 'cloud') !== 'cloud') {
            throw ValidationException::withMessages(['artifact' => 'This artifact is published elsewhere. Its versions live there.']);
        }
        if ($document->merged_into_id || $document->merged_at) {
            throw ValidationException::withMessages(['artifact' => 'This artifact has been merged. Flatten the destination artifact instead.']);
        }

        return DB::transaction(function () use ($document) {
            // Publishing and merging take this same lock
            Project::whereKey($document->project_id)->lockForUpdate()->firstOrFail();
            $document = Document::lockForUpdate()->findOrFail($document->id);
            $versions = $document->versions()->withTrashed()->get();
            $keep = $versions->first(fn ($version) => $version->deleted_at === null);
            if ($keep === null) {
                return ['removed' => 0];
            }
            $highest = (int) $versions->max('number');
            $removed = 0;
            foreach ($versions as $version) {
                if ($version->isNot($keep)) {
                    // The model removes the files once this commits
                    $version->forceDelete();
                    $removed++;
                }
            }
            $keep->forceFill(['number' => 1])->save();
            $document->forceFill([
                'next_version_number' => max((int) $document->next_version_number, $highest + 1, 2),
                'flattened_at' => now(),
            ])->save();
            $this->repin($document);

            return ['removed' => $removed];
        });
    }

    /** Every pin on this artifact, in collections and in report drafts and versions, now names version 1. */
    private function repin(Document $document): void
    {
        CollectionItem::where('document_id', $document->id)->whereNotNull('version_number')->update(['version_number' => 1]);
        $repin = function (?array $blocks) use ($document): ?array {
            if ($blocks === null) {
                return null;
            }
            foreach ($blocks as &$block) {
                if (($block['type'] ?? null) !== 'text' && (int) ($block['document_id'] ?? 0) === $document->id && ($block['version_number'] ?? null) !== null) {
                    $block['version_number'] = 1;
                }
            }

            return $blocks;
        };
        // A report anywhere in the account can show the artifact: home reports use any of them
        $projects = Project::withTrashed()->where('user_id', Project::withTrashed()->whereKey($document->project_id)->value('user_id'))->select('id');
        $reports = Report::withTrashed()->whereIn('project_id', $projects)->get();
        foreach ($reports as $report) {
            $blocks = $repin($report->draft_blocks);
            if ($blocks !== $report->draft_blocks) {
                $report->forceFill(['draft_blocks' => $blocks])->save();
            }
            foreach (ReportVersion::where('report_id', $report->id)->get() as $version) {
                $blocks = $repin($version->blocks);
                if ($blocks !== $version->blocks) {
                    $version->forceFill(['blocks' => $blocks])->save();
                }
            }
        }
    }
}
