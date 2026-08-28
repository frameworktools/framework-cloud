<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Projects', [
            'projects' => $request->user()->projects()->latest()
                ->get(['id', 'name', 'slug', 'project_type', 'description', 'created_at']),
            'blueprints' => Blueprint::orderBy('position')->get(['key', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'project_type' => ['required', 'exists:blueprints,key'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = Str::slug($validated['name']);

        if ($request->user()->projects()->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'You already have a project with this name.']);
        }

        $request->user()->projects()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'project_type' => $validated['project_type'],
            'description' => $validated['description'] ?? null,
        ]);

        return back();
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
