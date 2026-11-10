<?php

namespace App\Support;

use App\Models\DocumentVersion;

/**
 * Links to the content host's Cloudflare Worker, which serves published files
 * straight from the R2 bucket. The app only signs: it checks who may read the
 * file, then hands the browser a short-lived link, so file bytes never pass
 * through the app server.
 *
 * A link is https://content.framework.pub/c/{token}/{path}. The token is
 * base64url(JSON) "." base64url(HMAC-SHA256), keyed by FW_CONTENT_SIGNING_KEY,
 * and names the object (k), whether it is a bundle folder (b), its type (t),
 * an attachment filename (a), and when it expires (e). The Worker source is
 * cloudflare/content-worker/worker.js.
 */
class ContentWorker
{
    private const LIFETIME_SECONDS = 3600;

    /** Whether the Worker serves files at all. */
    public static function on(): bool
    {
        return filled(config('framework.content_signing_key')) && ContentHost::enabled();
    }

    /** The disk the Worker's bucket binding reads. */
    public static function disk(): string
    {
        return (string) config('framework.content_worker_disk');
    }

    public static function enabled(DocumentVersion $version): bool
    {
        return self::on()
            && ($version->document->driver ?? 'cloud') === 'cloud'
            && $version->disk_path
            && $version->disk() === self::disk();
    }

    /** The file itself, or a file inside a bundle, as the browser should show it. */
    public static function url(DocumentVersion $version, ?string $path = null): string
    {
        if ($version->isBundle()) {
            $path ??= $version->entrypoint;

            return self::link(['k' => $version->disk_path, 'b' => 1], $path);
        }

        $type = (string) ($version->content_type ?: 'application/octet-stream');
        $claims = ['k' => $version->disk_path, 't' => $type];
        if (! self::inline($type)) {
            $claims['a'] = $version->original_filename ?? $version->contentFilename();
        }

        return self::link($claims, $version->contentFilename());
    }

    /** A download of an object in the Worker's bucket, saved under its name. */
    public static function download(string $path, string $filename, string $type): string
    {
        return self::link(['k' => $path, 't' => $type, 'a' => $filename], self::safeName($filename));
    }

    /** Whether browsers show this type in place; anything else downloads. */
    public static function inline(string $type): bool
    {
        return str_starts_with($type, 'text/') || str_starts_with($type, 'image/')
            || in_array(strtolower(trim(explode(';', $type)[0])), ['application/pdf', 'application/xhtml+xml'], true);
    }

    /** @param  array<string, mixed>  $claims */
    private static function link(array $claims, string $path): string
    {
        $claims['e'] = now()->timestamp + self::LIFETIME_SECONDS;
        $payload = self::encode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = self::encode(hash_hmac('sha256', $payload, (string) config('framework.content_signing_key'), true));
        $path = implode('/', array_map(rawurlencode(...), explode('/', ltrim($path, '/'))));

        return rtrim((string) config('framework.content_url'), '/').'/c/'.$payload.'.'.$signature.'/'.$path;
    }

    private static function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', basename(str_replace('\\', '/', $name))) ?: 'download';
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
