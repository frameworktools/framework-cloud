<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The embed contract, framework.embed/v1: what other sites receive to draw an
 * artifact themselves, without a frame. See docs/plans/2026-11-16-artifact-embeds.md.
 *
 * Only cloud artifacts that anyone with the link may open are embeddable:
 * public or unlisted, no password. Every other artifact reads as missing.
 */
class ArtifactEmbed
{
    public const SCHEMA = 'framework.embed/v1';

    /** Figure types an embed may show through <img>. */
    public const IMAGE_TYPES = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /** Rows kept per table: the renderers show this many. row_count keeps the total. */
    public const ROWS = 25;

    /** Bytes read from a figure to learn its size. */
    private const SIZE_PROBE_BYTES = 262_144;

    public static function embeddable(Document $document): bool
    {
        return ($document->driver ?? 'cloud') === 'cloud'
            && in_array($document->visibility, ['public', 'unlisted'], true)
            && ! $document->isPasswordProtected()
            && $document->project !== null;
    }

    public static function byKey(string $key): ?Document
    {
        $document = Document::where('key', $key)->whereHas('project')->with('project.user')->first();

        return $document && self::embeddable($document) ? $document : null;
    }

    /** A readable link's artifact: public only, as on the reader page. */
    public static function byReadable(string $handle, string $project, string $slug): ?Document
    {
        $user = User::where('handle', $handle)->first();
        if ($user === null) {
            return null;
        }
        $document = Document::where('slug', $slug)->where('visibility', 'public')
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id)->where('slug', $project))
            ->with('project.user')->first();

        return $document && self::embeddable($document) ? $document : null;
    }

    /**
     * The artifact and version a pasted link names, or null. Accepted forms:
     * /d/{key}, /d/{key}/versions/{n}, /@{handle}/{project}/{slug}, and its
     * /versions/{n} form, on this server's host only.
     *
     * @return array{0: DocumentVersion, 1: bool}|null The version, and whether the link named one
     */
    public static function resolve(string $url): ?array
    {
        if (! self::isLocal($url)) {
            return null;
        }
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        $number = null;
        if (preg_match('#^/d/([A-Za-z0-9]+)(?:/versions/([0-9]{1,9}))?$#D', $path, $m)) {
            $document = self::byKey($m[1]);
            $number = isset($m[2]) ? (int) $m[2] : null;
        } elseif (preg_match('#^/@([a-z0-9][a-z0-9-]*)/([A-Za-z0-9_-]+)/([A-Za-z0-9_-]+)(?:/versions/([0-9]{1,9}))?$#D', $path, $m)) {
            $document = self::byReadable($m[1], $m[2], $m[3]);
            $number = isset($m[4]) ? (int) $m[4] : null;
        } else {
            return null;
        }
        $version = $document === null ? null : ($number === null ? $document->latestVersion() : $document->findVersion($number));

        return $version === null ? null : [$version, $number !== null];
    }

    /** Whether a URL is on this server's own host (APP_URL). */
    public static function isLocal(string $url): bool
    {
        $parts = parse_url($url);
        $app = parse_url((string) config('app.url'));
        if (! is_array($parts) || ! is_array($app) || ! isset($parts['host'], $app['host'])
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return strcasecmp($parts['host'], $app['host']) === 0 && ($parts['port'] ?? null) === ($app['port'] ?? null);
    }

    /** The embed document for one version. */
    public static function document(DocumentVersion $version): array
    {
        $document = $version->document;
        $owner = $document->project->user;
        $latest = $document->latestVersion()->number;
        $kind = self::kind($version);
        $preview = ArtifactPreview::forVersion($version);
        $data = $preview['type'] === 'representation' ? array_diff_key($preview, ['type' => true, 'data_file' => true]) : null;
        $spec = $kind === 'vega-lite' ? ArtifactPreview::vegaLiteSpec($version) : null;
        $image = $kind === 'image' ? ['url' => self::imageUrl($version), 'content_type' => self::contentType($version)] + self::imageSize($version) : null;
        $title = $document->title ?: $document->slug;
        $url = self::pageUrl($version);

        return [
            'schema' => self::SCHEMA,
            'key' => $document->key,
            'version' => $version->number,
            'latest_version' => $latest,
            'kind' => $kind,
            'title' => $title,
            'description' => filled($document->description) ? $document->description : null,
            'alt' => EmbedAlt::for($kind, $title, $data),
            'url' => $url,
            'embed_url' => self::embedUrl($version),
            'published_at' => ($version->created_at ?? $document->updated_at)?->toIso8601ZuluString(),
            'author' => self::author($owner),
            'provider' => ['name' => 'Framework', 'url' => self::providerUrl()],
            'attribution' => ['text' => 'Framework', 'url' => $url],
            'data' => $kind === 'page' ? null : self::compact($data),
            'spec' => $spec,
            'image' => $image,
        ];
    }

    /** model, table, test, image, vega-lite, or page. */
    public static function kind(DocumentVersion $version): string
    {
        $preview = ArtifactPreview::forVersion($version);

        return match ($preview['type']) {
            'representation' => in_array($preview['kind'] ?? null, ['model', 'table', 'test'], true) ? $preview['kind'] : 'page',
            'chart' => self::selfContained(ArtifactPreview::vegaLiteSpec($version)) ? 'vega-lite' : 'page',
            'image' => ! $version->isBundle() && in_array(self::contentType($version), self::IMAGE_TYPES, true) ? 'image' : 'page',
            default => 'page',
        };
    }

    /**
     * A representation with each table cut to its first ROWS rows. A cut
     * table is marked truncated; row_count, column_count, and the column
     * summaries still describe the whole table.
     */
    public static function compact(?array $data): ?array
    {
        if ($data === null || ! is_array($data['tables'] ?? null)) {
            return $data;
        }
        foreach ($data['tables'] as &$table) {
            if (is_array($table['rows'] ?? null) && count($table['rows']) > self::ROWS) {
                $table['rows'] = array_slice($table['rows'], 0, self::ROWS);
                $table['truncated'] = true;
                $data['truncated'] = true;
            }
        }
        unset($table);

        return $data;
    }

    /**
     * Whether a Vega-Lite spec draws from itself alone. Data loaded by URL
     * (data, lookup, and datasets entries), image marks, and links (href)
     * reach other hosts, so such a chart embeds as a page card.
     */
    public static function selfContained(mixed $spec): bool
    {
        if (! is_array($spec)) {
            return false;
        }
        foreach ($spec as $key => $value) {
            if ($key === 'url' || $key === 'href') {
                return false;
            }
            if ($key === 'datasets' && is_array($value)) {
                // Named inline data: rows, unless an entry is itself a URL source
                foreach ($value as $dataset) {
                    if (is_array($dataset) && ! array_is_list($dataset) && array_key_exists('url', $dataset)) {
                        return false;
                    }
                }

                continue;
            }
            // Inline rows and free-form metadata may use any field names
            if ($key === 'values' || $key === 'usermeta') {
                continue;
            }
            if (is_array($value) && ! self::selfContained($value)) {
                return false;
            }
        }

        return true;
    }

    public static function contentType(DocumentVersion $version): string
    {
        return strtolower(trim(explode(';', (string) $version->content_type)[0]));
    }

    /** The reader page for this version: the readable link when there is one. */
    public static function pageUrl(DocumentVersion $version): string
    {
        return $version->document->readableUrl($version->number)
            ?? route('documents.version', ['key' => $version->document->key, 'number' => $version->number]);
    }

    public static function embedUrl(DocumentVersion $version): string
    {
        return route('embed.version', ['key' => $version->document->key, 'number' => $version->number]);
    }

    public static function imageUrl(DocumentVersion $version): string
    {
        return route('embed.image', ['key' => $version->document->key, 'number' => $version->number]);
    }

    public static function oembedUrl(DocumentVersion $version): string
    {
        return route('embed.oembed', ['url' => self::pageUrl($version), 'format' => 'json']);
    }

    public static function providerUrl(): string
    {
        return rtrim(url('/'), '/');
    }

    /** The owner, named only when their profile is public: an unlisted link shouldn't reveal who made it. */
    private static function author(?User $user): ?array
    {
        if (! $user || ! filled($user->handle) || ! $user->profile_public || ! filled($user->name)) {
            return null;
        }

        return ['name' => $user->name, 'url' => route('profile', ['handle' => $user->handle])];
    }

    /**
     * A figure's width and height in CSS pixels, when its header says. Read
     * once per version and kept: a version's files never change.
     *
     * @return array{width: int|null, height: int|null}
     */
    public static function imageSize(DocumentVersion $version): array
    {
        $key = $version->exists ? 'artifact-embed-size:'.$version->id.':'.md5($version->disk().':'.$version->disk_path) : null;
        if ($key !== null && is_array($cached = Cache::get($key))) {
            return $cached;
        }
        $stream = Storage::disk($version->disk())->readStream($version->entryPath());
        if (! is_resource($stream)) {
            return ['width' => null, 'height' => null];
        }
        try {
            $head = (string) stream_get_contents($stream, self::SIZE_PROBE_BYTES);
        } finally {
            fclose($stream);
        }
        $size = self::contentType($version) === 'image/svg+xml' ? self::svgSize($head) : self::rasterSize($head);
        if ($key !== null) {
            Cache::forever($key, $size);
        }

        return $size;
    }

    /** @return array{width: int|null, height: int|null} */
    private static function rasterSize(string $head): array
    {
        $info = $head === '' ? false : @getimagesizefromstring($head);

        return is_array($info) && $info[0] > 0 && $info[1] > 0 ? ['width' => $info[0], 'height' => $info[1]] : ['width' => null, 'height' => null];
    }

    /**
     * The root element's width and height, in pixels (pt, in, cm, and mm are
     * converted), else its viewBox. Percentages count as unknown.
     *
     * @return array{width: int|null, height: int|null}
     */
    public static function svgSize(string $head): array
    {
        $none = ['width' => null, 'height' => null];
        if (! preg_match('/<svg\b([^>]*)>/i', $head, $root)) {
            return $none;
        }
        $attribute = fn (string $name) => preg_match('/\s'.$name.'\s*=\s*([\'"])(.*?)\1/is', $root[1], $m) ? trim($m[2]) : null;
        $length = function (?string $value): ?float {
            if ($value === null || ! preg_match('/^([0-9]*\.?[0-9]+)\s*(px|pt|in|cm|mm)?$/i', $value, $m)) {
                return null;
            }
            $factor = ['' => 1, 'px' => 1, 'pt' => 4 / 3, 'in' => 96, 'cm' => 96 / 2.54, 'mm' => 96 / 25.4][strtolower($m[2] ?? '')];

            return (float) $m[1] * $factor;
        };
        $width = $length($attribute('width'));
        $height = $length($attribute('height'));
        if (($width === null || $height === null) && ($box = $attribute('viewBox')) !== null) {
            $numbers = preg_split('/[\s,]+/', $box);
            if (count($numbers) === 4 && is_numeric($numbers[2]) && is_numeric($numbers[3]) && $numbers[2] > 0 && $numbers[3] > 0) {
                // Only one length given: keep the viewBox's proportions
                [$boxWidth, $boxHeight] = [(float) $numbers[2], (float) $numbers[3]];
                if ($width !== null) {
                    $height = $width * $boxHeight / $boxWidth;
                } elseif ($height !== null) {
                    $width = $height * $boxWidth / $boxHeight;
                } else {
                    [$width, $height] = [$boxWidth, $boxHeight];
                }
            }
        }

        return $width > 0 && $height > 0 ? ['width' => (int) round($width), 'height' => (int) round($height)] : $none;
    }
}
