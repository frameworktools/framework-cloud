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
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'profile_public' => 'boolean',
            'can_publish' => 'boolean',
            'project_defaults' => 'array',
        ];
    }

    /**
     * Publishing documents is open to everyone on a self-hosted server, and
     * a paid feature on the hosted one (framework.pub).
     */
    public function canPublish(): bool
    {
        return config('framework.mode') !== 'hosted' || $this->can_publish;
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
