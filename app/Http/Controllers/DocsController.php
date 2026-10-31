<?php

namespace App\Http\Controllers;

use App\Support\Docs\Products;
use App\Support\Docs\Search;
use App\Support\Docs\Sites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The docs sites: r.framework.pub, py.framework.pub, and cli.framework.pub,
 * served by this app with domain routing. Each route carries its product
 * as a route default, which Laravel passes after the URL's own parameters. Content comes from App\Support\Docs\Sites.
 */
class DocsController extends Controller
{
    public function home(string $product): View
    {
        $site = Sites::site($product);

        if ($site === null) {
            return view('docs.unpublished', ['product' => $product, 'site' => null]);
        }

        return view('docs.home', ['product' => $product, 'site' => $site]);
    }

    public function guide(string $id, string $product): View
    {
        $site = $this->site($product);
        $guide = $site['guides'][$id] ?? abort(404);

        return view('docs.guide', ['product' => $product, 'site' => $site, 'guide' => self::absolute($guide)]);
    }

    public function reference(string $product): View
    {
        $site = $this->site($product);
        abort_if($site['categories'] === [], 404);

        return view('docs.reference', ['product' => $product, 'site' => $site]);
    }

    public function entry(string $id, string $product): View|RedirectResponse
    {
        $site = $this->site($product);

        if (! isset($site['entries'][$id])) {
            // A name or alias (data_load, "project bootstrap") goes to its entry
            $target = $site['names'][preg_replace('/\(\)$/', '', $id)] ?? abort(404);

            return redirect('/reference/'.$target, 301);
        }

        return view('docs.entry', ['product' => $product, 'site' => $site, 'entry' => self::absolute($site['entries'][$id])]);
    }

    public function search(Request $request, string $product): View
    {
        $site = $this->site($product);
        $query = trim((string) $request->string('q'));

        return view('docs.search', [
            'product' => $product,
            'site' => $site,
            'query' => $query,
            'results' => Search::query(Sites::search($product), $query),
        ]);
    }

    public function searchIndex(string $product): JsonResponse
    {
        $this->site($product);

        return response()->json(Sites::search($product))
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function image(string $path, string $product): BinaryFileResponse
    {
        $file = Sites::image($product, $path) ?? abort(404);

        $headers = ['Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff'];
        if (str_ends_with(strtolower($path), '.svg')) {
            // An SVG is a document: no scripts, even when opened on its own
            $headers['Content-Type'] = 'image/svg+xml';
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($file, $headers);
    }

    /** Links to the other products' hosts, which stored HTML leaves as fwdocs:// */
    private static function absolute(array $page): array
    {
        array_walk_recursive($page, function (&$value) {
            if (is_string($value)) {
                $value = Products::absolutize($value);
            }
        });

        return $page;
    }

    /** @return array<string, mixed> */
    private function site(string $product): array
    {
        return Sites::site($product) ?? abort(404);
    }
}
