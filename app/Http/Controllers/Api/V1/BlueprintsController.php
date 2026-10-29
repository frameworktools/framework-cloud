<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlueprintsController extends Controller
{
    /**
     * The project types with their structure and AI masters, in the shape
     * framework 1.x reads. ?key= narrows to one type.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user() instanceof User, 403, 'This endpoint requires a user token.');

        $query = Blueprint::systemTypes()->with('latestVersion.base');

        if ($request->filled('key')) {
            $query->where('key', $request->string('key'));
        }

        return response()->json([
            'blueprints' => $query->get()->map->toV1Array(),
        ]);
    }
}
