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
     * All blueprints (project types) with their structure and AI masters.
     * The R package consumes these when creating projects; ?key= narrows to
     * one blueprint.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user() instanceof User, 403, 'This endpoint requires a user token.');

        $query = Blueprint::query()->orderBy('position');

        if ($request->filled('key')) {
            $query->where('key', $request->string('key'));
        }

        return response()->json([
            'blueprints' => $query->get([
                'key', 'name', 'description', 'structure', 'agents_md', 'skills', 'is_system',
            ]),
        ]);
    }
}
