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
        // Apps disconnected on their side, or unused past their refresh window, stop showing as connected
        McpConnection::endExpired($request->user()->id);

        return Inertia::render('Agents/Index', [
            'mcpUrl' => url('/mcp'),
            'active' => McpConnection::active()->where('user_id', $request->user()->id)->exists(),
            // Active connections, then those that ended in the last 90 days
            'connections' => McpConnection::where('user_id', $request->user()->id)
                ->where(fn ($query) => $query->whereNull('revoked_at')->orWhere('revoked_at', '>', now()->subDays(90)))
                ->orderByRaw('revoked_at is not null')->latest()->limit(50)->get()
                ->map(fn (McpConnection $connection) => [
                    'id' => $connection->id,
                    'name' => $connection->client_name,
                    'access' => McpConnection::accessLabel($connection->access),
                    'active' => $connection->revoked_at === null,
                    'status' => $connection->revoked_at === null ? 'Active' : ucfirst(McpConnection::endedLabel($connection->ended_reason) ?? 'disconnected'),
                    'created_at' => $connection->created_at->diffForHumans(),
                    'last_used_at' => $connection->last_used_at?->diffForHumans(),
                ]),
            'historyCount' => McpToolCall::where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function history(Request $request): Response
    {
        $titles = collect(ToolCatalog::tools())->map(fn (array $tool) => $tool['title']);
        $calls = McpToolCall::where('user_id', $request->user()->id)->with('connection:id,client_name,revoked_at,ended_reason')
            ->latest('id')->paginate(50);

        return Inertia::render('Agents/History', [
            'calls' => $calls->through(fn (McpToolCall $call) => [
                'id' => $call->id,
                'agent' => $call->connection?->client_name,
                'ended' => $call->connection?->revoked_at !== null ? McpConnection::endedLabel($call->connection->ended_reason) ?? 'disconnected' : null,
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
