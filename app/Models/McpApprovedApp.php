<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A web AI app (Claude, ChatGPT) a person approved once. When the same app,
 * by name and redirect host, connects again, it gets the saved access without
 * the consent screen. Apps on a computer (loopback) always ask: they name
 * themselves, so any local program could claim to be Claude Code.
 */
class McpApprovedApp extends Model
{
    protected $guarded = [];

    public const LOOPBACK = ['localhost', '127.0.0.1', '[::1]'];

    public static function rememberable(?string $redirectHost): bool
    {
        return $redirectHost !== null && $redirectHost !== '' && ! in_array($redirectHost, self::LOOPBACK, true);
    }

    public static function for(int $userId, string $clientName, ?string $redirectHost): ?self
    {
        return self::rememberable($redirectHost)
            ? self::query()->where('user_id', $userId)->where('client_name', $clientName)->where('redirect_host', $redirectHost)->first()
            : null;
    }
}
