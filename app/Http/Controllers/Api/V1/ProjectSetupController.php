<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * pull_setup() and push_setup(): a project's packages and skills. A project
 * token reaches its own project, as GET /projects/pull does; a user token
 * names one of the user's projects with ?project=<slug>.
 */
class ProjectSetupController extends Controller
{
    /** GET /api/v1/projects/setup */
    public function show(Request $request): JsonResponse
    {
        return response()->json(ProjectSetup::for(self::project($request)));
    }

    /**
     * POST /api/v1/projects/setup/packages: the full desired package list.
     * Packages missing from it are removed only with remove. A stale
     * base_revision is refused unless force; dry_run saves nothing.
     */
    public function packages(Request $request): JsonResponse
    {
        $project = self::project($request);

        $validated = $request->validate([
            'base_revision' => ['nullable', 'string', 'max:100'],
            'packages' => ['present', 'array', 'max:500'],
            'packages.*' => ['array'],
            'packages.*.name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'distinct'],
            'packages.*.source' => ['nullable', 'string', 'in:cran,bioc,github,pypi'],
            'packages.*.version' => ['nullable', 'string', 'max:100'],
            'packages.*.auto_attach' => ['nullable', 'boolean'],
            'packages.*.repo' => ['nullable', 'string', 'max:200', 'required_if:packages.*.source,github'],
            'remove' => ['boolean'],
            'force' => ['boolean'],
            'dry_run' => ['boolean'],
        ]);

        if (($project->language ?? 'r') === 'stata') {
            return response()->json(['message' => 'Stata projects have no package list.'], 422);
        }

        $revision = ProjectSetup::for($project)['revision'];
        if (($validated['base_revision'] ?? null) !== $revision && ! ($validated['force'] ?? false)) {
            return response()->json([
                'message' => 'The project\'s setup changed on Framework Cloud since you last pulled. Pull first, or push with force.',
                'revision' => $revision,
            ], 409);
        }

        return response()->json(ProjectSetup::pushPackages(
            $project,
            $validated['packages'],
            (bool) ($validated['remove'] ?? false),
            (bool) ($validated['dry_run'] ?? false),
        ));
    }

    /** The project a project token reaches, or the user's project named by ?project=<slug>. */
    public static function project(Request $request): Project
    {
        $tokenable = $request->user();

        if ($tokenable instanceof Project) {
            abort_unless($tokenable->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks the project:pull ability.');

            return $tokenable;
        }

        abort_unless($tokenable instanceof User, 403);
        $slug = (string) $request->query('project', '');
        abort_if($slug === '', 422, 'Name the project with ?project=<slug>.');

        return $tokenable->projects()->listed()->where('slug', $slug)->first()
            ?? abort(404, "You have no project named '{$slug}'.");
    }
}
