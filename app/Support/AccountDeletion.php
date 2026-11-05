<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\InstanceSetting;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Removes an account and everything it owns. Foreign keys cascade most rows.
 * This removes the rest: polymorphic rows, tokens, sessions, and stored files.
 *
 * Ledger entries are append-only. On Postgres a trigger refuses to change
 * them. It allows deletes only when this transaction sets the account
 * deletion flag and the entry's project row is already gone.
 */
class AccountDeletion
{
    public const LEDGER_FLAG = 'framework.account_deletion';

    public function delete(User $user): void
    {
        $versions = DB::transaction(function () use ($user) {
            if (DB::getDriverName() === 'pgsql') {
                // Transaction-local: the flag ends with this transaction
                DB::select('select set_config(?, ?, true)', [self::LEDGER_FLAG, 'on']);
            }
            $projectIds = Project::withTrashed()->where('user_id', $user->id)->pluck('id');
            $versions = $this->versions($projectIds);

            PersonalAccessToken::query()
                ->where(fn ($q) => $q->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id))
                ->orWhere(fn ($q) => $q->where('tokenable_type', (new Project)->getMorphClass())->whereIn('tokenable_id', $projectIds))
                ->delete();
            foreach ([$user->getMorphClass() => [$user->id], (new Project)->getMorphClass() => $projectIds] as $type => $ids) {
                Setting::where('owner_type', $type)->whereIn('owner_id', $ids)->delete();
            }
            InstanceSetting::where('updated_by', $user->id)->update(['updated_by' => null]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            // Cascades to projects, then documents, ledger entries, collections, and reports
            $user->delete();

            return $versions;
        });

        $versions->each->deleteFiles();
    }

    /** Every stored version, including documents saved before version rows. */
    private function versions(Collection $projectIds): Collection
    {
        return Document::whereIn('project_id', $projectIds)->with('versions')->get()
            ->flatMap(fn (Document $document) => $document->versions->isEmpty()
                ? [$document->latestVersion()]
                : $document->versions)
            ->filter(fn (DocumentVersion $version) => filled($version->disk_path));
    }
}
