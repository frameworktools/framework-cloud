<?php

namespace App\Http\Middleware;

use App\Models\McpConnection;
use App\Models\OAuthUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the connection behind an MCP request's OAuth token: the access level
 * the person chose when they approved the app. Kept on the request's
 * attributes, never in the container, so nothing outlives the request.
 */
class ResolveMcpConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        $token = $user instanceof OAuthUser ? $user->currentAccessToken() : null;

        if (! $token || ! $token->can('mcp:use')) {
            return response()->json(['message' => 'Unauthorized.', 'code' => 'missing_mcp_token'], 401);
        }

        $connection = McpConnection::query()
            ->active()
            ->with('user')
            ->where('user_id', $user->getKey())
            ->where('oauth_client_id', $token->oauth_client_id)
            ->latest('id')
            ->first();

        if (! $connection || ! $connection->user) {
            return response()->json([
                'message' => 'This app is no longer connected to Framework Cloud. Connect it again from your AI app.',
                'code' => 'mcp_connection_not_found',
            ], 401);
        }

        if ($connection->last_used_at === null || $connection->last_used_at->lt(now()->subMinutes(5))) {
            $connection->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('mcp_connection', $connection);

        return $next($request);
    }
}
