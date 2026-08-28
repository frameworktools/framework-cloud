<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    /**
     * Valid project types. Mirrors the framework R package's types,
     * plus "bare" (a near-empty project).
     */
    public const TYPES = [
        'project',
        'project_sensitive',
        'course',
        'presentation',
        'bare',
    ];

    public const PULL_ABILITY = 'project:pull';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a read-only pull token for this project. Returns the plain-text
     * token — shown once, stored hashed by Sanctum.
     */
    public function issuePullToken(string $name = 'pull'): string
    {
        return $this->createToken($name, [self::PULL_ABILITY])->plainTextToken;
    }
}
