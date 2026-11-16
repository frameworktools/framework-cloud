<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\Cache;

/**
 * What the docs pages read: the imported site for a product, or, for R
 * until its first import, the site built from the legacy sources.
 */
class Sites
{
    /**
     * A guide's name in the menus and lists. The guide that shares its
     * section's title (Project types under Project types) introduces the whole
     * section, so it reads "All project types" there.
     *
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $section
     */
    public static function navTitle(array $site, array $section, string $id): string
    {
        $title = (string) ($site['guides'][$id]['title'] ?? $id);

        $sectionTitle = trim((string) ($section['title'] ?? ''));

        return strcasecmp(trim($title), $sectionTitle) === 0 ? 'All '.mb_strtolower($sectionTitle) : $title;
    }

    /** A guide's address: /guides/{id} on a docs host, or the guide's own (the Cloud guide's /docs pages) */
    public static function guideUrl(array $site, string $id): string
    {
        return (string) ($site['guides'][$id]['url'] ?? '/guides/'.$id);
    }

    /** @return array<string, mixed>|null */
    public static function site(string $product): ?array
    {
        return Store::site($product) ?? ($product === 'r' ? LegacyR::compiled()['site'] : null);
    }

    /**
     * A product's search items, built from its site so an index format change
     * (Search::VERSION) needs no new import.
     *
     * @return list<array<string, string>>
     */
    public static function search(string $product): array
    {
        $release = Store::release($product);
        if ($release === null) {
            static $legacy = null;

            return $product === 'r' ? $legacy ??= Search::build(LegacyR::compiled()['site']) : [];
        }

        return Cache::rememberForever(
            'docs-search:'.Search::VERSION.":{$product}:".basename($release),
            fn () => Search::build(Store::site($product)),
        );
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
