<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'number' => 'integer'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function url(): string
    {
        return route('reports.version', ['key' => $this->report->key, 'number' => $this->number]);
    }
}
