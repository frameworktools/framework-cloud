<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Languages a project can use. The R package scaffolds r, the Python
     * package scaffolds python, and fw scaffolds stata (scaffolding only).
     */
    public const LANGUAGES = ['r', 'python', 'stata'];

    public const LANGUAGE_NAMES = ['r' => 'R', 'python' => 'Python', 'stata' => 'Stata'];

    /** Project types fw can scaffold for Stata. It refuses course and presentation. */
    public const STATA_TYPES = ['project', 'project_sensitive', 'bare'];

    /** Why a project type can't be used with a language, or null when it can. */
    public static function typeProblem(string $language, string $type): ?string
    {
        if ($language === 'stata' && ! in_array($type, self::STATA_TYPES, true)) {
            return 'Stata projects can use the standard, privacy-sensitive, or bare structure. This one uses the '.str_replace('_', ' ', $type).' structure.';
        }

        return null;
    }

    public const PULL_ABILITY = 'project:pull';

    public const SCAFFOLD_STATUSES = ['pending', 'running', 'done', 'failed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'github_private' => 'boolean',
            // spec stays raw JSON text so empty maps stay {}
            'spec_received_at' => 'datetime',
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

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
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
