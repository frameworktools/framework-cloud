<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    /**
     * Valid project types. Mirrors the framework R package's types, the
     * app types (Shiny and Plumber for R, Shiny and FastAPI for Python),
     * and "bare" (a near-empty project).
     */
    public const TYPES = [
        'project',
        'project_sensitive',
        'course',
        'presentation',
        'shiny',
        'plumber',
        'shiny_python',
        'fastapi',
        'bare',
    ];

    /** App types that only one language's package can build */
    public const LANGUAGE_TYPES = ['shiny' => 'r', 'plumber' => 'r', 'shiny_python' => 'python', 'fastapi' => 'python'];

    /**
     * Languages a project can use. The R package scaffolds r, the Python
     * package scaffolds python, and fw scaffolds stata.
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
        $only = self::LANGUAGE_TYPES[$type] ?? null;
        if ($only !== null && $only !== $language) {
            return 'The '.str_replace('_', ' ', $type).' structure is for '.self::LANGUAGE_NAMES[$only].' projects.';
        }

        return null;
    }

    public const PULL_ABILITY = 'project:pull';

    public const SCAFFOLD_STATUSES = ['pending', 'running', 'done', 'failed'];

    protected $guarded = [];

    /**
     * Deleted forever: its artifacts go through their own models first, so their files are
     * removed (the database would otherwise drop their rows and leave the files). Its tokens go
     * too. Reports, collections, and ledger entries go with the row.
     */
    protected static function booted(): void
    {
        static::forceDeleting(function (Project $project) {
            Document::withTrashed()->where('project_id', $project->id)->get()->each->forceDelete();
            $project->tokens()->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'composition' => 'array',
            'github_private' => 'boolean',
            'is_home' => 'boolean',
            // spec stays raw JSON text so empty maps stay {}
            'spec_received_at' => 'datetime',
            'adopted_at' => 'datetime',
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

    /** Artifacts from elsewhere in the account that this project lists and may use in reports. */
    public function linkedDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class)->withPivot('created_at');
    }

    /** Projects people made, leaving out the account's automatic home for artifacts. */
    public function scopeListed(Builder $query): void
    {
        $query->where('is_home', false);
    }

    /** The home's name and link name, and the link name used when a project already has it. */
    public const HOME_NAME = 'Artifacts';

    public const HOME_SLUG = 'artifacts';

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
