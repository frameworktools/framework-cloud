<?php

namespace App\Http\Controllers;

use App\Support\Docs;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * r.framework.pub — the R package documentation site, served by this app
 * via domain routing. Guides come from resources/docs/guides; the function
 * reference reads docs.db directly.
 */
class DocsController extends Controller
{
    public function home(): View
    {
        return view('docs.home', [
            'guides' => Docs::guides(),
            'categories' => Docs::categories(),
        ]);
    }

    public function guide(string $slug): View
    {
        $guide = Docs::guide($slug);

        abort_unless($guide !== null, 404);

        return view('docs.guide', [
            'guide' => $guide,
            'guides' => Docs::guides(),
            'categories' => Docs::categories(),
        ]);
    }

    public function reference(): View
    {
        return view('docs.reference', [
            'guides' => Docs::guides(),
            'categories' => Docs::categories(),
            'byCategory' => Docs::functionsByCategory(),
        ]);
    }

    public function fn(string $name): View
    {
        $fn = Docs::fn($name);

        abort_unless($fn !== null, 404);

        return view('docs.function', [
            'fn' => $fn,
            'guides' => Docs::guides(),
            'categories' => Docs::categories(),
        ]);
    }

    public function search(Request $request): View
    {
        $query = (string) $request->string('q');

        return view('docs.search', [
            'query' => $query,
            'results' => Docs::search($query),
            'guides' => Docs::guides(),
            'categories' => Docs::categories(),
        ]);
    }
}
