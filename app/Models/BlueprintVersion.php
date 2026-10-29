<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class BlueprintVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['layer' => 'array'];
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(FacetVersion::class, 'base_version_id');
    }

    public function facetVersions(): BelongsToMany
    {
        return $this->belongsToMany(FacetVersion::class, 'blueprint_version_facets')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * The blueprint's fragments in merge order: the base, then each facet.
     *
     * @return Collection<int, FacetVersion>
     */
    public function fragments(): Collection
    {
        return collect([$this->base])->concat($this->facetVersions);
    }
}
