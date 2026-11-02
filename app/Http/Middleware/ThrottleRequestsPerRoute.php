<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Auth\Authenticatable;
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
        $user = $request->user();

        // A project token's tokenable is a Project, which Laravel's version
        // can't key on (it has no getAuthIdentifier), so key it ourselves
        $who = $user instanceof Authenticatable
            ? parent::resolveRequestSignature($request)
            : sha1(($user ? $user::class.':'.$user->getKey() : $request->ip()).'|'.$request->ip());

        return sha1($who.'|'.$request->method().' '.$route?->uri());
    }
}
