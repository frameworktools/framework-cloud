<?php

namespace App\Support;

use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Phiz\Assets;
use Phiz\Html;

/**
 * A Vega-Lite artifact's chart page: the spec inlined into a small HTML page
 * that draws it. Built once, when the version is published, and stored beside
 * the spec, so the content Worker serves it; the app never builds it per view.
 * Dark mode comes from ?dark=1, read in the page.
 */
class ChartPage
{
    public const FILENAME = 'chart.html';

    /** The sandbox the chart draws in: scripts from the pinned chart libraries only, no network. */
    public const CSP = "sandbox allow-scripts; default-src 'none'; script-src https://cdn.jsdelivr.net 'unsafe-inline' 'unsafe-eval'; style-src 'unsafe-inline'; img-src data: blob:; font-src data:";

    public static function path(DocumentVersion $version): ?string
    {
        return $version->isBundle() || ! $version->disk_path || ! str_ends_with($version->disk_path, '/content')
            || ! ArtifactPreview::isVegaLite($version->original_filename)
            ? null : dirname($version->disk_path).'/'.self::FILENAME;
    }

    /** The page's HTML, or null when the spec isn't a chart. */
    public static function render(DocumentVersion $version, bool $dark = false): ?string
    {
        $spec = ArtifactPreview::vegaLiteSpec($version);
        if ($spec === null) {
            return null;
        }

        return view('documents.chart', [
            'container' => Html::container($spec, ['actions' => false, 'theme' => 'auto', 'config' => ['background' => 'transparent']], ['style' => 'width:100%']),
            'scripts' => Assets::cdn()->scripts(),
            'dark' => $dark,
        ])->render();
    }

    /** Builds and stores the page. False when the version has no chart. */
    public static function store(DocumentVersion $version): bool
    {
        $path = self::path($version);
        $html = $path ? self::render($version) : null;
        if ($html === null) {
            return false;
        }
        if (! Storage::disk($version->disk())->put($path, $html)) {
            return false;
        }
        Cache::forever(self::cacheKey($version), true);

        return true;
    }

    /** Whether the stored page is there, building it first for versions published before pages were stored. */
    public static function ensure(DocumentVersion $version): bool
    {
        if (self::path($version) === null) {
            return false;
        }

        return Cache::get(self::cacheKey($version)) === true || self::store($version);
    }

    private static function cacheKey(DocumentVersion $version): string
    {
        return "artifact-chart-page:{$version->id}:".md5($version->disk().':'.$version->disk_path);
    }
}
