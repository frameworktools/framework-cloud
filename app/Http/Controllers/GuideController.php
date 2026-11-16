<?php

namespace App\Http\Controllers;

use App\Support\Docs\CloudGuide;
use App\Support\Docs\SearchIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Framework Cloud guide at /docs (CloudGuide reads the pages), shown in
 * the docs sites' layout as the product "cloud".
 */
class GuideController extends Controller
{
    public const PAGES = CloudGuide::PAGES;

    public function show(string $page = 'index'): View
    {
        $guide = CloudGuide::page($page) ?? abort(404);

        return view('docs.guide', [
            'product' => CloudGuide::PRODUCT,
            'site' => CloudGuide::site(),
            'guide' => $guide,
        ]);
    }

    /** Every docs site's search index, for the ⌘K box on framework.pub */
    public function searchIndex(Request $request): JsonResponse
    {
        return response()->json(SearchIndex::payload(cloud: $request->root()))
            ->header('Cache-Control', 'public, max-age=300');
    }
}
