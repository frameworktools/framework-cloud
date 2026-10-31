<?php

namespace App\Support\Docs;

use Illuminate\Support\Str;

/**
 * The search index for one product: guide titles, descriptions, and h2/h3
 * headings, and reference names, summaries, and parameter names. The ⌘K
 * box loads it as /search.json; /search ranks it on the server.
 */
class Search
{
    /**
     * @param  array<string, mixed>  $site  a compiled site
     * @return list<array{type: string, title: string, url: string, text: string, group: string, keywords: string}>
     */
    public static function build(array $site): array
    {
        $sectionTitles = collect($site['sections'])->pluck('title', 'id');
        $categoryTitles = collect($site['categories'])->pluck('title', 'id');
        $items = [];

        foreach ($site['sections'] as $section) {
            foreach ($section['guides'] as $id) {
                $guide = $site['guides'][$id];
                $items[] = [
                    'type' => 'guide',
                    'title' => $guide['title'],
                    'url' => '/guides/'.$id,
                    'text' => $guide['description'],
                    'group' => $sectionTitles[$guide['section']] ?? '',
                    'keywords' => '',
                ];
                foreach ($guide['toc'] as $heading) {
                    $items[] = [
                        'type' => 'heading',
                        'title' => $heading['text'],
                        'url' => '/guides/'.$id.'#'.$heading['id'],
                        'text' => $guide['title'],
                        'group' => $sectionTitles[$guide['section']] ?? '',
                        'keywords' => '',
                    ];
                }
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
                'kind' => $entry['kind'],
            ];
        }

        return $items;
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
            $haystack = $title.' '.Str::lower($item['keywords'].' '.$item['text']);

            if (! collect($words)->every(fn ($w) => str_contains($haystack, $w))) {
                continue;
            }

            $score = match (true) {
                $title === $query => 100,
                str_starts_with($title, $query) => 80,
                str_contains($title, $query) => 60,
                str_contains(Str::lower($item['keywords']), $query) => 40,
                default => 20,
            };
            // Pages before the headings inside them
            $score += $item['type'] === 'heading' ? 0 : 5;

            $scored[] = [$score, $i, $item];
        }

        usort($scored, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(fn ($s) => $s[2], array_slice($scored, 0, $limit));
    }
}
