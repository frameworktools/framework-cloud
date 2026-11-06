<?php

namespace App\Http\Controllers\OAuth;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;

/**
 * Dynamic client registration (RFC 7591) for MCP clients.
 *
 * Registering grants nothing: a client still needs a person to sign in and
 * approve it on the consent screen. The redirect check sends codes only to
 * the person's own computer (Claude Code, Codex) or to the AI apps we support
 * on the web.
 */
class RegisterMcpClientController
{
    public function __invoke(Request $request, ClientRepository $clients): JsonResponse
    {
        $validated = $request->validate([
            'client_name' => ['nullable', 'string', 'max:100'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:5'],
            'redirect_uris.*' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (! self::allowedRedirect((string) $value)) {
                    $fail('This redirect address is not allowed.');
                }
            }],
        ]);

        $client = $clients->createAuthorizationCodeGrantClient(
            name: trim((string) ($validated['client_name'] ?? '')) ?: 'AI app',
            redirectUris: $validated['redirect_uris'],
            confidential: false,
        );

        return response()->json([
            'client_id' => (string) $client->id,
            'client_name' => $client->name,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'redirect_uris' => $client->redirect_uris,
            'scope' => 'mcp:use',
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }

    /**
     * Loopback on any port over http, or one of the configured web hosts
     * over https. Compared by parsed host, never by string prefix.
     */
    public static function allowedRedirect(string $uri): bool
    {
        $parts = parse_url($uri);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return true;
        }

        if ($scheme !== 'https') {
            return false;
        }

        return in_array($host, array_map('strtolower', config('mcp.redirect_hosts', [])), true);
    }
}
