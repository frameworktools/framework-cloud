<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\DefaultsController;
use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Project;
use App\Support\AuthorProfile;
use App\Support\BlueprintInput;
use App\Support\Catalog;
use App\Support\Library;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Introduction and author details, followed by the shared blueprint editor. */
class WelcomeController extends Controller
{
    private const DRAFT = 'onboarding';

    public function show(Request $request): Response|RedirectResponse
    {
        $draft = $this->draft($request) ?? [];
        if (isset($draft['version_id']) && ! $request->boolean('details')) {
            return to_route('welcome.blueprint', ['tab' => $draft['section'] ?? 'name']);
        }

        return Inertia::render('Welcome', [
            'blueprints' => DefaultsController::blueprintList(),
            'author' => $draft['author'] ?? AuthorProfile::for($request->user()),
            'accountEmail' => $request->user()->email,
            'language' => $draft['language'] ?? ProjectDefaults::language($request->user()),
            'projectsDir' => ProjectDefaults::projectsDir($request->user()),
            'blueprintId' => $draft['blueprint_id'] ?? null,
            'again' => (bool) $request->user()->setup_completed_at,
            'step' => min(3, $draft['step'] ?? 0),
        ]);
    }

    /** Keep the introduction's draft in this session until the blueprint is saved. */
    public function store(Request $request): RedirectResponse
    {
        AuthorProfile::prepare($request);
        $data = $request->validate(AuthorProfile::rules() + [
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'blueprint_id' => ['required', 'integer'],
            'projects_dir' => ProjectDefaults::projectsDirRules(),
        ], AuthorProfile::messages());
        $blueprint = Blueprint::systemTypes()->with(Library::BLUEPRINT_WITH)->find($data['blueprint_id']);
        if (! $blueprint || ! ProjectDefaults::fits($blueprint, $data['language'])) {
            throw ValidationException::withMessages(['blueprint_id' => 'Choose a starting blueprint for this language.']);
        }
        if ($request->exists('projects_dir')) {
            ProjectDefaults::setProjectsDir($request->user(), $data['projects_dir'] ?? null);
        }
        $previous = $this->draft($request) ?? [];
        $same = ($previous['version_id'] ?? null) === $blueprint->latestVersion->id && ($previous['language'] ?? null) === $data['language'];
        $this->saveDraft($request, [
            ...($same ? $previous : []),
            'author' => array_intersect_key($data, AuthorProfile::rules()),
            'user_id' => $request->user()->id, 'language' => $data['language'], 'blueprint_id' => $blueprint->id,
            'version_id' => $blueprint->latestVersion->id, 'id' => $same ? $previous['id'] : (string) Str::uuid(),
            'step' => 4, 'section' => $same ? ($previous['section'] ?? 'name') : 'name',
        ]);

        return to_route('welcome.blueprint');
    }

    public function blueprint(Request $request): Response|RedirectResponse
    {
        $draft = $this->draft($request);
        if (! $draft || ($draft['user_id'] ?? null) !== $request->user()->id) {
            return to_route('welcome');
        }
        $user = $request->user();
        $source = BlueprintVersion::with(['blueprint.owner', 'base.facet.owner', 'facetVersions.facet.owner'])->find($draft['version_id']);
        if (! $source || ! $source->blueprint->isVisibleTo($user)) {
            $this->saveDraft($request, null);

            return to_route('welcome');
        }
        $language = $draft['language'];
        $supported = $source->compatibility()['workflows'] ?: ProjectDefaults::WORKFLOW_KEYS;
        $workflow = collect([$source->default_workflow[$language] ?? null, ProjectDefaults::WORKFLOWS[$language], ...$supported])->first(fn ($w) => in_array($w, $supported, true) && ProjectDefaults::workflowFits($w, $language));
        // Narrow a multi-language starter in memory before populating the editor.
        $source->languages = [$language];
        $source->workflows = [$workflow];
        $source->default_workflow = [$language => $workflow];
        $source->setRelation('facetVersions', $source->facetVersions->filter(fn ($v) => ProjectLayers::fits($v, $language)));
        $start = BlueprintInput::start($source, Catalog::choices($source, $user), $user);
        $start['language'] = $language;
        $start['notebook_format'] = $language === 'stata' ? null : ($workflow === 'scripts' ? 'scripts' : ($start['notebook_format'] ?? $workflow));
        if (isset($draft['builder'])) {
            $packageOverrides = array_replace($start['package_overrides'], $draft['builder']['package_overrides'] ?? []);
            $packageAutoAttach = array_replace($start['package_auto_attach'], $draft['builder']['package_auto_attach'] ?? []);
            $start = array_replace($start, $draft['builder']);
            $start['package_overrides'] = $packageOverrides;
            $start['package_auto_attach'] = $packageAutoAttach;
            $start['default_workflow'] = [$language => $draft['builder']['workflow']];
            $start['notebook_format'] = $draft['builder']['notebook_format'] ?? ($draft['builder']['workflow'] === 'scripts' ? 'scripts' : $draft['builder']['workflow']);
        }
        $tabs = ['name', 'notebooks', 'quarto', 'jupyter', 'structure', 'environment', 'packages', 'scaffold', 'editor', 'ai', 'skills', 'git', 'publishing', 'review'];

        return Inertia::render('Blueprints/Builder', [
            'blueprint' => null, 'start' => $start, 'catalog' => Catalog::for($user),
            'agents' => $start['agents'], 'ggplotThemes' => ProjectDefaults::GGPLOT_THEMES,
            'quartoChoices' => ProjectDefaults::QUARTO_CHOICES,
            'section' => in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : ($draft['section'] ?? 'name'),
            'onboarding' => ['saved' => isset($draft['builder']), 'draftId' => $draft['id'], 'language' => $language, 'source' => $source->blueprint->name, 'name' => $draft['builder']['name'] ?? 'Default Project', 'description' => $draft['builder']['description'] ?? ''],
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $draft = $this->draft($request);
        if (! $draft || ($draft['user_id'] ?? null) !== $request->user()->id) {
            return to_route('welcome');
        }
        if ($request->input('language') !== $draft['language']) {
            throw ValidationException::withMessages(['language' => 'Return to setup to change the default language.']);
        }
        // Onboarding always creates a private, single-language blueprint.
        $request->merge(['visibility' => 'private', 'languages' => [$draft['language']]]);
        [$meta, $base, $pieces, $layer, $notes, $compatibility] = BlueprintInput::validated($request);
        $user = $request->user();
        DB::transaction(function () use ($user, $draft, $meta, $base, $pieces, $layer, $notes, $compatibility) {
            $blueprint = Blueprint::create($meta + ['user_id' => $user->id, 'key' => Library::uniqueKey(Blueprint::query(), $meta['name'], $user->id), 'is_system' => false, 'forked_from_id' => $draft['version_id']]);
            $blueprint->publish($base, $pieces, $layer, $notes, $compatibility);
            AuthorProfile::save($user, $draft['author']);
            ProjectDefaults::save($user, $draft['language'], [$draft['language'] => $blueprint]);
            $user->forceFill(['setup_completed_at' => now(), 'onboarding_progress' => null])->save();
        });
        $request->session()->forget(self::DRAFT);

        return to_route('welcome.ready')->with('status', 'setup-complete');
    }

    /** Save each completed step on the account, including blueprint edits. */
    public function progress(Request $request): JsonResponse
    {
        $stage = $request->validate(['stage' => ['required', Rule::in(['introduction', 'profile', 'language', 'builder'])]])['stage'];
        $draft = $this->draft($request) ?? ['user_id' => $request->user()->id];
        if ($stage === 'introduction') {
            $draft['step'] = 1;
        } elseif ($stage === 'profile') {
            AuthorProfile::prepare($request);
            $author = $request->validate(AuthorProfile::rules(), AuthorProfile::messages());
            AuthorProfile::save($request->user(), $author);
            $draft['author'] = $author;
            $draft['step'] = 2;
        } elseif ($stage === 'language') {
            $language = $request->validate([
                'language' => ['required', Rule::in(Project::LANGUAGES)],
                'projects_dir' => ProjectDefaults::projectsDirRules(),
            ])['language'];
            if (($draft['language'] ?? null) !== $language) {
                unset($draft['builder'], $draft['version_id'], $draft['blueprint_id'], $draft['id'], $draft['section']);
            }
            $draft['language'] = $language;
            $draft['step'] = 3;
            ProjectDefaults::setLanguage($request->user(), $language);
            if ($request->exists('projects_dir')) {
                ProjectDefaults::setProjectsDir($request->user(), $request->input('projects_dir'));
            }
        } else {
            abort_unless(isset($draft['version_id']), 409, 'Choose a starting blueprint first.');
            if ($request->input('language') !== $draft['language']) {
                throw ValidationException::withMessages(['language' => 'Return to setup to change the default language.']);
            }
            $request->merge(['visibility' => 'private', 'languages' => [$draft['language']]]);
            BlueprintInput::validated($request);
            $draft['builder'] = $request->only(['name', 'description', 'visibility', 'language', 'workflow', 'notebook_format', 'agents', 'structure', 'tools', 'package_sets', 'package_overrides', 'package_auto_attach', 'skill_sets', 'skills', 'excluded_skills', 'settings', 'scaffold', 'quarto', 'ide', 'publish_destination', 'extra_yaml', 'notes']);
            $section = $request->validate(['section' => ['required', Rule::in(['name', 'notebooks', 'quarto', 'jupyter', 'structure', 'environment', 'packages', 'scaffold', 'editor', 'ai', 'skills', 'git', 'publishing', 'review'])]])['section'];
            $draft['section'] = $section;
            $draft['step'] = 4;
        }
        $this->saveDraft($request, $draft);

        return response()->json(['saved' => true]);
    }

    private function draft(Request $request): ?array
    {
        $draft = $request->user()->onboarding_progress;
        if (! $draft) {
            $legacy = $request->session()->get(self::DRAFT);
            if (($legacy['user_id'] ?? null) === $request->user()->id) {
                $draft = $legacy;
                $this->saveDraft($request, $draft);
            }
        }

        return ($draft['user_id'] ?? null) === $request->user()->id ? $draft : null;
    }

    private function saveDraft(Request $request, ?array $draft): void
    {
        $request->user()->forceFill(['onboarding_progress' => $draft])->save();
        $request->session()->forget(self::DRAFT);
    }

    public function ready(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->setup_completed_at) {
            return to_route('welcome');
        }
        $language = ProjectDefaults::language($request->user());

        return Inertia::render('WelcomeReady', ['language' => $language, 'blueprint' => Library::blueprint(ProjectDefaults::blueprint($request->user(), $language), $request->user())]);
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->saveDraft($request, null);
        $request->user()->forceFill(['setup_completed_at' => $request->user()->setup_completed_at ?? now()])->save();

        return to_route('dashboard');
    }
}
