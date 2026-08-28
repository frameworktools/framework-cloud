<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMetricsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('monitoring.metrics_token');

        if (blank($expected) || ! hash_equals($expected, (string) $request->bearerToken())) {
            abort(401);
        }

        return $next($request);
    }
}
