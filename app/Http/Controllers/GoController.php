<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use App\Support\Share;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** framework.pub/go: short links to artifacts, reports, and collections, and their QR codes. */
class GoController extends Controller
{
    /** GET /go: a box for the code on screen. */
    public function index(Request $request): View|RedirectResponse
    {
        $typed = trim((string) $request->query('code', ''));

        if ($typed !== '') {
            $link = $this->resolve($typed);
            if ($link) {
                return redirect()->away($link);
            }
        }

        return view('go', ['typed' => $typed, 'missing' => $typed !== '']);
    }

    /** GET /go/{code}: on to the page. */
    public function show(string $code): RedirectResponse
    {
        return redirect()->away($this->resolve($code) ?? abort(404, 'No page has that code.'));
    }

    /**
     * GET /go/{code}/qr.svg or qr.png: the QR code of the short link, black on
     * white with a quiet zone, for a slide. PNGs are 1024 px; ?download=1
     * saves it under the page's name.
     */
    public function qr(Request $request, string $code, string $format): Response
    {
        $link = ShortLink::lookup($code);
        abort_unless($link?->linkable && Share::shareable($link->linkable), 404);

        $body = $format === 'png'
            ? (new GDLibRenderer(1024, 4))->render(Encoder::encode($link->url(), ErrorCorrectionLevel::M()))
            : (new Writer(new ImageRenderer(new RendererStyle(1024, 4), new SvgImageBackEnd)))->writeString($link->url());

        $headers = ['Content-Type' => $format === 'png' ? 'image/png' : 'image/svg+xml', 'Cache-Control' => 'public, max-age=3600'];
        if ($request->boolean('download')) {
            $name = Str::slug((string) ($link->linkable->title ?? $link->linkable->name ?? $link->code)) ?: $link->code;
            $headers['Content-Disposition'] = "attachment; filename=\"{$name}-qr.{$format}\"";
        }

        return response($body, 200, $headers);
    }

    private function resolve(string $typed): ?string
    {
        $link = ShortLink::lookup($typed);

        return $link?->linkable && Share::shareable($link->linkable) ? $link->linkable->shareUrl() : null;
    }
}
