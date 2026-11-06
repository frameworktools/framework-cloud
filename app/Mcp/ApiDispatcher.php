<?php

namespace App\Mcp;

use App\Models\McpConnection;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs an MCP tool call as a request to /api/v1, signed with the
 * connection's own hidden API token. Every API rule (ownership, validation,
 * paid publishing, rate limits) applies exactly as it does for a token used
 * from a script.
 */
class ApiDispatcher
{
    public function __construct(private readonly McpConnection $connection) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, body: mixed}
     */
    public function send(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $uri = '/api/v1'.$path.($query === [] ? '' : '?'.http_build_query($query));
        $appUrl = (string) config('app.url');

        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->connection->api_key,
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_HOST' => (string) parse_url($appUrl, PHP_URL_HOST).(parse_url($appUrl, PHP_URL_PORT) ? ':'.parse_url($appUrl, PHP_URL_PORT) : ''),
            'HTTPS' => str_starts_with($appUrl, 'https') ? 'on' : 'off',
            'REMOTE_ADDR' => request()->ip() ?? '127.0.0.1',
            'HTTP_USER_AGENT' => 'framework-cloud-mcp ('.$this->connection->client_name.')',
        ];

        $content = $body === null ? null : json_encode($body === [] ? new stdClass : $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($content !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $response = $this->handle(Request::create($uri, $method, [], [], [], $server, $content));
        $raw = (string) $response->getContent();
        $decoded = json_decode($raw, true);

        return [
            'status' => $response->getStatusCode(),
            'body' => json_last_error() === JSON_ERROR_NONE ? $decoded : $raw,
        ];
    }

    /**
     * Handle the API request inside this one, then put the outer request
     * back. Guards are forgotten on both sides so the API resolves the token
     * itself, and nothing about the inner request outlives it (Octane).
     */
    private function handle(Request $request): Response
    {
        $app = app();
        $outer = $app->make('request');

        Auth::forgetGuards();

        try {
            return $app->make(Kernel::class)->handle($request);
        } finally {
            $app->instance('request', $outer);
            Facade::clearResolvedInstance('request');
            app('url')->setRequest($outer);
            Auth::forgetGuards();
            Auth::shouldUse('api');
        }
    }
}
