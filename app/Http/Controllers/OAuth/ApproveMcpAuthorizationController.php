<?php

namespace App\Http\Controllers\OAuth;

use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Approves an AI app's connection from the consent screen.
 *
 * The person picks an access level. We give the connection its own hidden API
 * token, then let Passport finish the OAuth approval and send the app its
 * code. A second approval for the same app replaces the first connection.
 */
class ApproveMcpAuthorizationController
{
    public function __invoke(Request $request, ResponseInterface $psrResponse, ApproveAuthorizationController $passport): Response
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'access' => ['required', Rule::in(McpConnection::ACCESS_LEVELS)],
        ], ['access.*' => 'Choose what the app may do.']);

        $authRequest = $this->pendingAuthRequest($request);
        $clientId = $authRequest?->getClient()->getIdentifier();
        $client = $clientId ? Client::query()->find($clientId) : null;

        abort_if($client === null, 400, 'This sign-in request has expired. Start connecting again from your AI app.');

        $response = $passport->approve($request, $psrResponse);

        if (! $response->isRedirection()) {
            return $response;
        }

        DB::transaction(function () use ($user, $client, $validated, $authRequest): void {
            $redirectHost = parse_url((string) $authRequest->getRedirectUri(), PHP_URL_HOST) ?: null;
            $web = $redirectHost !== null && ! in_array($redirectHost, ['localhost', '127.0.0.1', '[::1]'], true);

            // The same app approved again replaces its old connection. Web apps such as Claude register
            // a new client each time they connect, so for them the same name and host count as the same
            // app. Apps on a computer (loopback) are matched by client only: two machines may each
            // connect their own Claude Code.
            McpConnection::query()
                ->active()
                ->where('user_id', $user->id)
                ->where(fn ($query) => $query->where('oauth_client_id', $client->id)
                    ->when($web, fn ($q) => $q->orWhere(fn ($same) => $same->where('client_name', $client->name)->where('redirect_host', $redirectHost))))
                ->get()
                ->each(fn (McpConnection $old) => $old->revoke(McpConnection::ENDED_REPLACED));

            $token = $user->createToken(McpConnection::TOKEN_PREFIX.$client->name);

            McpConnection::query()->create([
                'user_id' => $user->id,
                'oauth_client_id' => $client->id,
                'client_name' => $client->name,
                'redirect_host' => $redirectHost,
                'access' => $validated['access'],
                'personal_access_token_id' => $token->accessToken->id,
                'api_key' => $token->plainTextToken,
            ]);
        });

        return $response;
    }

    /**
     * Read the request Passport parked in the session, without taking it:
     * Passport's approve() takes it and checks the auth token.
     */
    private function pendingAuthRequest(Request $request): ?AuthorizationRequestInterface
    {
        $serialized = $request->session()->get('authRequest');

        if (! is_string($serialized)) {
            return null;
        }

        $authRequest = unserialize($serialized);

        return $authRequest instanceof AuthorizationRequestInterface ? $authRequest : null;
    }
}
