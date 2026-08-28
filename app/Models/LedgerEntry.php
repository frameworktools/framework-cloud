<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a project's data-integrity ledger: a named dataset's digest at
 * a moment in time. Append-only is the product — corrections are new entries.
 * Enforced three ways: this model guard, the absence of update/delete routes,
 * and (on Postgres) a database trigger + revoked grants in production.
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
            'client' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
