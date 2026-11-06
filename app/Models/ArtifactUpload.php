<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time link that publishes the raw file sent to it, for files too large
 * to send in an agent's tool call. Only a hash of the link's token is stored.
 */
class ArtifactUpload extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'mcp_connection_id');
    }
}
