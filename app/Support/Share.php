<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\ShortLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * How anything people can share is shared: its link and its QR code, as SVG
 * or PNG, to copy or download into slides. Artifacts, reports, and
 * collections that aren't private; public and unlisted blueprints; and
 * projects with a public download link. Private things get nothing.
 *
 * Only public things get a short link (framework.pub/go/abcd). A short code
 * is four characters, which anyone can guess; an unlisted thing is open to
 * whoever has its link, so it shares its full link, and its QR code holds that.
 */
class Share
{
    public static function shareable(Model $model): bool
    {
        return match (true) {
            $model instanceof Document, $model instanceof Report, $model instanceof Collection => $model->visibility !== 'private',
            $model instanceof Blueprint => $model->isPublic(),
            $model instanceof Project => filled($model->public_download_key),
            default => false,
        };
    }

    /** Whether anyone may open it without its link: only these get a short code. */
    public static function public(Model $model): bool
    {
        return match (true) {
            $model instanceof Document, $model instanceof Report, $model instanceof Collection => $model->visibility === 'public',
            $model instanceof Blueprint => $model->isSystem() || $model->visibility === 'public',
            default => false,
        };
    }

    /** @return array{url: string, short_url: string, short: string, code: string|null, qr_svg: string, qr_png: string, qr_svg_download: string, qr_png_download: string}|null */
    public static function for(Model $model): ?array
    {
        if (! self::shareable($model) || ! ($url = $model->shareUrl())) {
            return null;
        }
        if (! self::public($model)) {
            // Its own link, which can't be guessed, with a QR code of it behind a signed URL
            $qr = fn (string $format, bool $download = false) => URL::signedRoute('share.qr', array_filter([
                'url' => $url, 'format' => $format, 'name' => Str::slug((string) ($model->title ?? $model->name ?? '')) ?: null, 'download' => $download ? 1 : null,
            ]));

            return [
                'url' => $url,
                'short_url' => $url,
                'short' => preg_replace('#^https?://#', '', $url),
                'code' => null,
                'qr_svg' => $qr('svg'),
                'qr_png' => $qr('png'),
                'qr_svg_download' => $qr('svg', true),
                'qr_png_download' => $qr('png', true),
            ];
        }

        $link = ShortLink::for($model);

        return [
            'url' => $url,
            'short_url' => $link->url(),
            'short' => $link->display(),
            'code' => $link->code,
            'qr_svg' => route('go.qr', [$link->code, 'svg']),
            'qr_png' => route('go.qr', [$link->code, 'png']),
            'qr_svg_download' => route('go.qr', [$link->code, 'svg', 'download' => 1]),
            'qr_png_download' => route('go.qr', [$link->code, 'png', 'download' => 1]),
        ];
    }
}
