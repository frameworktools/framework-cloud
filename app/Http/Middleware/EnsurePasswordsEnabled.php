<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('framework.passwords'), 404);

        return $next($request);
    }
}
