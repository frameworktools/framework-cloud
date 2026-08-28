<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class DefaultsController extends Controller
{
    public function edit(Request $request): Response
    {
        $setting = $request->user()->globalSettings();
        $doc = $setting->document;

        return Inertia::render('Settings/Defaults', [
            'revision' => $setting->revision,
            'blueprints' => Blueprint::orderBy('position')->get(['key', 'name']),
            'values' => [
                'author_name' => Arr::get($doc, 'author.name', '') ?? '',
                'author_email' => Arr::get($doc, 'author.email', '') ?? '',
                'author_affiliation' => Arr::get($doc, 'author.affiliation', '') ?? '',
                'project_type' => Arr::get($doc, 'defaults.project_type', 'bare'),
                'notebook_format' => Arr::get($doc, 'defaults.notebook_format', 'quarto'),
                'ide' => Arr::get($doc, 'defaults.ide', 'positron'),
                'use_git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'use_renv' => (bool) Arr::get($doc, 'defaults.use_renv', false),
                'seed_on_scaffold' => (bool) Arr::get($doc, 'defaults.seed_on_scaffold', true),
                'ai_enabled' => (bool) Arr::get($doc, 'ai.enabled', true),
                'ai_canonical_file' => Arr::get($doc, 'ai.canonical_file', 'AGENTS.md'),
                'ai_skills' => (bool) Arr::get($doc, 'ai.skills', true),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'author_name' => ['nullable', 'string', 'max:120'],
            'author_email' => ['nullable', 'email', 'max:190'],
            'author_affiliation' => ['nullable', 'string', 'max:190'],
            'project_type' => ['required', 'exists:blueprints,key'],
            'notebook_format' => ['required', 'in:quarto,rmarkdown'],
            'ide' => ['required', 'in:positron,vscode,rstudio,both,none'],
            'use_git' => ['boolean'],
            'use_renv' => ['boolean'],
            'seed_on_scaffold' => ['boolean'],
            'ai_enabled' => ['boolean'],
            'ai_canonical_file' => ['required', 'string', 'max:60'],
            'ai_skills' => ['boolean'],
        ]);

        $setting = $request->user()->globalSettings();
        $doc = $setting->document;

        Arr::set($doc, 'author.name', $validated['author_name'] ?? '');
        Arr::set($doc, 'author.email', $validated['author_email'] ?? '');
        Arr::set($doc, 'author.affiliation', $validated['author_affiliation'] ?? '');
        Arr::set($doc, 'defaults.project_type', $validated['project_type']);
        Arr::set($doc, 'defaults.notebook_format', $validated['notebook_format']);
        Arr::set($doc, 'defaults.ide', $validated['ide']);
        Arr::set($doc, 'defaults.use_git', (bool) ($validated['use_git'] ?? true));
        Arr::set($doc, 'defaults.use_renv', (bool) ($validated['use_renv'] ?? false));
        Arr::set($doc, 'defaults.seed_on_scaffold', (bool) ($validated['seed_on_scaffold'] ?? true));
        Arr::set($doc, 'ai.enabled', (bool) ($validated['ai_enabled'] ?? true));
        Arr::set($doc, 'ai.canonical_file', $validated['ai_canonical_file']);
        Arr::set($doc, 'ai.skills', (bool) ($validated['ai_skills'] ?? true));

        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);

        return back()->with('status', 'defaults-updated');
    }
}
