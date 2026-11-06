<?php

namespace App\Support;

/**
 * Which proxies may report the visitor's address and https in X-Forwarded-*
 * headers (TRUSTED_PROXIES). Empty trusts loopback only: a local web server or
 * tunnel. "*" trusts any. "cloudflare" trusts Cloudflare's edge, for a site
 * behind its proxy, so rate limits and logs see visitors, not Cloudflare.
 * Otherwise, a comma-separated list of addresses and CIDR ranges, which may
 * also include "cloudflare".
 */
class TrustedProxies
{
    /** https://www.cloudflare.com/ips-v4 and ips-v6, checked 2026-11-06. */
    public const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public const LOOPBACK = ['127.0.0.1', '::1'];

    /** @return array<int, string>|string */
    public static function resolve(?string $setting): array|string
    {
        $setting = trim((string) $setting);
        if ($setting === '*') {
            return '*';
        }
        if ($setting === '') {
            return self::LOOPBACK;
        }

        $entries = array_values(array_filter(array_map('trim', explode(',', $setting))));

        return array_values(array_unique([...self::LOOPBACK, ...array_merge(...array_map(
            fn (string $entry) => strtolower($entry) === 'cloudflare' ? self::CLOUDFLARE : [$entry],
            $entries,
        ))]));
    }
}
