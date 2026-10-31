<?php

namespace App\Support\Docs;

/**
 * The three docs sites: the R package (r), framework-py (py), and the fw
 * command line tool (cli). Each answers on its own host
 * (config framework.docs_hosts).
 */
class Products
{
    /** Product => the short label the product switcher shows */
    public const LABELS = [
        'r' => 'R',
        'py' => 'Python',
        'cli' => 'CLI',
    ];

    /** Product => its name where no site.yml title is at hand */
    public const NAMES = [
        'r' => 'Framework for R',
        'py' => 'Framework for Python',
        'cli' => 'Framework CLI',
    ];

    /** Stand-in for another product's origin in stored HTML; see absolutize() */
    public const PLACEHOLDER = 'fwdocs://';

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function exists(string $product): bool
    {
        return isset(self::LABELS[$product]);
    }

    public static function host(string $product): string
    {
        return (string) config("framework.docs_hosts.{$product}");
    }

    /**
     * The other product's address. Hosts are configuration, so stored HTML
     * keeps fwdocs://{product}/path and pages swap it in when they render.
     */
    public static function url(string $product, string $path = '/'): string
    {
        $scheme = request()->getScheme();
        $port = request()->getPort();
        $port = in_array($port, [80, 443, null], true) ? '' : ':'.$port;

        return $scheme.'://'.self::host($product).$port.'/'.ltrim($path, '/');
    }

    public static function absolutize(string $html): string
    {
        if (! str_contains($html, self::PLACEHOLDER)) {
            return $html;
        }

        return preg_replace_callback(
            '#'.preg_quote(self::PLACEHOLDER, '#').'(r|py|cli)(/[^"\'\s<]*)?#',
            fn ($m) => self::url($m[1], $m[2] ?? '/'),
            $html,
        );
    }
}
