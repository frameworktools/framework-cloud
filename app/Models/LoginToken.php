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

    /** Wrong codes allowed before the code stops working */
    public const MAX_CODE_ATTEMPTS = 5;

    /** Session key holding the id of the token whose code this browser may enter */
    public const CODE_SESSION_KEY = 'login.code_token';

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

    /** Set with BROWSER_COOKIE when "Keep me signed in" was checked */
    public const REMEMBER_COOKIE = 'fw_login_remember';

    /** "password" when this browser last signed in with a password */
    public const METHOD_COOKIE = 'fw_login_method';

    /** A year, in minutes */
    public const METHOD_COOKIE_MINUTES = 525600;

    /** No second link is emailed to an account within this many seconds of the last */
    public const RESEND_SECONDS = 60;

    /** Session key holding the address typed at sign-in, for "Use your password instead" */
    public const EMAIL_SESSION_KEY = 'login.email';

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
     * Issue a token and a six-digit code together, for an emailed link. Using
     * either one uses up both.
     *
     * @return array{token: string, code: string, id: int}
     */
    public static function issueWithCode(User $user, ?string $browser = null): array
    {
        $plain = Str::random(64);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $record = static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'code_hash' => self::hashCode($code),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
            'browser_hash' => $browser !== null ? hash('sha256', $browser) : null,
            'created_at' => now(),
        ]);

        return ['token' => $plain, 'code' => $code, 'id' => $record->id];
    }

    /**
     * Sign in with the code of token $id: the user exactly once, or null. A
     * wrong code counts against the token, which stops taking codes after
     * MAX_CODE_ATTEMPTS.
     */
    public static function consumeCode(int $id, string $code): ?User
    {
        $token = static::query()->whereKey($id)->whereNotNull('code_hash')->whereNull('used_at')->where('expires_at', '>', now())->first();

        if (! $token || $token->attempts >= self::MAX_CODE_ATTEMPTS) {
            return null;
        }
        if (! hash_equals($token->code_hash, self::hashCode(preg_replace('/\D/', '', $code)))) {
            $token->increment('attempts');

            return null;
        }
        $token->forceFill(['used_at' => now()])->save();

        return $token->user;
    }

    private static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
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
