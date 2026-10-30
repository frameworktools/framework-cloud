<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One publish of a document. Versions are kept until their owner deletes
 * them, so readers can open earlier versions at /d/{key}/versions/{number}.
 */
class DocumentVersion extends Model
{
    protected $guarded = [];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isBundle(): bool
    {
        return $this->entrypoint !== null;
    }

    /** The stored file to serve for this version's page. */
    public function entryPath(): string
    {
        return $this->isBundle() ? $this->disk_path.'/'.$this->entrypoint : $this->disk_path;
    }

    public function url(): string
    {
        return route('documents.version', ['key' => $this->document->key, 'number' => $this->number]);
    }

    public function rawUrl(): string
    {
        return route('documents.version.raw', ['key' => $this->document->key, 'number' => $this->number]);
    }

    /** Where the content loads from: the documents domain when set, else the app. */
    public function contentUrl(): string
    {
        $base = config('framework.documents_base_url');

        return $base ? rtrim($base, '/').'/'.$this->entryPath() : $this->rawUrl();
    }

    public function deleteFiles(): void
    {
        $disk = Storage::disk(config('framework.documents_disk'));

        $this->isBundle() ? $disk->deleteDirectory($this->disk_path) : $disk->delete($this->disk_path);
    }
}
