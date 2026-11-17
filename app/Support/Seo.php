<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Structured data (schema.org JSON-LD) for public pages. Each method returns
 * one node; x-share-meta writes the nodes into a single @graph.
 */
class Seo
{
    public const SITE_NAME = 'Framework';

    public static function home(): string
    {
        return rtrim(config('app.url'), '/').'/';
    }

    /** @return array<string, mixed> */
    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => self::home().'#organization',
            'name' => self::SITE_NAME,
            'url' => self::home(),
            'logo' => rtrim(config('app.url'), '/').'/images/logo-80.webp',
            'sameAs' => ['https://github.com/frameworktools'],
        ];
    }

    /** @return array<string, mixed> */
    public static function website(?string $name = null, ?string $url = null): array
    {
        $url ??= self::home();

        return [
            '@type' => 'WebSite',
            '@id' => $url.'#website',
            'name' => $name ?? self::SITE_NAME,
            'url' => $url,
            'publisher' => ['@id' => self::home().'#organization'],
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $trail  [name, url] pairs, outermost first
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect(array_values($trail))->map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb[0], 'item' => $crumb[1],
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function person(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $person = ['@type' => 'Person', 'name' => $user->name];
        if ($user->handle && $user->profile_public) {
            $person['url'] = route('profile', $user->handle);
        }

        return $person;
    }

    public static function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    /**
     * The nodes as one JSON-LD document, safe inside a script element. Built
     * here, not in Blade, where '@context' would compile as a directive.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function json(array $nodes): string
    {
        return json_encode(['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }

    /** The first paragraph of rendered HTML, as a meta description */
    public static function lead(?string $html): ?string
    {
        return preg_match('#<p\b[^>]*>(.*?)</p>#is', (string) $html, $m)
            ? self::describe(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;
    }

    /** Plain text for a meta description: no tags, one line, about 160 characters. */
    public static function describe(?string $text, int $limit = 160): ?string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        return $text === '' ? null : Str::limit($text, $limit - 1, '…');
    }

    /**
     * Drops null and empty values, so optional fields never reach the page as nulls.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function clean(array $node): array
    {
        return array_filter(
            array_map(fn ($v) => is_array($v) && ! array_is_list($v) ? self::clean($v) : $v, $node),
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}
