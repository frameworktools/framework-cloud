<?php

namespace App\Http\Controllers;

use App\Support\Docs\CloudGuide;
use App\Support\Docs\SearchIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Framework Cloud guide at /docs (CloudGuide reads the pages).
 */
class GuideController extends Controller
{
    public const PAGES = CloudGuide::PAGES;

    public function show(string $page = 'index'): View
    {
        $html = CloudGuide::html($page) ?? abort(404);

        $html = self::languageTabs($html);
        $html = str_replace(['<table>', '</table>'], ['<div class="overflow-x-auto"><table>', '</table></div>'], $html);

        return view('guide', [
            'title' => CloudGuide::title($page),
            'page' => $page,
            'pages' => self::PAGES,
            'html' => $html,
        ]);
    }

    /** Every docs site's search index, for the ⌘K box on framework.pub */
    public function searchIndex(Request $request): JsonResponse
    {
        return response()->json(SearchIndex::payload(cloud: $request->root()))
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * An R code block followed directly by a Python one becomes a pair of
     * tabs. On GitHub the Markdown shows both blocks, R first.
     */
    public static function languageTabs(string $html): string
    {
        return preg_replace_callback(
            '#(<pre><code class="language-r">.*?</code></pre>)\s*(<pre><code class="language-python">.*?</code></pre>)#s',
            fn ($m) => '<div class="code-tabs" data-code-tabs>'
                .'<div class="code-tabs-bar" role="tablist">'
                .'<button type="button" role="tab" data-lang="r" aria-selected="true">R</button>'
                .'<button type="button" role="tab" data-lang="python" aria-selected="false">Python</button>'
                .'</div>'
                .'<div data-lang-panel="r">'.$m[1].'</div>'
                .'<div data-lang-panel="python" hidden>'.$m[2].'</div>'
                .'</div>',
            $html,
        );
    }
}
