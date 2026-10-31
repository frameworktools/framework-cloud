<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacetVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['layer' => 'array', 'language_layers' => 'array'];
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
