<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Mcp\ToolCatalog;
use App\Models\McpApprovedApp;
use App\Models\McpConnection;
use App\Models\McpToolCall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI agents connected over MCP (Claude, ChatGPT, Claude Code, Codex): how to
 * connect one, the connected agents, and what they did.
 */
class AgentsController extends Controller
{
    /** The apps with setup steps; the first is the section's landing page. */
    public const APPS = ['claude', 'chatgpt', 'claude-code', 'codex'];

    /** Setup steps for one app. */
    public function index(Request $request, string $app = 'claude'): Response
    {
        abort_unless(in_array($app, self::APPS, true), 404);
        // Apps disconnected on their side, or unused past their refresh window, stop showing as connected
        McpConnection::endExpired($request->user()->id);

        return Inertia::render('Agents/Index', [
            'app' => $app,
            'mcpUrl' => url('/mcp'),
            'plugin' => (bool) (config('framework.agent_plugin') ?? url('/mcp') === 'https://framework.pub/mcp'),
            'active' => McpConnection::active()->where('user_id', $request->user()->id)->exists(),
        ]);
    }

    /** Approved apps, then each time the user approved an agent: active connections, then those that ended in the last 90 days. */
    public function connections(Request $request): Response
    {
        McpConnection::endExpired($request->user()->id);

        return Inertia::render('Agents/Connections', [
            'approvedApps' => McpApprovedApp::where('user_id', $request->user()->id)->orderBy('client_name')->get()
                ->map(fn (McpApprovedApp $app) => [
                    'id' => $app->id, 'name' => $app->client_name, 'host' => $app->redirect_host,
                    'access' => $app->access, 'approved_at' => $app->updated_at->diffForHumans(),
                ]),
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
        ]);
    }

    /** Change the access a remembered app gets when it connects again. Its current connection keeps its own. */
    public function updateApp(Request $request, int $app): RedirectResponse
    {
        $validated = $request->validate(['access' => ['required', Rule::in(McpConnection::ACCESS_LEVELS)]]);
        McpApprovedApp::where('user_id', $request->user()->id)->findOrFail($app)->update($validated);

        return back();
    }

    /** Forget an app, so it asks again the next time it connects. */
    public function forgetApp(Request $request, int $app): RedirectResponse
    {
        McpApprovedApp::where('user_id', $request->user()->id)->findOrFail($app)->delete();

        return back();
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
        ]);
    }

    /** Disconnect an agent: its OAuth tokens and its API token stop working. */
    public function destroy(Request $request, int $connection): RedirectResponse
    {
        McpConnection::active()->where('user_id', $request->user()->id)->findOrFail($connection)->revoke();

        return back();
    }
}
