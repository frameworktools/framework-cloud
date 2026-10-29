<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
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

    public const SCAFFOLD_STATUSES = ['pending', 'running', 'done', 'failed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'github_private' => 'boolean',
        ];
    }

    /** The blueprint version this project was made from, when it came from one. */
    public function blueprintVersion(): BelongsTo
    {
        return $this->belongsTo(BlueprintVersion::class);
    }

    public function githubUrl(): ?string
    {
        return $this->github_repo ? "https://github.com/{$this->github_repo}" : null;
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
