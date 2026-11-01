<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Facet;
use App\Models\User;
use App\Support\Layer;
use App\Support\Library;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v2/structures: save a folder layout someone imported from an
 * existing project (R's and Python's structure_import(), fw structure
 * import) as a project structure of their own. Only folder paths and the
 * roles they play arrive; the packages never send file names or contents.
 * The structure starts private, for the person to refine and share.
 */
class StructuresController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403, 'This endpoint requires a user token.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'directories' => ['present', 'array'],
            'directories.*' => ['string', 'max:255'],
            'extra_directories' => ['nullable', 'array'],
            'extra_directories.*.label' => ['required', 'string', 'max:100'],
            'extra_directories.*.path' => ['required', 'string', 'max:255'],
        ]);

        $layer = array_filter([
            'type' => 'project',
            'directories' => $data['directories'] ?: null,
            'extra_directories' => $data['extra_directories'] ?? null,
        ]);
        foreach (array_keys($layer['directories'] ?? []) as $key) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $key)) {
                throw ValidationException::withMessages(['directories' => "Folder roles are lowercase names such as inputs_raw, not \"{$key}\"."]);
            }
        }
        if ($problems = Layer::problems($layer, 'structure')) {
            throw ValidationException::withMessages(['directories' => implode("\n", $problems)]);
        }

        $facet = Facet::create([
            'user_id' => $user->id,
            'kind' => 'structure',
            'key' => Library::uniqueKey(Facet::query(), $data['name'], $user->id),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => 'private',
        ]);
        $facet->publishVersion(['layer' => $layer, 'notes' => 'Imported from an existing project']);

        return response()->json([
            'key' => $facet->fresh()->handle,
            'name' => $facet->name,
            'url' => route('structures.edit', $facet),
        ], 201);
    }
}
