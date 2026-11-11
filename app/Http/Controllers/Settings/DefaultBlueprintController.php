<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Project;
use App\Support\ProjectDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The default blueprint for each language, and the default language. A new
 * project without a chosen blueprint uses its language's default.
 */
class DefaultBlueprintController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $blueprints = Blueprint::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($q) => $q->whereNull('user_id')->whereIn('key', Project::TYPES)))
            ->with('latestVersion.blueprint', 'latestVersion.base.facet')
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
            ->filter(fn (Blueprint $b) => $b->latestVersion);

        return Inertia::render('Settings/DefaultBlueprint', [
            'language' => ProjectDefaults::language($user),
            'defaults' => collect(Project::LANGUAGES)->mapWithKeys(fn (string $l) => [$l => ProjectDefaults::blueprint($user, $l)->id])->all(),
            // Each blueprint with the languages it takes and its notebook workflow in each
            'blueprints' => $blueprints->map(fn (Blueprint $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'description' => $b->description,
                'system' => $b->isSystem(),
                'href' => "/blueprints/{$b->id}",
                'languages' => array_values(array_filter(Project::LANGUAGES, fn (string $l) => ProjectDefaults::fits($b, $l))),
                'workflows' => collect(Project::LANGUAGES)->filter(fn (string $l) => ProjectDefaults::fits($b, $l))
                    ->mapWithKeys(fn (string $l) => [$l => ProjectDefaults::workflowOf($b->latestVersion, $l)])->all(),
            ])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'blueprints' => ['required', 'array'],
            'blueprints.*' => ['nullable', 'integer'],
        ]);

        $chosen = [];
        foreach (Project::LANGUAGES as $language) {
            $id = $validated['blueprints'][$language] ?? null;
            if ($id === null) {
                continue;
            }
            $blueprint = Blueprint::with('latestVersion.blueprint', 'latestVersion.base')->find($id);
            if (! $blueprint || ! ProjectDefaults::canBeDefault($blueprint, $user) || ! ProjectDefaults::fits($blueprint, $language)) {
                throw ValidationException::withMessages(["blueprints.{$language}" => 'Choose a blueprint for '.Project::LANGUAGE_NAMES[$language].' projects.']);
            }
            $chosen[$language] = $blueprint;
        }

        ProjectDefaults::save($user, $validated['language'], $chosen);

        return back()->with('status', 'defaults-updated');
    }
}
