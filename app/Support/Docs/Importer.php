<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Imports a product's docs site from docs-site.tar.gz, a docs-site folder,
 * or the newest GitHub release of the product's repository. The archive
 * holds one top folder, docs-site/.
 */
class Importer
{
    public const ASSET = 'docs-site.tar.gz';

    public const MAX_ARCHIVE_BYTES = 50 * 1024 * 1024;

    /** @var list<string> */
    public array $warnings = [];

    /**
     * @return array{release: string, guides: int, entries: int, images: int}
     */
    public function import(string $product, string $source): array
    {
        if (! Products::exists($product)) {
            throw new InvalidSite(["Unknown product \"{$product}\". Use r, py, or cli."]);
        }

        $work = $this->workDir();

        try {
            $dir = is_dir($source) ? $this->folder($source) : $this->extract($source, $work);

            $compiler = new Compiler(Store::linkTargets());
            $compiled = $compiler->compile($dir, $product);
            $this->warnings = $compiler->warnings;

            $release = Store::write($product, $compiled, $dir);
        } finally {
            File::deleteDirectory($work);
        }

        return [
            'release' => $release,
            'guides' => count($compiled['site']['guides']),
            'entries' => count($compiled['site']['entries']),
            'images' => count($compiled['images']),
        ];
    }

    /**
     * Download docs-site.tar.gz from the product's GitHub releases and import
     * it. Which release depends on the repository's prerelease setting:
     * "fallback" takes the newest stable release with the asset, or the
     * newest pre-release with it when no stable release has one; "always"
     * takes the newest release of either kind; "never" only stable ones.
     *
     * @return array{release: string, guides: int, entries: int, images: int, tag: string, prerelease: bool}
     */
    public function importRelease(string $product): array
    {
        $config = config("framework.docs_repositories.{$product}");
        $repository = is_array($config) ? ($config['repository'] ?? null) : $config;
        $mode = is_array($config) ? ($config['prerelease'] ?? 'fallback') : 'fallback';

        if (! is_string($repository) || $repository === '') {
            throw new InvalidSite(["No repository is set for product \"{$product}\"."]);
        }

        $response = Http::acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(30)
            ->get("https://api.github.com/repos/{$repository}/releases", ['per_page' => 30]);
        if ($response->failed()) {
            throw new RuntimeException("Could not list the releases of {$repository} (HTTP {$response->status()}).");
        }

        $release = self::pickRelease((array) $response->json(), (string) $mode);
        if ($release === null) {
            throw new InvalidSite(["No release of {$repository} has ".self::ASSET.($mode === 'never' ? ' outside pre-releases.' : '.')]);
        }

        $asset = collect($release['assets'])->firstWhere('name', self::ASSET);
        $download = Http::timeout(120)->get($asset['browser_download_url']);
        if ($download->failed()) {
            throw new RuntimeException("Could not download {$asset['browser_download_url']} (HTTP {$download->status()}).");
        }

        $work = $this->workDir();
        try {
            $file = "{$work}/".self::ASSET;
            File::put($file, $download->body());

            return [
                ...$this->import($product, $file),
                'tag' => (string) ($release['tag_name'] ?? ''),
                'prerelease' => (bool) ($release['prerelease'] ?? false),
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * GitHub lists releases newest first.
     *
     * @param  list<array<string, mixed>>  $releases
     * @return array<string, mixed>|null
     */
    public static function pickRelease(array $releases, string $mode = 'fallback'): ?array
    {
        $usable = collect($releases)
            ->filter(fn ($r) => is_array($r) && ! ($r['draft'] ?? false))
            ->filter(fn ($r) => collect($r['assets'] ?? [])->contains('name', self::ASSET))
            ->values();

        $stable = $usable->first(fn ($r) => ! ($r['prerelease'] ?? false));

        return match ($mode) {
            'always' => $usable->first(),
            'never' => $stable,
            default => $stable ?? $usable->first(),
        };
    }

    /** A folder given directly: docs-site/ itself, or a folder holding it */
    private function folder(string $source): string
    {
        $source = rtrim(realpath($source) ?: $source, '/');

        if (is_file("{$source}/site.yml")) {
            return $source;
        }
        if (is_file("{$source}/docs-site/site.yml")) {
            return "{$source}/docs-site";
        }

        throw new InvalidSite(["{$source} has no site.yml."]);
    }

    private function extract(string $archive, string $work): string
    {
        if (! is_file($archive)) {
            throw new InvalidSite(["{$archive} is not a file or folder."]);
        }
        if (filesize($archive) > self::MAX_ARCHIVE_BYTES) {
            throw new InvalidSite(["{$archive} is larger than 50 MB."]);
        }

        // PharData picks the format from the name, so give it a plain one
        $copy = "{$work}/archive.tar.gz";
        copy($archive, $copy);
        $target = "{$work}/extracted";

        try {
            (new PharData($copy))->extractTo($target);
        } catch (\Throwable $e) {
            throw new InvalidSite(["{$archive} is not a readable .tar.gz archive: {$e->getMessage()}"]);
        }

        foreach (Finder::create()->in($target)->ignoreDotFiles(false) as $file) {
            if ($file->isLink()) {
                throw new InvalidSite(['The archive contains a symbolic link ('.$file->getRelativePathname().').']);
            }
        }

        $top = array_map('basename', File::directories($target));
        $loose = array_map(fn ($f) => $f->getFilename(), File::files($target));
        if ($top !== ['docs-site'] || $loose !== []) {
            throw new InvalidSite(['The archive must hold a single top folder, docs-site/.']);
        }

        return "{$target}/docs-site";
    }

    private function workDir(): string
    {
        $dir = storage_path('framework/cache/docs-import-'.Str::lower(Str::random(10)));
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
