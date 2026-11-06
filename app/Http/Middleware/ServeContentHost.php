<?php

namespace App\Http\Middleware;

use App\Support\ContentHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The content host serves published files and nothing else, and sets no
 * cookies. On the app, links to file content move to the content host.
 */
class ServeContentHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ContentHost::enabled()) {
            return $next($request);
        }

        // Global, so before routing: content links are matched by path (the documents.content and documents.chart routes)
        $content = (bool) preg_match('#^d/[A-Za-z0-9]+/(content|chart)/[0-9]+/#', $request->path());

        if (ContentHost::isContentRequest($request)) {
            abort_unless($content, 404);
            $response = $next($request);
            $response->headers->remove('Set-Cookie');
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }

            return $response;
        }

        // Content links on the app go to the content host, with the same signed path
        if ($content) {
            return redirect()->away(rtrim((string) config('framework.content_url'), '/').$request->getRequestUri());
        }

        return $next($request);
    }
}
