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

    public const BROWSER_COOKIE = 'fw_magic_browser';

    /**
     * Issue a token for a user, returning the PLAIN token for the email URL.
     * A browser value ties the link to the browser that asked for it.
     */
    public static function issue(User $user, ?string $browser = null): string
    {
        $plain = Str::random(64);

        static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
            'browser_hash' => $browser !== null ? hash('sha256', $browser) : null,
            'created_at' => now(),
        ]);

        return $plain;
    }

    /**
     * Consume a plain token: returns the user exactly once, null otherwise.
     */
    public static function consume(string $plain): ?User
    {
        $token = static::usable($plain)->first();

        if (! $token) {
            return null;
        }

        $token->forceFill(['used_at' => now()])->save();

        return $token->user;
    }

    /**
     * The user a plain token would sign in, without using it up.
     */
    public static function peek(string $plain): ?User
    {
        return static::usable($plain)->first()?->user;
    }

    /**
     * Whether a plain token was issued to the browser holding this value.
     */
    public static function issuedTo(string $plain, ?string $browser): bool
    {
        return $browser !== null && $browser !== ''
            && static::usable($plain)->where('browser_hash', hash('sha256', $browser))->exists();
    }

    private static function usable(string $plain)
    {
        return static::query()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('used_at')
            ->where('expires_at', '>', now());
    }
}
