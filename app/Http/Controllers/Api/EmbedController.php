<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use App\Support\ArtifactEmbed;
use App\Support\ContentWorker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The embed API: embed documents, figure files, oEmbed, and discovery for
 * sites that draw Framework artifacts themselves. Public, cookie-free, and
 * stateless. Anything that isn't embeddable gets the same 404, so a private
 * key looks like a missing one.
 */
class EmbedController extends Controller
{
    /** Version URLs never change; newest-version and resolve answers may. */
    private const VERSION_MAX_AGE = 3600;

    private const LATEST_MAX_AGE = 300;

    private const IMAGE_CSP = "default-src 'none'; style-src 'unsafe-inline'; sandbox";

    public function resolve(Request $request): JsonResponse
    {
        $url = $request->query('url');
        $resolved = is_string($url) && strlen($url) <= 2048 ? ArtifactEmbed::resolve($url) : null;
        if ($resolved === null) {
            return self::notFound();
        }

        return $this->embed($resolved[0], $resolved[1] ? self::VERSION_MAX_AGE : self::LATEST_MAX_AGE, $resolved[2]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $document = ArtifactEmbed::byKey($key);

        return $document ? $this->embed($document->latestVersion(), self::LATEST_MAX_AGE, self::codeOnly($request)) : self::notFound();
    }

    public function showVersion(Request $request, string $key, int $number): JsonResponse
    {
        $document = ArtifactEmbed::byKey($key);
        // A version that flattening removed embeds as the latest, briefly cached like any latest
        if ($document?->flattenedAway($number)) {
            return $this->embed($document->latestVersion(), self::LATEST_MAX_AGE, self::codeOnly($request));
        }
        $version = $document?->findVersion($number);

        return $version ? $this->embed($version, self::VERSION_MAX_AGE, self::codeOnly($request)) : self::notFound();
    }

    /** ?output=none: a snippet's code without its output. */
    private static function codeOnly(Request $request): bool
    {
        return $request->query('output') === 'none';
    }

    /**
     * An image artifact's figure, also the fallback of a figure drawn live. Where the content Worker serves files, this
     * answers with a redirect to a signed Worker link, so the bytes never pass
     * through the app; the URL in the embed document stays stable.
     */
    public function image(string $key, int $number): JsonResponse|RedirectResponse|StreamedResponse
    {
        $document = ArtifactEmbed::byKey($key);
        $version = $document?->flattenedAway($number) ? $document->latestVersion() : $document?->findVersion($number);
        if ($version === null || ! ArtifactEmbed::hasImage($version)) {
            return self::notFound();
        }
        if (ContentWorker::enabled($version)) {
            return redirect()->away(ContentWorker::embedImage($version))->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                // The signed link lives at least an hour; a takedown reaches cached redirects within five minutes
                'Cache-Control' => 'public, max-age='.self::LATEST_MAX_AGE,
            ]);
        }
        $disk = Storage::disk($version->disk());
        if (! $disk->exists($version->entryPath())) {
            return self::notFound();
        }

        // Without the Worker (self-hosted servers), the app sends the file, as its reader page does
        return $disk->response($version->entryPath(), $version->contentFilename(), [
            'Content-Type' => ArtifactEmbed::contentType($version),
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => self::IMAGE_CSP,
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'public, max-age='.self::VERSION_MAX_AGE,
        ]);
    }

    /** oEmbed 1.0, JSON only: photo for figures of known size, link for everything else. */
    public function oembed(Request $request): JsonResponse|Response
    {
        if ($request->query('format', 'json') !== 'json') {
            return response('Only format=json is supported.', 501)->withHeaders([
                'Content-Type' => 'text/plain; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'no-store',
            ]);
        }
        $url = $request->query('url');
        $resolved = is_string($url) && strlen($url) <= 2048 ? ArtifactEmbed::resolve($url) : null;
        if ($resolved === null) {
            return self::notFound();
        }
        [$version, $pinned, $codeOnly] = $resolved;
        $maxAge = $pinned ? self::VERSION_MAX_AGE : self::LATEST_MAX_AGE;
        $embed = ArtifactEmbed::document($version, $codeOnly);

        $answer = ['version' => '1.0', 'type' => 'link', 'title' => $embed['title']];
        $image = $embed['image'];
        if ($image !== null && $image['width'] && $image['height']) {
            [$width, $height] = self::fit($image['width'], $image['height'], $request->query('maxwidth'), $request->query('maxheight'));
            $answer = ['version' => '1.0', 'type' => 'photo', 'title' => $embed['title'], 'url' => $image['url'], 'width' => $width, 'height' => $height];
        }
        $answer += ($embed['author'] === null ? [] : [
            'author_name' => $embed['author']['name'],
            'author_url' => $embed['author']['url'],
        ]) + [
            'provider_name' => $embed['provider']['name'],
            'provider_url' => $embed['provider']['url'],
            'cache_age' => $maxAge,
            'framework_embed_url' => $embed['embed_url'],
        ];

        return self::json($answer, $maxAge);
    }

    public function discovery(): JsonResponse
    {
        return self::json(['framework' => ['embed' => [
            'version' => 1,
            'resolve' => route('embed.resolve'),
            'artifact' => preg_replace('#/KEY$#', '/{key}', route('embed.artifact', ['key' => 'KEY'])),
            'oembed' => route('embed.oembed'),
        ]]], self::LATEST_MAX_AGE);
    }

    private function embed(DocumentVersion $version, int $maxAge, bool $codeOnly = false): JsonResponse
    {
        return self::json(ArtifactEmbed::document($version, $codeOnly), $maxAge);
    }

    /**
     * A figure's size scaled down to fit maxwidth and maxheight, keeping its proportions.
     *
     * @return array{0: int, 1: int}
     */
    private static function fit(int $width, int $height, mixed $maxWidth, mixed $maxHeight): array
    {
        $scale = 1.0;
        if (is_numeric($maxWidth) && (int) $maxWidth > 0) {
            $scale = min($scale, (int) $maxWidth / $width);
        }
        if (is_numeric($maxHeight) && (int) $maxHeight > 0) {
            $scale = min($scale, (int) $maxHeight / $height);
        }

        return [max(1, (int) floor($width * $scale)), max(1, (int) floor($height * $scale))];
    }

    private static function json(array $body, int $maxAge): JsonResponse
    {
        return response()->json($body, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age='.$maxAge,
            'X-Content-Type-Options' => 'nosniff',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** One answer for private, password-protected, missing, deleted, and external artifacts. */
    public static function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Not found.'], 404, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
