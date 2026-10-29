<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitHubAccount extends Model
{
    protected $table = 'github_accounts';

    protected $guarded = [];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Store the tokens from an OAuth exchange or refresh. */
    public function fillTokens(string $accessToken, ?string $refreshToken, ?int $expiresIn, ?int $refreshExpiresIn): static
    {
        return $this->fill([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
            'refresh_token_expires_at' => $refreshExpiresIn ? now()->addSeconds($refreshExpiresIn) : null,
        ]);
    }
}
