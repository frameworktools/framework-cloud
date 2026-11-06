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
            // A connected agent's own token is hidden; the agent is listed on the Agents page
            'tokens' => $request->user()->tokens()->whereNotIn('id', $connections->pluck('personal_access_token_id')->filter())
                ->latest()->get()->map(fn ($token) => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'created_at' => $token->created_at->diffForHumans(),
                    'last_used_at' => $token->last_used_at?->diffForHumans(),
                ]),
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

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back();
    }
}
