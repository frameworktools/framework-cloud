<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\PackageIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageSearchController extends Controller
{
    /** Packages for the package lists' name fields. */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:cran,bioc,pypi,github'],
            'q' => ['required', 'string', 'max:100'],
            'language' => ['nullable', 'in:r,python'],
        ]);

        return response()->json(['packages' => PackageIndex::search($validated['source'], $validated['q'], $validated['language'] ?? null)]);
    }
}
