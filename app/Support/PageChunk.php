<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * The Vite entry for an Inertia page, so app.blade.php can preload it with
 * app.js. A page Vite folded into a shared chunk has no entry of its own;
 * asking @vite for it throws, so that page loads through app.js instead.
 */
class PageChunk
{
    private static ?array $manifest = null;

    public static function for(?string $component): array
    {
        if (! $component) {
            return [];
        }
        $entry = "resources/js/Pages/{$component}.vue";

        if (Vite::isRunningHot()) {
            return [$entry];
        }
        self::$manifest ??= (array) json_decode((string) @file_get_contents(public_path('build/manifest.json')), true);

        return isset(self::$manifest[$entry]) ? [$entry] : [];
    }
}
