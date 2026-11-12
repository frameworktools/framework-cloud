<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProjectData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * pull_data_registry() and push_data_registry(): a project's data registry,
 * the catalog in its settings' data section. Definitions only: never data
 * files or their hashes. A project token reaches its own project; a user
 * token names one of the user's projects with ?project=<slug>.
 */
class ProjectDataController extends Controller
{
    /** GET /api/v1/projects/data */
    public function show(Request $request): JsonResponse
    {
        return response()->json(ProjectData::for(ProjectSetupController::project($request)));
    }

    /**
     * POST /api/v1/projects/data: the full desired entry list. Entries
     * missing from it are removed only with remove. A stale base_revision
     * is refused unless force; dry_run saves nothing.
     */
    public function update(Request $request): JsonResponse
    {
        $project = ProjectSetupController::project($request);

        $validated = $request->validate([
            'base_revision' => ['nullable', 'string', 'max:100'],
            'entries' => ['present', 'array', 'max:1000'],
            'entries.*' => ['array'],
            'entries.*.name' => ['required', 'string', 'max:200', 'regex:'.ProjectData::NAME_PATTERN, 'distinct'],
            'entries.*.path' => ['required', 'string', 'max:500'],
            'entries.*.type' => ['nullable', 'string', Rule::in(ProjectData::TYPES)],
            'entries.*.delimiter' => ['nullable', 'string', Rule::in(ProjectData::DELIMITERS)],
            'entries.*.locked' => ['nullable', 'boolean'],
            'entries.*.private' => ['nullable', 'boolean'],
            'entries.*.description' => ['nullable', 'string', 'max:2000'],
            'entries.*.stata_names' => ['nullable', 'array', 'max:500'],
            'entries.*.stata_names.*' => ['nullable', 'string', 'max:100'],
            'remove' => ['boolean'],
            'force' => ['boolean'],
            'dry_run' => ['boolean'],
        ], [
            'entries.*.name.regex' => 'Data entry names are dot-separated parts of letters, digits, _ and -, such as inputs.raw.survey.',
            'entries.*.type.in' => 'A data entry\'s type must be one of: '.implode(', ', ProjectData::TYPES).'.',
            'entries.*.delimiter.in' => 'A data entry\'s delimiter must be comma, tab, semicolon, or space.',
        ]);

        $revision = ProjectData::for($project)['revision'];
        if (($validated['base_revision'] ?? null) !== $revision && ! ($validated['force'] ?? false)) {
            return response()->json([
                'message' => 'The project\'s data registry changed on Framework Cloud since you last pulled. Pull first, or push with force.',
                'revision' => $revision,
            ], 409);
        }

        return response()->json(ProjectData::push(
            $project,
            $validated['entries'],
            (bool) ($validated['remove'] ?? false),
            (bool) ($validated['dry_run'] ?? false),
        ));
    }
}
