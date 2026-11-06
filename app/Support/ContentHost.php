<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where published files are served from (FW_CONTENT_URL), apart from the app.
 *
 * Published HTML runs its own scripts. It already runs in an opaque sandbox, so
 * it cannot act as the signed-in viewer; a host of its own also keeps any
 * page someone publishes off the app's address, the way GitHub serves
 * githubusercontent.com. Content links there carry a signed, expiring access
 * token (ArtifactContentAccess), since the content host has no session, and it
 * serves nothing but those links. Unset, files are served from the app itself.
 */
class ContentHost
{
    public static function enabled(): bool
    {
        return filled(config('framework.content_url'));
    }

    public static function host(): ?string
    {
        return self::enabled() ? parse_url((string) config('framework.content_url'), PHP_URL_HOST) : null;
    }

    public static function isContentRequest(Request $request): bool
    {
        return self::enabled() && strcasecmp($request->getHost(), (string) self::host()) === 0;
    }

    /** A route's URL on the content host, or on the app when there is none. */
    public static function route(string $name, array $parameters = []): string
    {
        return self::enabled()
            ? rtrim((string) config('framework.content_url'), '/').route($name, $parameters, false)
            : route($name, $parameters);
    }

    /** Pages that may frame content: the app itself, wherever content is served. */
    public static function frameAncestors(): string
    {
        if (! self::enabled()) {
            return "frame-ancestors 'self'";
        }
        $app = parse_url((string) config('app.url'));
        $origin = isset($app['scheme'], $app['host']) ? $app['scheme'].'://'.$app['host'].(isset($app['port']) ? ':'.$app['port'] : '') : null;

        return "frame-ancestors 'self'".($origin ? ' '.$origin : '');
    }

    /** X-Frame-Options for content: same-origin framing on the app; none on the content host, which the app frames. */
    public static function frameOptions(): array
    {
        return self::enabled() ? [] : ['X-Frame-Options' => 'SAMEORIGIN'];
    }
}
