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
        $user = $request->user();
        $setting = $user->globalSettings();
        $doc = $setting->document;

        return Inertia::render('Welcome', [
            'blueprints' => DefaultsController::blueprintList(),
            'values' => DefaultsController::values($user),
            'again' => (bool) $user->setup_completed_at,
            // What the account has actually saved: setup starts from these, and
            // recommends only where nothing was saved
            'saved' => [
                'any' => $setting->revision > 0 || (bool) $user->setup_completed_at,
                'language' => isset($user->project_defaults['language']),
                'workflows' => array_keys(ProjectDefaults::chosenWorkflows(Arr::get($doc, 'defaults.workflows'))),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'in:r,python,stata'],
            'project_type' => ['required', 'string'],
            // R Markdown is R's Quarto workflow writing .Rmd notebooks
            'workflow' => ['required', 'in:'.implode(',', [...ProjectDefaults::WORKFLOW_KEYS, 'rmarkdown'])],
            'editors' => ['array'],
            'editors.*' => ['in:rstudio,vscode'],
            'git' => ['required', 'boolean'],
            'ai' => ['required', 'boolean'],
            'agents' => ['array'],
            'agents.*' => ['in:'.implode(',', ProjectDefaults::AGENTS)],
            'other_agents' => ['boolean'],
        ]);

        // The blueprint must take the language and workflow
        $version = Blueprint::systemTypes()->where('key', $validated['project_type'])->with('latestVersion')->first()?->latestVersion
            ?? throw ValidationException::withMessages(['project_type' => 'Choose one of the project types.']);
        $compatibility = $version->compatibility();
        $rmarkdown = $validated['workflow'] === 'rmarkdown';
        if ($rmarkdown && $validated['language'] !== 'r') {
            throw ValidationException::withMessages(['workflow' => 'R Markdown is for R projects.']);
        }
        $validated['workflow'] = $rmarkdown ? 'quarto' : $validated['workflow'];
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
        // Setup answers R's workflow only when R is the language; otherwise R's R Markdown choice stays
        $keepRmarkdown = $validated['language'] === 'r' ? $rmarkdown : Arr::get($doc, 'defaults.notebook_format') === 'rmarkdown';
        Arr::set($doc, 'defaults.notebook_format', ProjectDefaults::notebookFormat($workflows['r'], 'r', $keepRmarkdown) ?? 'quarto');
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
            'project_defaults' => ['language' => $validated['language'], 'ai_other' => (bool) ($validated['other_agents'] ?? false)] + ($user->project_defaults ?? []),
            'setup_completed_at' => now(),
        ])->save();

        return redirect()->route('dashboard')->with('status', 'setup-complete');
    }

    public function skip(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['setup_completed_at' => $request->user()->setup_completed_at ?? now()])->save();

        return redirect()->route('dashboard');
    }
}
