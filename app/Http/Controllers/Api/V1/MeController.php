<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Identify the bearer: a user token answers with account info, a project
     * token with the project it can pull. Doubles as the client's "is my
     * token valid" check.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tokenable = $request->user();

        if ($tokenable instanceof User) {
            return response()->json([
                'kind' => 'user',
                'name' => $tokenable->name,
                'email' => $tokenable->email,
            ]);
        }

        if ($tokenable instanceof Project) {
            return response()->json([
                'kind' => 'project',
                'project' => $tokenable->slug,
            ]);
        }

        abort(403, 'Unrecognized token type.');
    }
}
