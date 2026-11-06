<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Mcp\ToolCatalog;
use App\Models\McpConnection;
use App\Models\McpToolCall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI agents connected over MCP (Claude, ChatGPT, Claude Code, Codex): how to
 * connect one, the connected agents, and what they did.
 */
class AgentsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Agents/Index', [
            'mcpUrl' => url('/mcp'),
            'connections' => McpConnection::active()->where('user_id', $request->user()->id)->latest()->get()
                ->map(fn (McpConnection $connection) => [
                    'id' => $connection->id,
                    'name' => $connection->client_name,
                    'access' => McpConnection::accessLabel($connection->access),
                    'created_at' => $connection->created_at->diffForHumans(),
                    'last_used_at' => $connection->last_used_at?->diffForHumans(),
                ]),
            'historyCount' => McpToolCall::where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function history(Request $request): Response
    {
        $titles = collect(ToolCatalog::tools())->map(fn (array $tool) => $tool['title']);
        $calls = McpToolCall::where('user_id', $request->user()->id)->with('connection:id,client_name,revoked_at')
            ->latest('id')->paginate(50);

        return Inertia::render('Agents/History', [
            'calls' => $calls->through(fn (McpToolCall $call) => [
                'id' => $call->id,
                'agent' => $call->connection?->client_name,
                'disconnected' => $call->connection?->revoked_at !== null,
                'tool' => $titles[$call->tool] ?? $call->tool,
                'target' => $call->target,
                'outcome' => $call->outcome,
                'status' => $call->status,
                'message' => $call->message,
                'at' => $call->created_at->diffForHumans(),
                'at_iso' => $call->created_at->toIso8601String(),
            ]),
            'keepDays' => McpToolCall::KEEP_DAYS,
            'historyCount' => $calls->total(),
        ]);
    }

    /** Disconnect an agent: its OAuth tokens and its API token stop working. */
    public function destroy(Request $request, int $connection): RedirectResponse
    {
        McpConnection::active()->where('user_id', $request->user()->id)->findOrFail($connection)->revoke();

        return back();
    }
}
