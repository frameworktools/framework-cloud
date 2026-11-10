<?php

namespace App\Models;

use App\Support\ArtifactContentAccess;
use App\Support\ChartPage;
use App\Support\ContentHost;
use App\Support\ContentWorker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One publish of a document. Versions are kept until their owner deletes
 * them, so readers can open earlier versions at /d/{key}/versions/{number}.
 */
class DocumentVersion extends Model
{
    // A deleted version keeps its files in the Trash until restored or deleted forever
    use SoftDeletes;

    /** Deleted forever, by any path: its files go too, once the deletion commits. */
    protected static function booted(): void
    {
        static::forceDeleted(fn (DocumentVersion $version) => DB::afterCommit(fn () => $version->deleteFiles()));
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'provenance' => 'array'];
    }

    public function disk(): string
    {
        return $this->storage_disk ?? config('framework.documents_disk');
    }

    public function downloadUrl(): string
    {
        if (($this->driver ?? 'cloud') !== 'cloud') {
            return $this->external_url ?? $this->document->publicUrl();
        }

        return route('documents.version.download', ['key' => $this->document->key, 'number' => $this->number]);
    }

    public function usesExternalUrl(): bool
    {
        return ! $this->document->isPasswordProtected()
            && $this->storage_disk === null && filled(config('framework.documents_base_url'));
    }

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
        if (($this->driver ?? 'cloud') !== 'cloud') {
            return $this->external_url ?? $this->document->publicUrl();
        }

        return route('documents.version', ['key' => $this->document->key, 'number' => $this->number]);
    }

    public function rawUrl(): string
    {
        return route('documents.version.raw', ['key' => $this->document->key, 'number' => $this->number]);
    }

    /** Where the content loads from: the documents domain for old files, the content host when set, else the app. */
    public function contentUrl(): string
    {
        if (($this->driver ?? 'cloud') !== 'cloud') {
            return $this->external_url ?? $this->document->publicUrl();
        }
        $base = config('framework.documents_base_url');

        if ($this->usesExternalUrl()) {
            return rtrim($base, '/').'/'.$this->entryPath();
        }
        // Served by the content Worker straight from the bucket
        if (ContentWorker::enabled($this)) {
            abort_unless($this->document->canRead(), 403, 'Enter the artifact password on its page.');

            return ContentWorker::url($this);
        }
        // Its own host: a signed link for every file, since the content host has no session
        if (ContentHost::enabled()) {
            return ContentHost::route('documents.content', [
                'key' => $this->document->key, 'number' => $this->number,
                'access' => ArtifactContentAccess::issue($this), 'path' => $this->isBundle() ? $this->entrypoint : $this->contentFilename(),
            ]);
        }

        if ($this->isBundle()) {
            return route('documents.content', [
                'key' => $this->document->key, 'number' => $this->number,
                'access' => ArtifactContentAccess::issue($this), 'path' => $this->entrypoint,
            ]);
        }

        return $this->rawUrl();
    }

    /** The sandboxed page that draws a Vega-Lite chart. */
    public function chartUrl(): string
    {
        // The page stored at publish time, through the Worker
        if (ContentWorker::enabled($this) && ChartPage::ensure($this)) {
            abort_unless($this->document->canRead(), 403, 'Enter the artifact password on its page.');

            return ContentWorker::chart($this, ChartPage::path($this));
        }

        return ContentHost::route('documents.chart', [
            'key' => $this->document->key, 'number' => $this->number,
            'access' => ArtifactContentAccess::issue($this),
        ]);
    }

    /** The name a single file is served under on the content host: its own, made safe for a path. */
    public function contentFilename(): string
    {
        $name = basename(str_replace('\\', '/', (string) ($this->original_filename ?: 'content')));

        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'content';
    }

    /** A typed table built from a CSV or TSV when it was published, stored beside the file. */
    public const REPRESENTATION = 'representation.json';

    public function representationPath(): ?string
    {
        return $this->isBundle() || ! $this->disk_path || ! str_ends_with($this->disk_path, '/content') ? null : dirname($this->disk_path).'/'.self::REPRESENTATION;
    }

    public function deleteFiles(): void
    {
        if (($this->driver ?? 'cloud') !== 'cloud') {
            return;
        }
        $disk = Storage::disk($this->disk());

        if ($this->isBundle()) {
            $disk->deleteDirectory($this->disk_path);

            return;
        }
        $disk->delete(array_filter([$this->disk_path, $this->representationPath(), ChartPage::path($this)]));
    }
}
