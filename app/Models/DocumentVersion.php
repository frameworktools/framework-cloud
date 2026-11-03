<?php

namespace App\Models;

use App\Support\ArtifactContentAccess;
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

        if ($this->usesExternalUrl()) {
            return rtrim($base, '/').'/'.$this->entryPath();
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
        return route('documents.chart', [
            'key' => $this->document->key, 'number' => $this->number,
            'access' => ArtifactContentAccess::issue($this),
        ]);
    }

    /** A typed table built from a CSV or TSV when it was published, stored beside the file. */
    public const REPRESENTATION = 'representation.json';

    public function representationPath(): ?string
    {
        return $this->isBundle() || ! $this->disk_path || ! str_ends_with($this->disk_path, '/content') ? null : dirname($this->disk_path).'/'.self::REPRESENTATION;
    }

    public function deleteFiles(): void
    {
        $disk = Storage::disk($this->disk());

        if ($this->isBundle()) {
            $disk->deleteDirectory($this->disk_path);

            return;
        }
        $disk->delete(array_filter([$this->disk_path, $this->representationPath()]));
    }
}
