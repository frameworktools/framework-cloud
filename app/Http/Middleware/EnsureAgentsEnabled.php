<?php

namespace App\Http\Middleware;

use App\Support\Agents;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** With agents turned off, the MCP server, its OAuth sign-in, and the Agents page are not found. */
class EnsureAgentsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Agents::enabled(), 404);

        return $next($request);
    }
}
