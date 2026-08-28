<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'client' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }
}
