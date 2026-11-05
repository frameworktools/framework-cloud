<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Collection;

// The project's artifacts as the report and collection editors list them
class ArtifactChoices
{
    public static function for(int $projectId): Collection
    {
        return Document::where('project_id', $projectId)->where('driver', 'cloud')->with('versions')->orderBy('title')->get()
            ->map(fn (Document $document) => [...$document->only('id', 'title', 'slug', 'kind', 'visibility'),
                'url' => $document->publicUrl(), 'password_protected' => $document->isPasswordProtected(),
                'latest_version' => $document->latestVersion()->number,
                'versions' => ($document->versions->isEmpty() ? collect([$document->latestVersion()]) : $document->versions)
                    ->map(fn ($version) => $version->only('number', 'created_at'))->values()]);
    }
}
