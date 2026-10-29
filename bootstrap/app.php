<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ServeBuiltAssetsThroughTunnels;
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
        // Expose tunnels (Polyscope's remote preview) hand requests to the
        // local web server from loopback, carrying the public host and https
        // scheme in X-Forwarded-* headers. Only loopback is trusted, so
        // production clients cannot spoof them.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // Library forms carry file text (SKILL.md, YAML, templates) whose
        // whitespace is content, so they skip input trimming.
        $middleware->trimStrings(except: [
            fn (Request $request) => $request->is('blueprints*', 'facets*', 'skills*'),
        ]);

        $middleware->web(append: [
            ServeBuiltAssetsThroughTunnels::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
