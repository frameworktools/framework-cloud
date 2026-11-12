<?php

namespace App\Models;

use App\Support\DefaultsPreview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacetVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['layer' => 'array', 'language_layers' => 'array', 'manual_packages' => 'array'];
    }

    /** The effective layer for a catalog language, or all language members for discovery. */
    public function catalogLayer(?string $language = null): array
    {
        $base = $this->layer ?? [];
        if ($language !== null) {
            return DefaultsPreview::merge([['layer' => $base], ['layer' => $this->language_layers[$language] ?? []]]);
        }
        if (! $this->language_layers) {
            return $base;
        }
        $all = [];
        foreach ($this->language_layers as $extra) {
            $all = array_replace_recursive($all, DefaultsPreview::merge([['layer' => $base], ['layer' => $extra]]));
        }

        return $all;
    }

    public function facet(): BelongsTo
    {
        return $this->belongsTo(Facet::class);
    }

    /** The fragment reference R records in a project's spec source block. */
    public function reference(): array
    {
        return ['key' => $this->facet->handle, 'version' => $this->version];
    }
}
