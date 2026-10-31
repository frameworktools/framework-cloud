<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlueprintsController extends Controller
{
    /**
     * GET /api/v2/blueprints, with a user token: the blueprints this user can
     * make projects from (Framework's, their own, and ones shared with them).
     * fw blueprints list prints these.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403, 'This endpoint requires a user token.');

        $blueprints = Blueprint::visibleTo($user)
            ->with('owner', 'latestVersion.base.facet', 'latestVersion.facetVersions.facet')
            ->whereHas('latestVersion')
            ->orderBy('position')->orderBy('name')
            ->get()
            ->map(fn (Blueprint $b) => [
                'key' => $b->handle,
                'name' => $b->name,
                'description' => $b->description,
                'language' => $b->language ?? 'r',
                'type' => $b->latestVersion->base?->layer['type'] ?? 'bare',
                'version' => $b->latestVersion->version,
                'visibility' => $b->visibility,
                'system' => $b->isSystem(),
                'facets' => $b->latestVersion->facetVersions->map(fn ($v) => $v->facet->handle)->values()->all(),
            ]);

        return response()->json(['blueprints' => $blueprints]);
    }
}
