<?php

namespace App\Mcp;

use App\Models\McpConnection;
use App\Models\McpToolCall;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * One API operation from ToolCatalog, offered as an MCP tool. Path
 * parameters, the query, and the JSON body's fields are its arguments. Every
 * tool also takes dry_run.
 */
class ApiTool extends Tool
{
    /**
     * @param  array<string, mixed>  $tool  from ToolCatalog::tools()
     */
    public function __construct(
        private readonly string $toolName,
        private readonly array $tool,
        private readonly McpConnection $connection,
    ) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function title(): string
    {
        return $this->tool['title'];
    }

    public function description(): string
    {
        $text = $this->tool['description'];

        return $text.' API: '.$this->tool['method'].' /api/v1'.$this->tool['path'].'.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        // A preview computes without saving, so it reads only though it is a POST
        $read = $this->tool['method'] === 'GET' || ($this->tool['read_only'] ?? false);

        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $this->inputSchema(),
            'annotations' => [
                'title' => $this->title(),
                'readOnlyHint' => $read,
                'destructiveHint' => ! $read && $this->tool['level'] === ToolCatalog::FULL,
                'idempotentHint' => $read || $this->tool['method'] === 'PATCH',
                'openWorldHint' => false,
            ],
        ];
    }

    public function handle(Request $request): Response
    {
        $arguments = $request->all();
        $dryRun = (bool) ($arguments['dry_run'] ?? false);
        unset($arguments['dry_run']);

        $call = $this->buildCall($arguments);

        if (isset($call['error'])) {
            $this->record($arguments, 'error', null, $call['error']['message']);

            return self::error($call['error']);
        }

        $refusal = AccessPolicy::check($this->connection, $this->toolName, $this->tool, $call['body'] ?? []);

        if ($refusal !== null) {
            $this->record($arguments, 'refused', null, $refusal['message']);

            return self::error($refusal);
        }

        if ($dryRun) {
            $this->record($arguments, 'dry_run');

            return Response::json([
                'dry_run' => true,
                'method' => $call['method'],
                'path' => '/api/v1'.$call['path'],
                'query' => $call['query'] ?: null,
                // File contents are shown as their size, never echoed back
                'body' => $call['body'] === null ? null : array_map(
                    fn ($value) => is_string($value) && strlen($value) > 2000 ? '('.number_format(strlen($value)).' characters)' : $value,
                    $call['body'],
                ),
            ]);
        }

        $result = (new ApiDispatcher($this->connection))->send($call['method'], $call['path'], $call['query'], $call['body']);

        if ($result['status'] >= 400) {
            $body = is_array($result['body']) ? $result['body'] : ['message' => (string) $result['body']];
            $this->record($arguments, 'error', $result['status'], is_string($body['message'] ?? null) ? $body['message'] : null);

            return self::error(['status' => $result['status'], 'code' => self::errorCode($result['status'])] + $body);
        }

        $this->record($arguments, 'ok', $result['status']);

        if ($result['status'] === 204 || $result['body'] === null || $result['body'] === '') {
            return Response::json(['ok' => true, 'status' => $result['status']]);
        }

        // Fields the agent should never see, such as a new project's token
        $body = is_array($result['body']) ? array_diff_key($result['body'], array_flip($this->tool['hide'] ?? [])) : $result['body'];

        return is_array($body) ? Response::json($body) : Response::text((string) $body);
    }

    /**
     * Split the arguments into the path, query, and body of the API request.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{method: string, path: string, query: array<string, mixed>, body: array<string, mixed>|null}|array{error: array<string, mixed>}
     */
    public function buildCall(array $arguments): array
    {
        $path = $this->tool['path'];

        foreach (array_keys($this->tool['path_params'] ?? []) as $name) {
            $value = $arguments[$name] ?? null;

            if (! is_scalar($value) || (string) $value === '') {
                return ['error' => ['code' => 'missing_argument', 'message' => "Pass {$name}.", 'fields' => [$name => ['Required.']]]];
            }

            $path = str_replace('{'.$name.'}', rawurlencode((string) $value), $path);
            unset($arguments[$name]);
        }

        $query = [];
        foreach (array_keys($this->tool['query'] ?? []) as $name) {
            if (array_key_exists($name, $arguments)) {
                $query[$name] = $arguments[$name];
                unset($arguments[$name]);
            }
        }

        $known = array_keys($this->tool['body']['properties'] ?? []);
        $body = $this->tool['method'] === 'GET' ? null : array_intersect_key($arguments, array_flip($known));

        // A tool argument named differently from the API field, such as artifact for the body's slug
        foreach ($this->tool['rename'] ?? [] as $from => $to) {
            if ($body !== null && array_key_exists($from, $body)) {
                $body[$to] = $body[$from];
                unset($body[$from]);
            }
        }

        return ['method' => $this->tool['method'], 'path' => $path, 'query' => $query, 'body' => $body];
    }

    /** @return array<string, mixed> */
    private function inputSchema(): array
    {
        $properties = ($this->tool['path_params'] ?? []) + ($this->tool['query'] ?? []) + ($this->tool['body']['properties'] ?? []);
        $required = [...array_keys($this->tool['path_params'] ?? []), ...($this->tool['body']['required'] ?? [])];

        $properties['dry_run'] = ['type' => 'boolean', 'description' => 'Return the request that would be sent, without sending it.'];

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** @param  array<string, mixed>  $arguments */
    private function record(array $arguments, string $outcome, ?int $status = null, ?string $message = null): void
    {
        McpToolCall::record($this->connection, $this->toolName, $arguments, $outcome, $status, $message);
    }

    private static function errorCode(int $status): string
    {
        return match ($status) {
            401, 403 => 'forbidden',
            402 => 'payment_required',
            404 => 'not_found',
            422 => 'validation_failed',
            429 => 'rate_limited',
            default => $status >= 500 ? 'server_error' : 'request_failed',
        };
    }

    /** @param  array<string, mixed>  $error */
    private static function error(array $error): Response
    {
        return Response::error(json_encode(['error' => $error], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
