<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Projects', [
            'projects' => $user->projects()->latest()->with('blueprintVersion.blueprint.owner')->get()
                ->map(fn (Project $p) => $p->only(['id', 'name', 'slug', 'project_type', 'description', 'created_at']) + [
                    'blueprint' => $p->blueprintVersion ? [
                        'id' => $p->blueprintVersion->blueprint_id,
                        'handle' => $p->blueprintVersion->blueprint->handle,
                        'name' => $p->blueprintVersion->blueprint->name,
                        'version' => $p->blueprintVersion->version,
                    ] : null,
                ]),
            'blueprints' => Blueprint::listedFor($user)->with('owner')
                ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')
                ->get()->map(fn (Blueprint $b) => ['id' => $b->id, 'name' => $b->name, 'handle' => $b->handle]),
            'selected' => $request->integer('blueprint') ?: null,
        ]);
    }

    /**
     * Projects pin the blueprint's current version, so later edits to the
     * blueprint never change what an existing project was made from.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'blueprint_id' => ['required', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $blueprint = Blueprint::with('latestVersion.base')->find($validated['blueprint_id']);

        if (! $blueprint?->isVisibleTo($request->user()) || ! $blueprint->latestVersion) {
            return back()->withErrors(['blueprint_id' => 'Choose a blueprint.']);
        }

        $slug = Str::slug($validated['name']);

        if ($request->user()->projects()->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'You already have a project with this name.']);
        }

        $request->user()->projects()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'project_type' => $blueprint->latestVersion->base->layer['type'] ?? 'bare',
            'blueprint_version_id' => $blueprint->latestVersion->id,
            'description' => $validated['description'] ?? null,
        ]);

        return to_route('projects');
    }

    public function issueToken(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);

        return back()->with([
            'token' => $project->issuePullToken(),
            'status' => 'project-token',
        ]);
    }

    public function destroy(Request $request, int $project): RedirectResponse
    {
        $project = $request->user()->projects()->findOrFail($project);
        $project->tokens()->delete();
        $project->delete();

        return back();
    }
}
