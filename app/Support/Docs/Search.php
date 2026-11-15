<?php

namespace App\Support\Docs;

use Illuminate\Support\Str;

/**
 * The search index for one product: guide titles, descriptions, and h2/h3
 * headings with the text under each, and reference names, summaries,
 * parameter names, and descriptions. The ⌘K box loads every product's index
 * at once (SearchIndex); /search ranks one product's on the server.
 */
class Search
{
    /** Bump when the items change shape, so cached indexes are rebuilt */
    public const VERSION = 2;

    /** The most text kept from one section or entry */
    private const BODY_LIMIT = 4000;

    /**
     * @param  array<string, mixed>  $site  a compiled site
     * @return list<array{type: string, title: string, url: string, text: string, group: string, keywords: string, body: string}>
     */
    public static function build(array $site): array
    {
        $sectionTitles = collect($site['sections'])->pluck('title', 'id');
        $categoryTitles = collect($site['categories'])->pluck('title', 'id');
        $items = [];

        foreach ($site['sections'] as $section) {
            foreach ($section['guides'] as $id) {
                $guide = $site['guides'][$id];
                $group = $sectionTitles[$guide['section']] ?? '';
                array_push($items, ...self::page($guide['title'], '/guides/'.$id, $guide['description'], $group, (string) ($guide['html'] ?? '')));
            }
        }

        foreach ($site['entries'] as $entry) {
            $items[] = [
                'type' => 'entry',
                'title' => $entry['name'],
                'url' => '/reference/'.$entry['id'],
                'text' => $entry['summary'],
                'group' => $categoryTitles[$entry['category']] ?? '',
                'keywords' => implode(' ', [
                    ...array_column($entry['parameters'], 'name'),
                    ...$entry['aliases'],
                    ...array_filter([$entry['title']]),
                ]),
                'body' => self::limit(implode(' ', array_map(
                    fn ($part) => is_string($part) ? self::plain($part) : '',
                    [$entry['description'] ?? '', $entry['details'] ?? '', $entry['returns'] ?? ''],
                ))),
                'kind' => $entry['kind'],
            ];
        }

        return $items;
    }

    /**
     * A page and its h2/h3 sections, each with the text under it.
     *
     * @return list<array<string, string>>
     */
    public static function page(string $title, string $url, string $description, string $group, string $html): array
    {
        $parts = preg_split('#<h([23])\b[^>]*\bid="([^"]+)"[^>]*>(.*?)</h\1>#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $items = [[
            'type' => 'guide',
            'title' => $title,
            'url' => $url,
            'text' => $description,
            'group' => $group,
            'keywords' => '',
            'body' => self::limit(self::plain($parts[0])),
        ]];

        for ($i = 1; $i < count($parts); $i += 4) {
            $items[] = [
                'type' => 'heading',
                'title' => self::plain($parts[$i + 2]),
                'url' => $url.'#'.$parts[$i + 1],
                'text' => $title,
                'group' => $group,
                'keywords' => '',
                'body' => self::limit(self::plain($parts[$i + 3] ?? '')),
            ];
        }

        return $items;
    }

    /** Readable text from HTML: block tags become spaces, entities are decoded */
    public static function plain(string $html): string
    {
        $html = preg_replace('#<(script|style|svg)\b.*?</\1>#si', ' ', $html);
        $html = preg_replace('#</?(p|div|li|ul|ol|h[1-6]|pre|br|tr|td|th|table|thead|tbody|blockquote|dl|dt|dd|section|figure|figcaption)\b[^>]*>#i', ' $0 ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function limit(string $text): string
    {
        return Str::limit($text, self::BODY_LIMIT, '');
    }

    /**
     * Rank the index for a query: an exact title first, then titles that
     * start with it, then titles, keywords, and descriptions that contain it.
     *
     * @param  list<array<string, string>>  $index
     * @return list<array<string, string>>
     */
    public static function query(array $index, string $query, int $limit = 30): array
    {
        $query = Str::lower(trim($query));
        if ($query === '') {
            return [];
        }

        $words = preg_split('/\s+/', $query);
        $scored = [];

        foreach ($index as $i => $item) {
            $title = Str::lower($item['title']);
            $haystack = $title.' '.Str::lower($item['keywords'].' '.$item['text'].' '.($item['body'] ?? ''));

            if (! collect($words)->every(fn ($w) => str_contains($haystack, $w))) {
                continue;
            }

            $score = match (true) {
                $title === $query => 100,
                str_starts_with($title, $query) => 80,
                str_contains($title, $query) => 60,
                str_contains(Str::lower($item['keywords']), $query) => 40,
                str_contains(Str::lower($item['text']), $query) => 20,
                default => 10,
            };
            // Pages before the headings inside them
            $score += $item['type'] === 'heading' ? 0 : 5;

            $scored[] = [$score, $i, $item];
        }

        usort($scored, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(fn ($s) => $s[2], array_slice($scored, 0, $limit));
    }
}
