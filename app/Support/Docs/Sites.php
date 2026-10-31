<?php

namespace App\Support\Docs;

/**
 * What the docs pages read: the imported site for a product, or, for R
 * until its first import, the site built from the legacy sources.
 */
class Sites
{
    /** @return array<string, mixed>|null */
    public static function site(string $product): ?array
    {
        return Store::site($product) ?? ($product === 'r' ? LegacyR::compiled()['site'] : null);
    }

    /** @return list<array<string, string>> */
    public static function search(string $product): array
    {
        if (Store::release($product) !== null) {
            return Store::search($product) ?? [];
        }

        return $product === 'r' ? LegacyR::compiled()['search'] : [];
    }

    public static function image(string $product, string $path): ?string
    {
        if (Store::release($product) !== null) {
            return Store::image($product, $path);
        }

        return $product === 'r' ? LegacyR::image($path) : null;
    }

    public static function imported(string $product): bool
    {
        return Store::release($product) !== null;
    }
}
