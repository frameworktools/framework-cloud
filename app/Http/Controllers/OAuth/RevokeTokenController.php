<?php

namespace App\Http\Controllers\OAuth;

use App\Models\McpConnection;
use Defuse\Crypto\Crypto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Throwable;

/**
 * Token revocation (RFC 7009). An AI app calls this when someone disconnects
 * it on the app's side; we then end the whole connection, as Disconnect on
 * the Connected agents page does.
 *
 * Takes an access token (a signed JWT) or a refresh token (encrypted by
 * Passport). Answers 200 whether or not the token was valid, as the RFC asks,
 * so the endpoint says nothing about which tokens exist.
 */
class RevokeTokenController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:8000'], 'client_id' => ['sometimes', 'nullable', 'string', 'max:100']]);

        $found = $this->accessToken((string) $request->input('token')) ?? $this->refreshToken((string) $request->input('token'));

        // A public client names itself; it may only revoke its own tokens
        if ($found !== null && (! $request->filled('client_id') || (string) $request->input('client_id') === (string) $found['client_id'])) {
            McpConnection::query()->active()
                ->where('user_id', $found['user_id'])
                ->where('oauth_client_id', $found['client_id'])
                ->get()
                ->each(fn (McpConnection $connection) => $connection->revoke(McpConnection::ENDED_BY_APP));
        }

        return response()->json([], 200, ['Cache-Control' => 'no-store']);
    }

    /** @return array{client_id: string, user_id: int}|null */
    private function accessToken(string $jwt): ?array
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse($jwt);
            $key = InMemory::plainText(str_replace('\\n', "\n", (string) (config('passport.public_key') ?: file_get_contents(Passport::keyPath('oauth-public.key')))));
            if (! $token instanceof UnencryptedToken || ! (new Validator)->validate($token, new SignedWith(new Sha256, $key))) {
                return null;
            }
            $row = Token::query()->find($token->claims()->get('jti'));
        } catch (Throwable) {
            return null;
        }

        return $row && $row->user_id ? ['client_id' => (string) $row->client_id, 'user_id' => (int) $row->user_id] : null;
    }

    /** @return array{client_id: string, user_id: int}|null */
    private function refreshToken(string $encrypted): ?array
    {
        try {
            $payload = json_decode(Crypto::decryptWithPassword($encrypted, Passport::tokenEncryptionKey(app('encrypter'))), true);
            $row = is_array($payload) ? RefreshToken::query()->with('accessToken')->find($payload['refresh_token_id'] ?? null) : null;
        } catch (Throwable) {
            return null;
        }

        $access = $row?->accessToken;

        return $access && $access->user_id ? ['client_id' => (string) $access->client_id, 'user_id' => (int) $access->user_id] : null;
    }
}
