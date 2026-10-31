<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * `throttle:N,M` with a separate counter for each route.
 *
 * Laravel's version keys the counter on the user, or for guests on the IP
 * address, so every throttled route shares it. fw polling /api/v1/cli/device/token
 * then used up the limit on starting a new login, and a few sign-in page
 * requests could block sending a sign-in link.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return sha1(parent::resolveRequestSignature($request).'|'.$request->method().' '.$route?->uri());
    }
}
