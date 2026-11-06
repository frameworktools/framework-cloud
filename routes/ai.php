<?php

use App\Http\Controllers\OAuth\ApproveMcpAuthorizationController;
use App\Http\Controllers\OAuth\OAuthMetadataController;
use App\Http\Controllers\OAuth\RegisterMcpClientController;
use App\Http\Controllers\OAuth\RevokeTokenController;
use App\Http\Middleware\EnsureAgentsEnabled;
use App\Http\Middleware\ResolveMcpConnection;
use App\Mcp\FrameworkServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
| The hosted MCP server and the OAuth sign-in that connects an AI app to it
| (Claude, ChatGPT, Claude Code, Codex). Loaded by laravel/mcp. Passport's
| own routes are off (AppServiceProvider): only the ones the sign-in needs
| are registered here.
*/

// Off when agents are turned off (FW_AGENTS, or Server administration)
Route::middleware(EnsureAgentsEnabled::class)->group(function () {
    Route::get('/.well-known/oauth-protected-resource/{path?}', [OAuthMetadataController::class, 'protectedResource'])
        ->where('path', '.*')
        ->name('mcp.oauth.protected-resource.nested'); // laravel/mcp's 401 challenge links here by this name

    Route::get('/.well-known/oauth-authorization-server/{path?}', [OAuthMetadataController::class, 'authorizationServer'])
        ->where('path', '.*')
        ->name('mcp.oauth.authorization-server');

    Route::prefix('oauth')->name('passport.')->group(function () {
        Route::post('/register', RegisterMcpClientController::class)
            ->middleware('throttle:mcp-oauth')
            ->name('register');

        Route::post('/revoke', RevokeTokenController::class)
            ->middleware('throttle:mcp-oauth')
            ->name('revoke');

        Route::post('/token', [AccessTokenController::class, 'issueToken'])
            ->middleware('throttle:mcp-oauth')
            ->name('token');

        Route::get('/authorize', [AuthorizationController::class, 'authorize'])
            ->middleware('web')
            ->name('authorizations.authorize');

        Route::post('/authorize', ApproveMcpAuthorizationController::class)
            ->middleware(['web', 'auth'])
            ->name('authorizations.approve');

        Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])
            ->middleware(['web', 'auth'])
            ->name('authorizations.deny');
    });

    Mcp::web('/mcp', FrameworkServer::class)
        ->middleware(['auth:api', ResolveMcpConnection::class, 'throttle:mcp'])
        ->name('mcp');
});
