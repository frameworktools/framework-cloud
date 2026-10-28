<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ServeBuiltAssetsThroughTunnels
{
    // A browser on the far side of an Expose tunnel cannot reach the local
    // Vite dev server, so tunnelled requests get the built assets instead.
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isFromTrustedProxy() && $request->headers->has('X-Forwarded-Host')) {
            Vite::useHotFile(storage_path('framework/vite.hot.disabled'));
        }

        return $next($request);
    }
}
