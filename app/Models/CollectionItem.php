<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['share_unlisted' => 'boolean', 'version_number' => 'integer'];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isShareable(bool $owner = false): bool
    {
        $document = $this->document;
        if (! $document || $document->project_id !== $this->collection->project_id) {
            return false;
        }
        if ($this->version_number && ! $document->findVersion($this->version_number)) {
            return false;
        }

        return $owner || $document->visibility === 'public'
            || ($this->collection->visibility === 'unlisted' && $document->visibility === 'unlisted' && $this->share_unlisted);
    }

    public function readerUrl(): string
    {
        return $this->version_number
            ? route('documents.version', ['key' => $this->document->key, 'number' => $this->version_number])
            : $this->document->shareUrl();
    }
}
