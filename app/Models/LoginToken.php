<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single-use magic-link token. Only the SHA-256 of the token is stored;
 * the plain value exists once, inside the emailed URL.
 */
class LoginToken extends Model
{
    public const UPDATED_AT = null;

    public const LIFETIME_MINUTES = 15;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a token for a user, returning the PLAIN token for the email URL.
     */
    public static function issue(User $user): string
    {
        $plain = Str::random(64);

        static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
            'created_at' => now(),
        ]);

        return $plain;
    }

    /**
     * Consume a plain token: returns the user exactly once, null otherwise.
     */
    public static function consume(string $plain): ?User
    {
        $token = static::query()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token) {
            return null;
        }

        $token->forceFill(['used_at' => now()])->save();

        return $token->user;
    }
}
