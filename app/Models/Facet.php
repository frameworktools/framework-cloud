<?php

namespace App\Models;

use App\Models\Concerns\HasVersions;
use App\Models\Concerns\Shareable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable piece of a blueprint, stored as a partial spec layer. A base
 * facet sets the project type and its core structure; other facets add one
 * capability each (restricted data, a manuscript, renv) and stack on a base
 * in order. The framework R package merges the stack, never the cloud.
 */
class Facet extends Model
{
    use HasFactory, HasVersions, Shareable;

    public const KINDS = ['base', 'facet'];

    /** Builder sections, in the order the builder asks about them. */
    public const CATEGORIES = [
        'data' => 'Data',
        'outputs' => 'Outputs',
        'reproducibility' => 'Reproducibility',
        'ai' => 'AI assistants',
        'tooling' => 'Tools',
    ];

    protected $guarded = [];

    protected $appends = ['handle'];

    protected function versionModel(): string
    {
        return FacetVersion::class;
    }

    protected function versionContent(): array
    {
        return ['layer'];
    }

    public function isBase(): bool
    {
        return $this->kind === 'base';
    }
}
