<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imported docs sites on disk, one folder per product:
 *
 *   {docs_path}/{product}/releases/{name}/site.json     the compiled site
 *   {docs_path}/{product}/releases/{name}/search.json   the search index
 *   {docs_path}/{product}/releases/{name}/images/       the site's images
 *   {docs_path}/{product}/current                        the live release's name
 *
 * A new release is written beside the live one and goes live when the
 * current file is replaced by rename, so readers see the old site or the
 * new one and never a half-written folder. The previous release is kept.
 */
class Store
{
    /** @var array<string, array<string, mixed>> release folder => decoded site */
    private static array $sites = [];

    public static function root(): string
    {
        return rtrim((string) config('framework.docs_path'), '/');
    }

    public static function release(string $product): ?string
    {
        $pointer = self::root()."/{$product}/current";
        if (! is_file($pointer)) {
            return null;
        }

        $name = trim((string) file_get_contents($pointer));
        $dir = self::root()."/{$product}/releases/{$name}";

        return $name !== '' && ! str_contains($name, '/') && is_file("{$dir}/site.json") ? $dir : null;
    }

    /** @return array<string, mixed>|null */
    public static function site(string $product): ?array
    {
        $dir = self::release($product);
        if ($dir === null) {
            return null;
        }

        return self::$sites[$dir] ??= json_decode((string) file_get_contents("{$dir}/site.json"), true);
    }

    /** @return list<array<string, string>>|null */
    public static function search(string $product): ?array
    {
        $dir = self::release($product);

        return $dir !== null && is_file("{$dir}/search.json")
            ? json_decode((string) file_get_contents("{$dir}/search.json"), true)
            : null;
    }

    public static function image(string $product, string $path): ?string
    {
        $dir = self::release($product);
        if ($dir === null || str_contains($path, '..')) {
            return null;
        }

        $file = "{$dir}/images/{$path}";

        return is_file($file) && ! is_link($file) ? $file : null;
    }

    /**
     * What the other imported products can be linked to, for Links.
     *
     * @return array<string, array{guides: array<string, list<string>>, entries: array<string, list<string>>}>
     */
    public static function linkTargets(): array
    {
        $targets = [];
        foreach (Products::all() as $product) {
            $site = self::site($product);
            if ($site === null) {
                continue;
            }
            $targets[$product] = [
                'guides' => array_map(fn ($g) => array_column($g['toc'], 'id'), $site['guides']),
                'entries' => array_map(fn () => [], $site['entries']),
            ];
        }

        return $targets;
    }

    /**
     * Write a compiled site as a new release and make it live.
     *
     * @param  array{site: array<string, mixed>, search: list<array<string, string>>, images: list<string>}  $compiled
     * @return string the release's name
     */
    public static function write(string $product, array $compiled, string $sourceDir): string
    {
        $base = self::root()."/{$product}";
        $releases = "{$base}/releases";
        File::ensureDirectoryExists($releases);

        $name = now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $staging = "{$releases}/.staging-{$name}";

        try {
            File::ensureDirectoryExists("{$staging}/images");
            self::put("{$staging}/site.json", json_encode($compiled['site'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            self::put("{$staging}/search.json", json_encode($compiled['search'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            foreach ($compiled['images'] as $image) {
                $target = "{$staging}/images/{$image}";
                File::ensureDirectoryExists(dirname($target));
                if (! copy("{$sourceDir}/images/{$image}", $target)) {
                    throw new RuntimeException("Could not copy images/{$image}.");
                }
            }

            if (! rename($staging, "{$releases}/{$name}")) {
                throw new RuntimeException("Could not move the new release into {$releases}.");
            }
        } catch (\Throwable $e) {
            File::deleteDirectory($staging);

            throw $e;
        }

        $previous = self::release($product);

        // The swap: rename() replaces the pointer in one step
        $pointer = "{$base}/current.".Str::random(8);
        self::put($pointer, $name."\n");
        if (! rename($pointer, "{$base}/current")) {
            @unlink($pointer);
            File::deleteDirectory("{$releases}/{$name}");

            throw new RuntimeException("Could not make release {$name} live.");
        }

        // Keep the new release and the one before it
        $keep = array_filter([$name, $previous !== null ? basename($previous) : null]);
        foreach (File::directories($releases) as $dir) {
            if (! in_array(basename($dir), $keep, true)) {
                File::deleteDirectory($dir);
            }
        }

        self::$sites = [];

        return $name;
    }

    public static function flush(): void
    {
        self::$sites = [];
    }

    private static function put(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Could not write {$path}.");
        }
    }
}
