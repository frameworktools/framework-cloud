<?php

namespace App\Support;

use App\Models\DocumentVersion;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Phiz\Plot;
use Throwable;

/**
 * A figure's phiz plot description, figure.phiz.json, published beside its SVG
 * or PNG. phiz compiles it to Vega-Lite, so the figure also draws as a live
 * chart. The description is data only; phiz refuses raw Vega-Lite,
 * expressions, and URLs. It is never served as is.
 *
 * Compiled once per version and phiz release, when the version is published,
 * and kept. A description phiz refuses leaves the figure an image.
 */
class PlotDescription
{
    public const FILENAME = 'figure.phiz.json';

    /** The largest description kept, as for a Vega-Lite file. */
    public const MAX_BYTES = ArtifactPreview::MAX_CHART_BYTES;

    /** Figures a description may accompany. */
    public const FIGURE_TYPES = ['image/svg+xml', 'image/png'];

    /**
     * Config keys that set the chart's chrome: text, axis, grid, panel, and default mark colors, fonts, and the
     * background. The embedding page sets these. Palettes (range) stay: they encode data, as in the figure.
     */
    private const THEME_KEYS = ['background', 'color', 'fill', 'stroke', 'font'];

    /** Where a version's description is stored, or null when it cannot have one. */
    public static function path(DocumentVersion $version): ?string
    {
        return $version->isBundle() || ! $version->disk_path || ! str_ends_with($version->disk_path, '/content')
            || ! in_array(ArtifactEmbed::contentType($version), self::FIGURE_TYPES, true)
            ? null : dirname($version->disk_path).'/'.self::FILENAME;
    }

    /**
     * The compiled Vega-Lite specification, with phiz's theme, or null when
     * the version has no description or phiz refuses it. The chart page draws this.
     *
     * @return array<string, mixed>|null
     */
    public static function spec(DocumentVersion $version): ?array
    {
        if (self::path($version) === null) {
            return null;
        }
        if (! $version->exists) {
            return self::compile($version) ?: null;
        }
        $key = 'artifact-phiz:'.self::phizVersion().":{$version->id}:".md5($version->disk().':'.$version->disk_path);
        if (Cache::has($key)) {
            return Cache::get($key) ?: null;
        }
        $spec = self::compile($version);
        // A failed read is tried again on the next request; anything else is kept
        if ($spec !== null) {
            Cache::forever($key, $spec);
        }

        return $spec ?: null;
    }

    /**
     * The specification an embed draws: without phiz's chrome colors, fonts,
     * and background, so the page's theme wins, and without phiz's own dark
     * theme. Null when there is none or it would load anything from elsewhere.
     *
     * @return array<string, mixed>|null
     */
    public static function embedSpec(DocumentVersion $version): ?array
    {
        $spec = self::spec($version);
        if ($spec === null) {
            return null;
        }
        $embed = self::forEmbed($spec);
        if ($embed === null) {
            Log::warning('A compiled plot description reaches other hosts; the figure embeds as an image.', ['version_id' => $version->id]);
        }

        return $embed;
    }

    /**
     * A compiled spec as embeds receive it, or null when it would load data,
     * images, or links from elsewhere.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    public static function forEmbed(array $spec): ?array
    {
        unset($spec['usermeta'], $spec['background']);
        $config = self::layoutOnly(is_array($spec['config'] ?? null) ? $spec['config'] : []);
        unset($spec['config']);
        if ($config !== []) {
            $spec['config'] = $config;
        }

        return ArtifactEmbed::selfContained($spec) ? $spec : null;
    }

    /** The description's text for screen readers, when it has one. */
    public static function text(DocumentVersion $version): ?string
    {
        $text = self::spec($version)['description'] ?? null;

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /**
     * A config with its chrome colors, fonts, and background removed, keeping
     * sizes, weights, layout, and palettes. Empty groups are dropped.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function layoutOnly(array $config): array
    {
        $kept = [];
        foreach ($config as $key => $value) {
            if (is_string($key) && (in_array($key, self::THEME_KEYS, true) || preg_match('/(Color|Font)$/', $key))) {
                continue;
            }
            if (is_array($value) && ! array_is_list($value)) {
                $value = self::layoutOnly($value);
                if ($value === []) {
                    continue;
                }
            }
            $kept[$key] = $value;
        }

        return $kept;
    }

    /**
     * The compiled spec, false when there is no usable description, or null
     * when storage could not be read.
     *
     * @return array<string, mixed>|false|null
     */
    private static function compile(DocumentVersion $version): array|false|null
    {
        $disk = Storage::disk($version->disk());
        $path = self::path($version);
        try {
            if (! $disk->exists($path)) {
                return false;
            }
            if ($disk->size($path) > self::MAX_BYTES) {
                Log::warning('A plot description is too large to compile; the figure stays an image.', ['version_id' => $version->id]);

                return false;
            }
            $json = $disk->get($path);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        if (! is_string($json)) {
            return null;
        }
        try {
            return Plot::fromJson($json)->width('container')->toArray();
        } catch (Throwable $e) {
            // phiz names the place in the description; a refused one leaves the figure an image
            Log::warning('A plot description could not be compiled; the figure stays an image.', [
                'version_id' => $version->id, 'error' => $e::class.': '.$e->getMessage(),
            ]);

            return false;
        }
    }

    private static function phizVersion(): string
    {
        try {
            return (string) (InstalledVersions::getReference('phpstats/phiz') ?? InstalledVersions::getVersion('phpstats/phiz'));
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
