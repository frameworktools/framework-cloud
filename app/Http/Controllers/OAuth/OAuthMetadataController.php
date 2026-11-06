<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Http\JsonResponse;

/**
 * OAuth discovery for MCP clients (RFC 9728 and RFC 8414). A client that gets
 * a 401 from /mcp reads these to find where to register and sign in.
 */
class OAuthMetadataController
{
    public function protectedResource(?string $path = ''): JsonResponse
    {
        return response()->json([
            'resource' => url('/'.ltrim((string) $path, '/')),
            'authorization_servers' => [url('/')],
            'scopes_supported' => ['mcp:use'],
            'resource_name' => 'Framework Cloud',
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => route('passport.register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp:use'],
        ]);
    }
}
