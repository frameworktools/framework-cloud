<?php

namespace App\Models;

use App\Models\Concerns\HasVersions;
use App\Models\Concerns\Shareable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A typed piece of a blueprint, stored as a partial spec layer
 * (docs/plans/2026-10-31-blueprints-structures-settings.md):
 *
 * - structure: the project type's folders and core files (the old "base")
 * - tool: an optional tool that only adds files, folders, and packages
 * - package_set: a named list of packages for one language
 * - skill_set: a named group of skills, with the packages they need
 * - setting: a layer the cloud adds for a setting, such as sensitive data
 * - legacy, retired: old facets, kept so pinned blueprint versions resolve
 *
 * A blueprint stacks one structure and any tools and sets, in order. The
 * framework R package merges the stack, never the cloud.
 */
class Facet extends Model
{
    use HasFactory, HasVersions, Shareable;

    public const KINDS = ['structure', 'tool', 'package_set', 'skill_set', 'setting', 'legacy', 'retired'];

    /** Kinds a blueprint can add on top of its structure. */
    public const PIECES = ['tool', 'package_set', 'skill_set'];

    /** Groups for tools in the builder. Goes away with the Facets page. */
    public const CATEGORIES = [
        'outputs' => 'Outputs',
        'reproducibility' => 'Reproducibility',
        'packages' => 'Packages',
        'skills' => 'Skills',
    ];

    /** Kinds people can make and share. Framework writes tools and settings. */
    public const AUTHORED = ['structure', 'package_set', 'skill_set'];

    protected $guarded = [];

    protected $appends = ['handle'];

    protected function versionModel(): string
    {
        return FacetVersion::class;
    }

    protected function versionContent(): array
    {
        return ['layer', 'language_layers'];
    }

    protected function casts(): array
    {
        return ['language' => 'array', 'requires' => 'array', 'conflicts' => 'array'];
    }

    public function isStructure(): bool
    {
        return $this->kind === 'structure';
    }

    /** The kind the layers envelope labels this piece with. Clients know only base and facet. */
    public function layerKind(): string
    {
        return $this->isStructure() ? 'base' : 'facet';
    }
}
