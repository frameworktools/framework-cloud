<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\McpConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TokensController extends Controller
{
    public function index(Request $request): Response
    {
        $connections = McpConnection::active()->where('user_id', $request->user()->id)->latest()->get();

        return Inertia::render('Tokens', [
            // A connected AI app's own token is hidden; the app is listed under connections
            'tokens' => $request->user()->tokens()->whereNotIn('id', $connections->pluck('personal_access_token_id')->filter())
                ->latest()->get()->map(fn ($token) => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'created_at' => $token->created_at->diffForHumans(),
                    'last_used_at' => $token->last_used_at?->diffForHumans(),
                ]),
            'connections' => $connections->map(fn (McpConnection $connection) => [
                'id' => $connection->id,
                'name' => $connection->client_name,
                'access' => McpConnection::accessLabel($connection->access),
                'redirect_host' => $connection->redirect_host,
                'created_at' => $connection->created_at->diffForHumans(),
                'last_used_at' => $connection->last_used_at?->diffForHumans(),
            ]),
            'mcpUrl' => url('/mcp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:60']]);

        return back()->with([
            'token' => $request->user()->createToken($validated['name'])->plainTextToken,
            'status' => 'user-token',
        ]);
    }

    /** Disconnect an AI app: its OAuth tokens and its API token stop working. */
    public function destroyConnection(Request $request, int $connection): RedirectResponse
    {
        McpConnection::active()->where('user_id', $request->user()->id)->findOrFail($connection)->revoke();

        return back();
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back();
    }
}
