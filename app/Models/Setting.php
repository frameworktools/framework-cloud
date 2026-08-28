<?php

namespace App\Models;

use App\Support\DefaultSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory;

    public const KIND_GLOBAL = 'global';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document' => 'array',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SettingRevision::class);
    }

    /**
     * Fetch (or seed) the settings document of a given kind for an owner.
     */
    public static function forOwner(Model $owner, string $kind = self::KIND_GLOBAL): self
    {
        return static::firstOrCreate(
            [
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'kind' => $kind,
            ],
            [
                'document' => DefaultSettings::document(),
                'schema_version' => DefaultSettings::SCHEMA_VERSION,
                'revision' => 0,
            ],
        );
    }

    /**
     * Replace the document, bump the revision, and record history.
     */
    public function replaceDocument(array $document, ?string $schemaVersion = null, ?array $client = null): self
    {
        $this->revision++;
        $this->document = $document;

        if ($schemaVersion !== null) {
            $this->schema_version = $schemaVersion;
        }

        $this->save();

        $this->revisions()->create([
            'revision' => $this->revision,
            'document' => $document,
            'hash' => hash('sha256', json_encode($document)),
            'client' => $client,
            'created_at' => now(),
        ]);

        // Retention: keep the newest N revisions
        $kept = (int) config('framework.setting_revisions_kept', 200);
        $cutoff = $this->revision - $kept;
        if ($cutoff > 0) {
            $this->revisions()->where('revision', '<=', $cutoff)->delete();
        }

        return $this;
    }
}
