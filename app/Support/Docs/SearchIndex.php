<?php

namespace App\Support\Docs;

/**
 * The index the ⌘K box loads: every listed product's docs and the Framework
 * Cloud guide in one list. Items keep their paths; origins says where each
 * product's pages live, so the index reads the same from any host.
 */
class SearchIndex
{
    public const CLOUD = 'cloud';

    /**
     * @param  string|null  $current  the docs site asking, which lists Stata even before release
     * @param  string|null  $cloud  where the Cloud guide lives; framework.pub passes its own root
     * @return array{origins: array<string, string>, labels: array<string, string>, items: list<array<string, string>>}
     */
    public static function payload(?string $current = null, ?string $cloud = null): array
    {
        $products = array_keys(Products::listed($current));
        $origins = [];
        $items = [];

        foreach ($products as $product) {
            $origins[$product] = rtrim(Products::url($product), '/');
            foreach (Sites::search($product) as $item) {
                $items[] = ['product' => $product] + $item;
            }
        }

        $origins[self::CLOUD] = rtrim($cloud ?? (string) config('app.url'), '/');
        foreach (CloudGuide::search() as $item) {
            $items[] = ['product' => self::CLOUD] + $item;
        }

        return [
            'origins' => $origins,
            'labels' => array_intersect_key(Products::LABELS, $origins) + [self::CLOUD => 'Cloud'],
            'items' => $items,
        ];
    }
}
