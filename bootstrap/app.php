<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePasswordsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ServeBuiltAssetsThroughTunnels;
use App\Http\Middleware\ServeContentHost;
use App\Http\Middleware\SingleUserMode;
use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Support\TrustedProxies;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Proxies in front of the app pass the public host and https scheme
        // in X-Forwarded-* headers. Loopback is trusted by default (Expose
        // tunnels, a local web server). TRUSTED_PROXIES adds a reverse proxy
        // in front of a self-hosted server: "*" or a comma-separated list of
        // addresses and CIDR ranges. Read from the process environment, which
        // Docker sets.
        // TRUSTED_PROXIES as the process sees it (Docker sets it). AppServiceProvider applies it again
        // from config, which also covers a value in .env with a cached config. "cloudflare" names
        // Cloudflare's edge; see App\Support\TrustedProxies.
        $middleware->trustProxies(at: TrustedProxies::resolve(env('TRUSTED_PROXIES')));

        // Library forms carry file text (SKILL.md, YAML, templates) whose
        // whitespace is content, so they skip input trimming.
        $middleware->trimStrings(except: [
            fn (Request $request) => $request->is('blueprints*', 'facets*', 'structures*', 'skills*'),
        ]);

        // Every request, API and MCP included: the content host serves only published files, without cookies
        $middleware->prepend(ServeContentHost::class);

        $middleware->web(append: [
            ServeBuiltAssetsThroughTunnels::class,
            SingleUserMode::class,
            HandleInertiaRequests::class,
        ]);

        // Route middleware such as auth is sorted ahead of the web group's
        // appended middleware, so single-user sign-in must be ranked first
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: SingleUserMode::class,
        );

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'passwords' => EnsurePasswordsEnabled::class,
            'throttle' => ThrottleRequestsPerRoute::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API clients (curl, Stata, scripts) may omit Accept: application/json.
        // They still get a JSON 401 or 422, never a redirect to the login page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp', 'oauth/token', 'oauth/register', 'oauth/revoke') || $request->expectsJson(),
        );
    })->create();
