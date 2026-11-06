<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * An AI app (Claude, ChatGPT, Claude Code, Codex) connected to an account
 * over MCP.
 *
 * One per user and OAuth client. The owner picks the access level on the
 * consent screen. Tool calls run through the API with the connection's own
 * hidden token, so every API rule applies; the access level decides which
 * tools the app sees (App\Mcp\ToolCatalog).
 */
class McpConnection extends Model
{
    public const ACCESS_READ = 'read';

    public const ACCESS_FULL = 'full';

    public const ACCESS_LEVELS = [self::ACCESS_FULL, self::ACCESS_READ];

    /** The hidden API token's name, so the API tokens page can leave it out. */
    public const TOKEN_PREFIX = 'AI app: ';

    protected $fillable = [
        'user_id', 'oauth_client_id', 'client_name', 'redirect_host',
        'access', 'personal_access_token_id', 'api_key', 'last_used_at', 'revoked_at',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id');
    }

    /** @param  Builder<McpConnection>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public static function accessLabel(string $access): string
    {
        return match ($access) {
            self::ACCESS_READ => 'Read only',
            self::ACCESS_FULL => 'Full access',
            default => $access,
        };
    }

    /** Cut the app off: its OAuth tokens, its refresh tokens, and its API token. */
    public function revoke(): void
    {
        $tokens = Token::query()
            ->where('client_id', $this->oauth_client_id)
            ->where('user_id', $this->user_id)
            ->get();

        foreach ($tokens as $token) {
            $token->revoke();
            $token->refreshToken?->revoke();
        }

        $this->apiToken?->delete();

        $this->forceFill(['revoked_at' => now(), 'personal_access_token_id' => null])->save();
    }
}
