<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Immutable, numbered versions. Projects and blueprints pin a version, so a
 * published version never changes; saving different content adds the next
 * one. Saving identical content returns the current version unchanged.
 *
 * Using classes define versionModel() and versionContent(), which names the
 * columns that make up a version's content.
 */
trait HasVersions
{
    abstract protected function versionModel(): string;

    /** @return list<string> */
    abstract protected function versionContent(): array;

    public function versions(): HasMany
    {
        return $this->hasMany($this->versionModel())->orderBy('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne($this->versionModel())->latestOfMany('version');
    }

    /**
     * @param  array<string, mixed>  $attributes  content columns plus optional notes
     */
    public function publishVersion(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $latest = $this->versions()->reorder()->orderByDesc('version')->lockForUpdate()->first();
            $content = array_intersect_key($attributes, array_flip($this->versionContent()));

            if ($latest && self::canonical(array_intersect_key($this->versionSnapshot($latest), $content)) === self::canonical($content)) {
                return $latest;
            }

            $version = $this->storeVersion($attributes + ['version' => ($latest?->version ?? 0) + 1]);
            $this->touch();
            $this->unsetRelation('latestVersion');

            return $version;
        });
    }

    /** A version's content, keyed like versionContent(). */
    protected function versionSnapshot(Model $version): array
    {
        return $version->only($this->versionContent());
    }

    protected function storeVersion(array $attributes): Model
    {
        return $this->versions()->create($attributes);
    }

    /** Key-order-independent JSON, so reordered maps compare equal. */
    private static function canonical(mixed $value): string
    {
        $sort = function ($v) use (&$sort) {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($sort, $v);
        };

        return json_encode($sort($value));
    }
}
