<?php

namespace App\Support;

use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ArtifactPreview
{
    private const MAX_BYTES = 1_048_576;

    /** Vega-Lite specifications larger than this are offered as a download. */
    public const MAX_CHART_BYTES = 8_388_608;

    /** Charts from altair and vegawidget arrive as figure.vl.json. */
    public static function isVegaLite(?string $filename): bool
    {
        return str_ends_with(strtolower($filename ?? ''), '.vl.json');
    }

    /** A Vega-Lite specification, or null when the file isn't one. */
    public static function vegaLiteSpec(DocumentVersion $version): ?array
    {
        if (! $version->exists || ! $version->disk_path) {
            return self::readVegaLiteSpec($version);
        }
        // Kept like the preview; null is kept as false
        $spec = self::remember(self::cacheKey('chart', $version), fn () => self::readVegaLiteSpec($version) ?? false);

        return $spec === false ? null : $spec;
    }

    private static function readVegaLiteSpec(DocumentVersion $version): ?array
    {
        $stream = Storage::disk($version->disk())->readStream($version->entryPath());
        if ($stream === false) {
            self::$readFailed = true;

            return null;
        }
        try {
            $content = stream_get_contents($stream, self::MAX_CHART_BYTES + 1);
        } finally {
            fclose($stream);
        }
        if ($content === false || strlen($content) > self::MAX_CHART_BYTES) {
            return null;
        }
        $spec = json_decode($content, true, 512);

        return is_array($spec) && ! array_is_list($spec) && is_string($spec['$schema'] ?? null)
            && str_contains($spec['$schema'], 'vega-lite') ? $spec : null;
    }

    /**
     * What the artifact page shows for a version. A version's files never
     * change, so the preview is built once, from storage, and kept.
     */
    public static function forVersion(DocumentVersion $version): array
    {
        if (! $version->exists || ! $version->disk_path) {
            return self::build($version);
        }

        return self::remember(self::cacheKey('preview', $version), fn () => self::build($version));
    }

    /** A storage read failed while building: don't keep the result. */
    private static bool $readFailed = false;

    private static function remember(string $key, \Closure $build): mixed
    {
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        self::$readFailed = false;
        $value = $build();
        if (! self::$readFailed) {
            Cache::forever($key, $value);
        }

        return $value;
    }

    private static function cacheKey(string $what, DocumentVersion $version): string
    {
        return "artifact-{$what}:{$version->id}:".md5($version->disk().':'.$version->disk_path);
    }

    private static function build(DocumentVersion $version): array
    {
        $type = strtolower(explode(';', $version->content_type)[0]);
        if (in_array($type, ['text/html', 'application/xhtml+xml', 'application/pdf'])) {
            return ['type' => 'frame'];
        }
        // An SVG in an img element can't run scripts, so figures show like any other image
        if ($type === 'image/svg+xml') {
            return ['type' => 'image'];
        }
        if (self::isVegaLite($version->original_filename) && ! $version->isBundle()) {
            return self::vegaLiteSpec($version) !== null
                ? ['type' => 'chart']
                : ['type' => 'download', 'message' => 'This chart cannot be shown. Download the specification to open it.'];
        }
        if (str_starts_with($type, 'image/')) {
            return ['type' => 'image'];
        }
        // A CSV or TSV published with a typed table shows that table and its column summaries
        if (DataFile::isTabular($version->original_filename, $type) && ($sidecar = $version->representationPath()) !== null) {
            $disk = Storage::disk($version->disk());
            $representation = $disk->exists($sidecar) && $disk->size($sidecar) <= ArtifactRepresentation::MAX_BYTES
                ? ArtifactRepresentation::parse((string) $disk->get($sidecar)) : null;
            if ($representation !== null) {
                return ['type' => 'representation', 'data_file' => true] + $representation;
            }
        }
        if ($version->kind === 'data') {
            return ['type' => 'download', 'message' => 'Download this data file to open it in R, Python, Stata, or a spreadsheet.'];
        }
        if (! str_starts_with($type, 'text/') && ! in_array($type, ['application/json', 'application/xml'])) {
            return ['type' => 'download'];
        }
        $json = $type === 'application/json' || str_ends_with(strtolower($version->original_filename ?? ''), '.json');
        $limit = $json ? ArtifactRepresentation::MAX_BYTES : self::MAX_BYTES;
        $stream = Storage::disk($version->disk())->readStream($version->entryPath());
        if ($stream === false) {
            self::$readFailed = true;

            return ['type' => 'download'];
        }
        try {
            $content = stream_get_contents($stream, $limit + 1);
        } finally {
            fclose($stream);
        }
        if ($content === false) {
            self::$readFailed = true;

            return ['type' => 'download'];
        }
        if ($json) {
            $representation = ArtifactRepresentation::parse($content);
            if ($representation !== null) {
                return ['type' => 'representation'] + $representation;
            }
            if (strlen($content) > ArtifactRepresentation::MAX_BYTES
                || in_array($version->kind, ['table', 'model'])
                || preg_match('/"schema"\s*:/', $content)) {
                return ['type' => 'download', 'message' => 'This artifact representation cannot be previewed. Download the original file to inspect it.'];
            }
        }
        $truncated = strlen($content) > self::MAX_BYTES;
        $content = substr($content, 0, self::MAX_BYTES);
        $table = in_array($type, ['text/csv', 'text/tab-separated-values'])
            || in_array(strtolower(pathinfo($version->original_filename ?? '', PATHINFO_EXTENSION)), ['csv', 'tsv']);
        if (! $table) {
            return ['type' => 'text', 'text' => $content, 'truncated' => $truncated];
        }
        $delimiter = $type === 'text/tab-separated-values' || str_ends_with(strtolower($version->original_filename ?? ''), '.tsv') ? "\t" : ',';
        $buffer = fopen('php://temp', 'r+');
        fwrite($buffer, $content);
        rewind($buffer);
        $rows = [];
        $columnsTruncated = false;
        try {
            while (count($rows) < 101 && ($row = fgetcsv($buffer, null, $delimiter, '"', '')) !== false) {
                $columnsTruncated = $columnsTruncated || count($row) > 50;
                $rows[] = array_slice($row, 0, 50);
            }
            $truncated = $truncated || ! feof($buffer) || count($rows) > 100 || $columnsTruncated;
        } finally {
            fclose($buffer);
        }

        return ['type' => 'table', 'rows' => array_slice($rows, 0, 100), 'truncated' => $truncated];
    }
}
