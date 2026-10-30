<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A device sign-in request from fw login. The CLI holds the plain device
 * code and polls with it; only its SHA-256 is stored. The user types the
 * short user code into /device in a signed-in browser to approve it.
 */
class CliDeviceCode extends Model
{
    public const LIFETIME_SECONDS = 600;

    public const INTERVAL_SECONDS = 5;

    // No 0/O, 1/I/L: easy to read off a terminal and type
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DENIED = 'denied';

    public const CONSUMED = 'consumed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_polled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Start a request, returning the model and the PLAIN device code for the
     * CLI. Expired requests are pruned first.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(?string $clientName = null): array
    {
        static::where('expires_at', '<=', now())->delete();

        $plain = Str::random(48);

        do {
            $userCode = static::randomUserCode();
        } while (static::where('user_code', $userCode)->exists());

        $code = static::create([
            'device_code_hash' => hash('sha256', $plain),
            'user_code' => $userCode,
            'client_name' => filled($clientName) ? $clientName : null,
            'status' => self::PENDING,
            'expires_at' => now()->addSeconds(self::LIFETIME_SECONDS),
        ]);

        return [$code, $plain];
    }

    /**
     * The request a plain device code belongs to, expired or not.
     */
    public static function findByDeviceCode(string $plain): ?self
    {
        return static::where('device_code_hash', hash('sha256', $plain))->first();
    }

    /**
     * The request a typed user code belongs to, expired or not. Case and
     * dashes are ignored.
     */
    public static function findByUserCode(string $typed): ?self
    {
        $normalized = static::normalizeUserCode($typed);

        return strlen($normalized) === 8
            ? static::where('user_code', $normalized)->first()
            : null;
    }

    public static function normalizeUserCode(string $typed): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $typed));
    }

    /**
     * The user code as shown to people: ABCD-EFGH.
     */
    public function displayCode(): string
    {
        return substr($this->user_code, 0, 4).'-'.substr($this->user_code, 4);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Whether the CLI polled again sooner than the interval allows.
     */
    public function polledTooSoon(): bool
    {
        return $this->last_polled_at !== null
            && $this->last_polled_at->gt(now()->subSeconds(self::INTERVAL_SECONDS));
    }

    /**
     * Move an approved request to consumed. True exactly once, so two racing
     * polls can't both receive a token.
     */
    public function consume(): bool
    {
        return static::whereKey($this->id)
            ->where('status', self::APPROVED)
            ->update(['status' => self::CONSUMED, 'updated_at' => now()]) === 1;
    }

    private static function randomUserCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;

        return implode('', array_map(
            fn () => self::ALPHABET[random_int(0, $max)],
            range(1, 8),
        ));
    }
}
