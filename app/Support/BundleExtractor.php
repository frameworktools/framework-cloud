<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Extracts an uploaded render bundle (zip of a Quarto/RMarkdown output:
 * entry HTML + its asset directory) into a storage prefix, defensively:
 * path traversal, absolute paths, symlink-ish names, zip bombs, and
 * oversized bundles are all rejected before a single byte is stored.
 */
class BundleExtractor
{
    public const MAX_FILES = 4000;

    public const MAX_UNCOMPRESSED_BYTES = 209_715_200; // 200 MB

    /**
     * @return array{files: int, bytes: int}
     */
    public static function extract(UploadedFile $zip, string $disk, string $prefix): array
    {
        $archive = new ZipArchive;

        if ($archive->open($zip->getRealPath()) !== true) {
            throw new RuntimeException('Could not read the bundle zip.');
        }

        if ($archive->numFiles > self::MAX_FILES) {
            $archive->close();
            throw new RuntimeException('Bundle has too many files.');
        }

        // Validate everything before storing anything
        $total = 0;
        $entries = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $stat = $archive->statIndex($i);
            $name = $stat['name'];

            if (str_ends_with($name, '/')) {
                continue; // directory entry
            }

            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name)) {
                $archive->close();
                throw new RuntimeException("Unsafe path in bundle: {$name}");
            }

            $total += $stat['size'];
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                $archive->close();
                throw new RuntimeException('Bundle exceeds the uncompressed size limit.');
            }

            $entries[] = $name;
        }

        foreach ($entries as $name) {
            $stream = $archive->getStream($name);
            if ($stream === false) {
                $archive->close();
                throw new RuntimeException("Could not read bundle entry: {$name}");
            }
            Storage::disk($disk)->put($prefix.'/'.$name, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $archive->close();

        return ['files' => count($entries), 'bytes' => $total];
    }

    /**
     * Best-effort content type from a bundle path.
     */
    public static function contentType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'html', 'htm' => 'text/html; charset=utf-8',
            'js', 'mjs' => 'text/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'map' => 'application/json',
            'txt' => 'text/plain; charset=utf-8',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
