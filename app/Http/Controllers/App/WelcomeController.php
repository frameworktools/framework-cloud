<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\DefaultsController;
use App\Models\Blueprint;
use App\Support\ProjectDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guided setup: six questions that set real defaults every way of creating
 * a project honors (the Project defaults pages, the New project page, R,
 * Python, and fw). It starts after sign-up, can be skipped, and can be run
 * again from Project defaults.
 */
class WelcomeController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Welcome', [
            'blueprints' => DefaultsController::blueprintList(),
            'values' => DefaultsController::values($request->user()),
            'again' => (bool) $request->user()->setup_completed_at,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'in:r,python,stata'],
            'project_type' => ['required', 'string'],
            'workflow' => ['required', 'in:'.implode(',', ProjectDefaults::WORKFLOW_KEYS)],
            'editors' => ['array'],
            'editors.*' => ['in:rstudio,vscode'],
            'git' => ['required', 'boolean'],
            'ai' => ['required', 'boolean'],
            'agents' => ['array'],
            'agents.*' => ['in:'.implode(',', ProjectDefaults::AGENTS)],
        ]);

        // The blueprint must take the language and workflow
        $version = Blueprint::systemTypes()->where('key', $validated['project_type'])->with('latestVersion')->first()?->latestVersion
            ?? throw ValidationException::withMessages(['project_type' => 'Choose one of the project types.']);
        $compatibility = $version->compatibility();
        if ($compatibility['languages'] && ! in_array($validated['language'], $compatibility['languages'], true)) {
            throw ValidationException::withMessages(['project_type' => 'That project type isn\'t available for this language.']);
        }
        if (! ProjectDefaults::workflowFits($validated['workflow'], $validated['language'])
            || ($compatibility['workflows'] && ! in_array($validated['workflow'], $compatibility['workflows'], true))) {
            throw ValidationException::withMessages(['workflow' => 'That workflow isn\'t available for this project type and language.']);
        }

        $user = $request->user();
        $setting = $user->globalSettings();
        $doc = $setting->document;
        $editors = $validated['editors'] ?? [];
        $workflows = ProjectDefaults::workflows(Arr::get($doc, 'defaults.workflows'));
        $workflows[$validated['language']] = $validated['workflow'];

        Arr::set($doc, 'defaults.project_type', $validated['project_type']);
        Arr::set($doc, 'defaults.workflows', $workflows);
        Arr::set($doc, 'defaults.notebook_format', ProjectDefaults::notebookFormat($workflows['r'], 'r', Arr::get($doc, 'defaults.notebook_format') === 'rmarkdown') ?? 'quarto');
        Arr::set($doc, 'defaults.ide', match (true) {
            in_array('rstudio', $editors, true) && in_array('vscode', $editors, true) => 'both',
            in_array('rstudio', $editors, true) => 'rstudio',
            in_array('vscode', $editors, true) => 'vscode',
            default => 'none',
        });
        Arr::set($doc, 'defaults.use_git', (bool) $validated['git']);
        Arr::set($doc, 'ai.enabled', (bool) $validated['ai']);
        Arr::set($doc, 'ai.assistants', $validated['ai'] ? ProjectDefaults::agents($validated['agents'] ?? []) : ProjectDefaults::agents(Arr::get($doc, 'ai.assistants')));
        $setting->replaceDocument($doc, client: ['device' => 'web', 'app' => 'framework.pub']);

        $user->forceFill([
            'project_defaults' => ['language' => $validated['language']] + ($user->project_defaults ?? []),
            'setup_completed_at' => now(),
        ])->save();

        return redirect()->route('projects')->with('status', 'setup-complete');
    }

    public function skip(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['setup_completed_at' => $request->user()->setup_completed_at ?? now()])->save();

        return redirect()->route('projects');
    }
}
