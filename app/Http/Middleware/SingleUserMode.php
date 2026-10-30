<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single-user mode (FW_SINGLE_USER=true): a server running on someone's own
 * computer signs every local request in as the admin, with no sign-in
 * screen.
 *
 * A request counts as local only when all of these hold:
 * - APP_URL is a localhost address
 * - the connection comes from FW_SINGLE_USER_NETWORKS (loopback by default),
 *   read from REMOTE_ADDR, never from proxy headers
 * - it carries no forwarding headers, so tunnels such as Expose, which
 *   connect from loopback, don't count
 * - it is addressed to a localhost host name
 */
class SingleUserMode
{
    private const FORWARDING_HEADERS = ['X-Forwarded-For', 'X-Forwarded-Host', 'X-Real-IP', 'Forwarded'];

    public function handle(Request $request, Closure $next): Response
    {
        if (self::appliesTo($request) && ! Auth::guard('web')->check() && $user = self::owner()) {
            Auth::guard('web')->login($user, remember: true);
        }

        return $next($request);
    }

    public static function appliesTo(Request $request): bool
    {
        if (! config('framework.single_user') || ! self::isLoopbackHost(parse_url((string) config('app.url'), PHP_URL_HOST))) {
            return false;
        }

        foreach (self::FORWARDING_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        $address = (string) $request->server('REMOTE_ADDR');

        return IpUtils::checkIp($address, config('framework.single_user_networks'))
            && self::isLoopbackHost($request->getHost());
    }

    /**
     * The account single-user mode signs in: FW_ADMIN_EMAIL, or else the
     * first admin.
     */
    public static function owner(): ?User
    {
        $email = config('framework.admin_email');

        return ($email ? User::where('email', strtolower($email))->first() : null)
            ?? User::where('is_admin', true)->orderBy('id')->first();
    }

    private static function isLoopbackHost(?string $host): bool
    {
        $host = strtolower(trim((string) $host, '[]'));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
    }
}
