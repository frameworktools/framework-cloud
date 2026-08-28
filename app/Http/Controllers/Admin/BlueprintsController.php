<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlueprintsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Blueprints', [
            'blueprints' => Blueprint::orderBy('position')
                ->get(['id', 'key', 'name', 'description', 'structure', 'agents_md', 'skills']),
        ]);
    }

    public function update(Request $request, Blueprint $blueprint): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'structure' => ['required', 'array'],
            'agents_md' => ['nullable', 'string'],
            'skills' => ['array'],
        ]);

        $blueprint->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'structure' => $validated['structure'],
            'agents_md' => $validated['agents_md'] ?? null,
            'skills' => $validated['skills'] ?? [],
        ]);

        return back()->with('status', 'blueprint-updated');
    }
}
