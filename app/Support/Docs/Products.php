<?php

namespace App\Support\Docs;

/**
 * The docs sites: the R package (r), framework-py (py), the fw command
 * line tool (cli), and the Stata package (stata). Each answers on its own host
 * (config framework.docs_hosts).
 */
class Products
{
    /** Product => the short label the product switcher shows */
    public const LABELS = [
        'r' => 'R',
        'py' => 'Python',
        'stata' => 'Stata',
        'cli' => 'CLI',
    ];

    /** Product => its name where no site.yml title is at hand */
    public const NAMES = [
        'r' => 'Framework for R',
        'py' => 'Framework for Python',
        'stata' => 'Framework for Stata',
        'cli' => 'Framework CLI',
    ];

    /** Stand-in for another product's origin in stored HTML; see absolutize() */
    public const PLACEHOLDER = 'fwdocs://';

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * The products the switcher and footer link to. Stata joins once its
     * package is public (framework.stata_package), its docs are imported,
     * or it is the page's own product.
     *
     * @return array<string, string> product => label
     */
    public static function listed(?string $current = null): array
    {
        $stata = $current === 'stata' || config('framework.stata_package') || Store::release('stata') !== null;

        return array_filter(self::LABELS, fn ($product) => $product !== 'stata' || $stata, ARRAY_FILTER_USE_KEY);
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

    /** Public marketing links must remain usable outside a local checkout. */
    public static function publicUrl(string $product, string $path = '/'): string
    {
        if (str_ends_with(self::host($product), '.test')) {
            return 'https://'.$product.'.framework.pub/'.ltrim($path, '/');
        }

        return self::url($product, $path);
    }

    public static function absolutize(string $html): string
    {
        if (! str_contains($html, self::PLACEHOLDER)) {
            return $html;
        }

        return preg_replace_callback(
            '#'.preg_quote(self::PLACEHOLDER, '#').'(r|py|cli|stata)(/[^"\'\s<]*)?#',
            fn ($m) => self::url($m[1], $m[2] ?? '/'),
            $html,
        );
    }
}
