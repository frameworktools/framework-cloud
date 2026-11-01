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
        return [
            'layer' => 'array',
            'languages' => 'array',
            'workflows' => 'array',
            'default_workflow' => 'array',
            'requires_tools' => 'array',
            'requires' => 'array',
        ];
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
    /**
     * What the version declares it supports and needs, for resolution to
     * validate against. A field left empty means no restriction: every
     * language, or every workflow the language has.
     *
     * @return array{languages: ?list<string>, workflows: ?list<string>, default_workflow: array<string, string>, requires_tools: list<string>, requires: array<string, mixed>}
     */
    public function compatibility(): array
    {
        return [
            'languages' => $this->languages ?: null,
            'workflows' => $this->workflows ?: null,
            'default_workflow' => $this->default_workflow ?? [],
            'requires_tools' => $this->requires_tools ?? [],
            'requires' => $this->requires ?? [],
        ];
    }

    public function fragments(): Collection
    {
        return collect([$this->base])->concat($this->facetVersions);
    }
}
