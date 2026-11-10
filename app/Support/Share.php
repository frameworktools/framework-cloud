<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\ShortLink;
use Illuminate\Database\Eloquent\Model;

/**
 * How anything people can share is shared: its short link and its QR code,
 * as SVG or PNG, to copy or download into slides. Artifacts, reports, and
 * collections that aren't private; public and unlisted blueprints; and
 * projects with a public download link. Private things get nothing.
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

    /** @return array{url: string, short_url: string, short: string, code: string, qr_svg: string, qr_png: string, qr_svg_download: string, qr_png_download: string}|null */
    public static function for(Model $model): ?array
    {
        if (! self::shareable($model) || ! ($url = $model->shareUrl())) {
            return null;
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
