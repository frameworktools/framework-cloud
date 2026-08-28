<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectPullController extends Controller
{
    /**
     * Resolve a project pull token to the project's spec plus the owner's
     * settings document — everything framework::setup() needs to scaffold
     * the project locally.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $project = $request->user();

        abort_unless($project instanceof Project, 403, 'This endpoint requires a project token.');
        abort_unless($request->user()->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks the project:pull ability.');

        $settings = $project->user->globalSettings();
        $blueprint = \App\Models\Blueprint::where('key', $project->project_type)->first();

        return response()->json([
            'project' => [
                'name' => $project->name,
                'slug' => $project->slug,
                'project_type' => $project->project_type,
                'description' => $project->description,
                'payload' => $project->payload ?: (object) [],
            ],
            'settings' => [
                'revision' => $settings->revision,
                'schema_version' => $settings->schema_version,
                'document' => $settings->document,
            ],
            'blueprint' => $blueprint?->only(['key', 'name', 'structure', 'agents_md', 'skills']),
        ]);
    }
}
