<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A published artifact (rendered notebook, report). The `key` is the
 * capability in its private URL, /d/{key}. Each publish adds a version; the
 * row mirrors the newest version's storage fields.
 *
 * Visibility: "unlisted" documents are reachable only through the key.
 * "public" documents also get a readable URL,
 * /@{handle}/{project}/{slug}, once their owner has a handle.
 */
class Document extends Model
{
    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'merged_at' => 'datetime'];
    }

    public function isPasswordProtected(): bool
    {
        return filled($this->password_hash);
    }

    public function hasUnprotectedLegacyContent(): bool
    {
        // Only the standard private local disk is a known-safe legacy source.
        // A public disk can expose files even without a content-domain setting.
        return (filled(config('framework.documents_base_url')) || config('framework.documents_disk') !== 'local')
            && ($this->storage_disk === null || $this->versions()->whereNull('storage_disk')->exists());
    }

    public function passwordSessionKey(): string
    {
        return 'artifact_access.'.$this->id;
    }

    public function canRead(?Request $request = null): bool
    {
        $request ??= request();
        if ($this->visibility === 'private') {
            return $request->user() instanceof User && $request->user()->id === $this->project->user_id;
        }

        return ! $this->isPasswordProtected()
            || ($request->user() instanceof User && $request->user()->id === $this->project->user_id)
            || ($request->hasSession() && hash_equals($this->password_hash, (string) $request->session()->get($this->passwordSessionKey(), '')));
    }

    /**
     * The visibility a request asks for. An explicit `visibility` wins.
     * The older `readable` flag only switches between public and unlisted,
     * so it never opens a private artifact.
     */
    public static function visibilityFor(?string $current, array $input): string
    {
        if (filled($input['visibility'] ?? null)) {
            return $input['visibility'];
        }
        if (array_key_exists('readable', $input) && $current !== 'private') {
            return $input['readable'] ? 'public' : 'unlisted';
        }

        return $current ?? 'unlisted';
    }

    public function preserveImplicitVersion(): void
    {
        if (! $this->versions()->exists()) {
            $version = $this->implicitVersion();
            $version->document_id = $this->id;
            $version->save();
        }
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('number');
    }

    /**
     * The newest version. A document saved without version rows (older data,
     * tests) reads as version 1 built from its own storage fields.
     */
    public function latestVersion(): DocumentVersion
    {
        return $this->versions()->first() ?? $this->implicitVersion();
    }

    public function findVersion(int $number): ?DocumentVersion
    {
        return $this->versions()->where('number', $number)->first()
            ?? ($number === 1 && ! $this->versions()->exists() ? $this->implicitVersion() : null);
    }

    private function implicitVersion(): DocumentVersion
    {
        $version = new DocumentVersion([
            'number' => 1,
            'kind' => $this->kind ?? 'html',
            'original_filename' => $this->original_filename,
            'manifest' => $this->manifest,
            'storage_disk' => $this->storage_disk,
            'source_path' => $this->source_path,
            'disk_path' => $this->disk_path,
            'entrypoint' => $this->entrypoint,
            'content_type' => $this->content_type,
            'size_bytes' => $this->size_bytes,
        ]);
        $version->setRelation('document', $this);
        $version->created_at = $this->updated_at;

        return $version;
    }

    public function isReadable(): bool
    {
        return $this->visibility === 'public' && ($this->driver ?? 'cloud') === 'cloud';
    }

    /** The readable URL, when the document is public and its owner has a handle. */
    public function readableUrl(?int $version = null): ?string
    {
        $handle = $this->project->user->handle;

        if (! $this->isReadable() || ! $handle) {
            return null;
        }

        $params = ['handle' => $handle, 'project' => $this->project->slug, 'slug' => $this->slug];

        return $version
            ? route('documents.readable.version', $params + ['number' => $version])
            : route('documents.readable', $params);
    }

    /** The link to share: readable when available, else the private link. */
    public function shareUrl(): string
    {
        return ($this->driver ?? 'cloud') !== 'cloud' ? ($this->external_url ?? $this->publicUrl()) : ($this->readableUrl() ?? $this->publicUrl());
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

    /** Where the newest version's content loads from. */
    public function contentUrl(): string
    {
        return $this->latestVersion()->contentUrl();
    }
}
