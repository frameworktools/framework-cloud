<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;

/**
 * Deletes forever what has been in the Trash longer than FW_TRASH_DAYS (30 by default; 0 keeps
 * the Trash until it is emptied by hand). Each item goes through its model, so artifact files are
 * removed (Document, DocumentVersion, and Project force-delete hooks). Runs daily on the
 * scheduler, and for one account whenever its Trash page opens.
 */
class TrashPurge
{
    public static function days(): int
    {
        return max(0, (int) config('framework.trash_days', 30));
    }

    /** @return array<string, int> how many of each were deleted forever */
    public static function run(?int $userId = null): array
    {
        $days = self::days();
        if ($days === 0) {
            return [];
        }
        $before = now()->subDays($days);
        $projects = fn () => Project::withTrashed()->when($userId, fn ($q) => $q->where('user_id', $userId))->select('id');
        $counts = [];

        $counts['versions'] = self::each(DocumentVersion::onlyTrashed()->where('deleted_at', '<', $before)
            ->whereHas('document', fn ($q) => $q->withTrashed()->whereIn('project_id', $projects())));
        $counts['artifacts'] = self::each(Document::onlyTrashed()->where('deleted_at', '<', $before)->whereIn('project_id', $projects()));
        $counts['reports'] = self::each(Report::onlyTrashed()->where('deleted_at', '<', $before)->whereIn('project_id', $projects()));
        $counts['skills'] = self::each(Skill::onlyTrashed()->where('deleted_at', '<', $before)->when($userId, fn ($q) => $q->where('user_id', $userId)));
        $counts['projects'] = self::each(Project::onlyTrashed()->where('deleted_at', '<', $before)->when($userId, fn ($q) => $q->where('user_id', $userId)), ledger: true);

        return $counts;
    }

    /** Force-delete each, one transaction apiece, so one failure leaves the rest to go. */
    private static function each($query, bool $ledger = false): int
    {
        $count = 0;
        foreach ($query->get() as $model) {
            DB::transaction(function () use ($model, $ledger) {
                // A project's ledger is append-only except when the project itself is removed for good
                if ($ledger && DB::getDriverName() === 'pgsql') {
                    DB::select('select set_config(?, ?, true)', [AccountDeletion::LEDGER_FLAG, 'on']);
                }
                $model->forceDelete();
            });
            $count++;
        }

        return $count;
    }
}
