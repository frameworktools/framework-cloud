<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class User extends Authenticatable implements HasPasskeys, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, InteractsWithPasskeys, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'setup_completed_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'profile_public' => 'boolean',
            'can_publish' => 'boolean',
            'project_defaults' => 'array',
        ];
    }

    /**
     * Publishing is available to every account. Hosted uploads share an account storage budget.
     */
    public function canPublish(): bool
    {
        return true;
    }

    public function githubAccount(): HasOne
    {
        return $this->hasOne(GitHubAccount::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The automatic home for artifacts published without a project, made the first
     * time one is. Its link name is "artifacts", or the next free one if a project
     * already uses that.
     */
    public function artifactHome(): Project
    {
        $home = $this->projects()->where('is_home', true)->first();
        if ($home) {
            return $home;
        }

        $slug = Project::HOME_SLUG;
        for ($n = 2; Project::withTrashed()->where('user_id', $this->id)->where('slug', $slug)->exists(); $n++) {
            $slug = Project::HOME_SLUG.'-'.$n;
        }

        return $this->projects()->create(['name' => Project::HOME_NAME, 'slug' => $slug, 'project_type' => 'project', 'is_home' => true]);
    }

    /**
     * The user's global settings document (seeded with defaults on first access).
     */
    public function globalSettings(): Setting
    {
        return Setting::forOwner($this);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
