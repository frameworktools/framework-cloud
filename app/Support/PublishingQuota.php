<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PublishingQuota
{
    public static function limit(): int
    {
        return (int) (config('framework.publish_storage_limit_bytes') ?? (config('framework.mode') === 'hosted' ? 1073741824 : 0));
    }

    public static function used(User $user): int
    {
        $ids = Document::where('driver', 'cloud')->whereIn('project_id', $user->projects()->select('id'))->select('id');

        return (int) DocumentVersion::whereIn('document_id', $ids)->sum('size_bytes')
            + (int) Document::where('driver', 'cloud')->whereIn('project_id', $user->projects()->select('id'))->doesntHave('versions')->sum('size_bytes');
    }

    /** Call inside the publishing transaction, before the project lock. Versions share the account budget. */
    public static function check(Project $project, int $bytes): void
    {
        $limit = self::limit();
        if ($limit <= 0) {
            return;
        }
        $user = User::whereKey($project->user_id)->lockForUpdate()->firstOrFail();
        if (self::used($user) + $bytes > $limit) {
            throw ValidationException::withMessages(['file' => 'Artifact storage limit reached. Remove older versions or publish to another destination.']);
        }
    }
}
