<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /** GET /go/{code}/qr.svg: the QR code of the short link. */
    public function qr(string $code): Response
    {
        $link = ShortLink::lookup($code);
        abort_unless($link?->linkable, 404);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(512, 1), new SvgImageBackEnd)))->writeString($link->url());

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }

    private function resolve(string $typed): ?string
    {
        $link = ShortLink::lookup($typed);

        return $link?->linkable?->shareUrl();
    }
}
