<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The www name of the app's host redirects permanently to the app's host,
 * so search engines index one copy of each page.
 */
class RedirectWwwHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        if (is_string($appHost) && ! str_starts_with($appHost, 'www.') && strcasecmp($request->getHost(), 'www.'.$appHost) === 0) {
            return redirect()->away($appUrl.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
