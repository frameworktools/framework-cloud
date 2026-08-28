<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Blueprint is a project type: its directory structure, render/quarto
 * configuration, and the master copies of the AI artifacts (AGENTS.md
 * template, skill files) that projects of this type receive.
 *
 * System blueprints mirror the framework R package's built-in types and are
 * managed in the admin panel. Per-user customization happens as overrides in
 * the user's settings document, never by editing the masters.
 */
class Blueprint extends Model
{
    /** @use HasFactory<\Database\Factories\BlueprintFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'skills' => 'array',
            'is_system' => 'boolean',
        ];
    }
}
