<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePasswordsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ServeBuiltAssetsThroughTunnels;
use App\Http\Middleware\SingleUserMode;
use App\Http\Middleware\ThrottleRequestsPerRoute;
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
        $proxies = trim((string) env('TRUSTED_PROXIES', ''));
        $middleware->trustProxies(at: match (true) {
            $proxies === '*' => '*',
            $proxies !== '' => array_values(array_filter(array_map('trim', explode(',', $proxies)))),
            default => ['127.0.0.1', '::1'],
        });

        // Library forms carry file text (SKILL.md, YAML, templates) whose
        // whitespace is content, so they skip input trimming.
        $middleware->trimStrings(except: [
            fn (Request $request) => $request->is('blueprints*', 'facets*', 'skills*'),
        ]);

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
        //
    })->create();
