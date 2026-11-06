<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tool call by a connected agent, for the History page. Keeps the tool, what
 * it named (project, report, artifact), and how it ended, never the request or
 * response body. Kept for KEEP_DAYS days.
 */
class McpToolCall extends Model
{
    public const KEEP_DAYS = 90;

    public const UPDATED_AT = null;

    protected $fillable = ['mcp_connection_id', 'user_id', 'tool', 'target', 'outcome', 'status', 'message'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'mcp_connection_id');
    }

    /**
     * Record a call. Now and then, also drop the user's calls older than KEEP_DAYS,
     * since there is no scheduler.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function record(McpConnection $connection, string $tool, array $arguments, string $outcome, ?int $status = null, ?string $message = null): void
    {
        $target = collect(['project', 'slug', 'report', 'artifact', 'skill', 'blueprint'])
            ->map(fn (string $key) => is_scalar($arguments[$key] ?? null) ? (string) $arguments[$key] : null)
            ->filter()->implode(' / ');

        static::query()->create([
            'mcp_connection_id' => $connection->id,
            'user_id' => $connection->user_id,
            'tool' => $tool,
            'target' => $target !== '' ? mb_substr($target, 0, 255) : null,
            'outcome' => $outcome,
            'status' => $status,
            'message' => $message !== null ? mb_substr($message, 0, 500) : null,
        ]);

        if (random_int(1, 50) === 1) {
            static::query()->where('user_id', $connection->user_id)->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        }
    }
}
