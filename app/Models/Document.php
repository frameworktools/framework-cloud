<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A published artifact (rendered notebook, report). Stored on the local disk
 * in MVP; the storage backend can move to R2 behind the same routes without
 * clients noticing. The `key` is the capability in the public URL.
 */
class Document extends Model
{
    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function newKey(): string
    {
        return Str::lower(Str::random(24));
    }

    public function publicUrl(): string
    {
        return route('documents.show', ['key' => $this->key]);
    }

    public function rawUrl(): string
    {
        return route('documents.raw', ['key' => $this->key]);
    }

    /**
     * Where the content actually loads from: the R2 custom domain when
     * configured (separate origin — sandboxes uploaded scripts), else the
     * app's own streaming route.
     */
    public function contentUrl(): string
    {
        $base = config('framework.documents_base_url');

        if (! $base) {
            return $this->rawUrl();
        }

        $path = $this->entrypoint
            ? $this->disk_path.'/'.$this->entrypoint
            : $this->disk_path;

        return rtrim($base, '/').'/'.$path;
    }
}
