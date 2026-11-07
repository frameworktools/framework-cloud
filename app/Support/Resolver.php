<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns a person's choices for a new project into a layers envelope: a
 * blueprint or a structure, tools switched on or off, package sets, skill
 * sets, single skills, and settings. The result has the same shape as a
 * project's layers, so R, Python, and fw scaffold it the same way.
 *
 * Pieces resolve by handle (framework/quarto, ada/survey-packages) among
 * the ones the user can see, at their latest version.
 */
class Resolver
{
    private Collection $pieces;

    private array $settings = [];

    private ?FacetVersion $structure = null;

    private ?BlueprintVersion $blueprint = null;

    private string $language = 'r';

    /** @var list<string> */
    private array $notices = [];

    /** @var array<string, FacetVersion> */
    private array $workflowPieces = [];

    /**
     * @param  bool  $withDefaults  false when building a blueprint, which shouldn't carry anyone's defaults
     * @param  array<string, mixed>|null  $defaults  other defaults than the saved ones, to preview them
     */
    public function __construct(private User $user, private bool $withDefaults = true, private ?array $defaults = null) {}

    /**
     * Resolve choices into layers, bottom to top, so the shared merge (later
     * layers win) applies one precedence everywhere:
     *
     * 1. the project structure,
     * 2. the workflow, then pieces: tools, package sets, and skill sets,
     * 3. the blueprint's own values (its defaults),
     * 4. the person's defaults (settings): only what they set apart from
     *    Framework's own, plus their editor and agents,
     * 5. choices made for this project (project choices),
     * 6. the blueprint's requirements, which nothing overrides,
     * 7. the project itself: its language and, with the tools key, every tool.
     *
     * A creation choice that conflicts with a requirement is refused. A
     * default that conflicts is set aside, and the result says so.
     *
     * @param  array{name?: string, language?: string, blueprint?: string, structure?: string, workflow?: string, notebook_format?: string, tools?: array<string, bool>, package_sets?: list<string>, skill_sets?: list<string>, skills?: list<string>, settings?: array<string, mixed>}  $choices
     * @return array{envelope: array<string, mixed>, blueprint: ?BlueprintVersion, structure: FacetVersion, pieces: list<FacetVersion>, layer: array<string, mixed>, language: string, workflow: ?string, unchanged: bool, notices: list<string>, layers: array<string, array<string, mixed>>}
     */
    public function resolve(array $choices): array
    {
        $defaults = $this->withDefaults ? ($this->defaults ?? ProjectDefaults::for($this->user)) : null;
        $this->pieces = collect();
        $this->notices = [];
        $chose = [];

        // Extra settings from YAML sit under the switches below
        if (filled($choices['extra_yaml'] ?? null)) {
            [$extra, $error] = Layer::fromYaml($choices['extra_yaml']);
            if ($error || ($problems = Layer::problems($extra, 'blueprint'))) {
                throw ValidationException::withMessages(['extra_yaml' => $error ?? implode("\n", $problems)]);
            }
        }

        // Without a blueprint or structure, the person's default blueprint
        $explicit = filled($choices['blueprint'] ?? null);
        if (! $explicit && blank($choices['structure'] ?? null) && $defaults) {
            $choices['blueprint'] = $defaults['blueprint'];
        }
        $language = $choices['language'] ?? $defaults['language'] ?? null;

        if (filled($choices['blueprint'] ?? null)) {
            $this->blueprint = $this->findBlueprint($choices['blueprint']);
            $language ??= $this->blueprint->blueprint->language ?? 'r';
            $supported = $this->blueprint->compatibility()['languages'];
            if ($supported && ! in_array($language, $supported, true)) {
                if ($explicit) {
                    throw ValidationException::withMessages(['language' => "{$this->blueprint->blueprint->name} supports ".$this->languageList($supported).' projects.']);
                }
                $this->notices[] = "Your default blueprint, {$this->blueprint->blueprint->name}, doesn't support ".Project::LANGUAGE_NAMES[$language].' projects, so this one uses the standard project.';
                $this->blueprint = $this->findBlueprint('framework/project');
            }
            $this->structure = $this->blueprint->base;
            $this->pieces = $this->blueprint->facetVersions->keyBy('facet_id');
            $this->settings = $this->blueprint->layer ?? [];
        } elseif (filled($choices['from'] ?? null)) {
            // Chosen piece by piece, starting from a blueprint: kept for
            // provenance, so an unchanged result still pins it
            $this->blueprint = $this->findBlueprint($choices['from']);
        }

        if (isset($extra)) {
            $this->settings = array_replace_recursive($this->settings, $extra);
            $chose[] = 'extra_yaml';
        }

        if (filled($choices['structure'] ?? null)) {
            $this->structure = $this->findPiece($choices['structure'], ['structure'], 'structure')->latestVersion;
            $chose[] = 'structure';
        }

        if (! $this->structure) {
            throw ValidationException::withMessages(['structure' => 'Choose a blueprint or a project structure.']);
        }

        $language ??= 'r';
        if (! in_array($language, Project::LANGUAGES, true)) {
            throw ValidationException::withMessages(['language' => 'Choose r, python, or stata.']);
        }
        if ($problem = Project::typeProblem($language, $this->structure->layer['type'] ?? 'bare')) {
            throw ValidationException::withMessages(['language' => $problem]);
        }

        // A blueprint's pieces that don't fit the project's language are left
        // out quietly, such as uv in an R project
        $this->language = $language;
        $this->pieces = $this->pieces->filter(fn (FacetVersion $v) => $this->fits($v));
        $compatibility = $this->blueprint && filled($choices['blueprint'] ?? null) ? $this->blueprint->compatibility() : null;

        // The workflow, and the tools it and the blueprint need
        $workflow = $this->withDefaults || filled($choices['workflow'] ?? null) ? $this->workflow($choices, $defaults, $compatibility) : null;
        $required = $compatibility['requires_tools'] ?? [];
        if ($workflow) {
            $piece = $this->workflowPiece($workflow);
            $this->pieces->put($piece->facet_id, $piece);
            $required = array_values(array_unique([...($piece->facet->requires ?? []), ...$required]));
        }
        foreach ($required as $key) {
            $tool = $this->findPiece("framework/{$key}", ['tool'], 'tools');
            $this->pieces->put($tool->id, $tool->latestVersion);
        }

        // The person's tools and sets: a tool they turned off stays when
        // something requires it
        foreach ($defaults['tools'] ?? [] as $key => $on) {
            $tool = Facet::whereNull('user_id')->where('kind', 'tool')->where('key', $key)->with('latestVersion')->first();
            if (! $tool || ! $this->fits($tool->latestVersion)) {
                continue;
            }
            if ($on) {
                $this->pieces->put($tool->id, $tool->latestVersion);
            } elseif (! in_array($key, $required, true)) {
                $this->pieces->forget($tool->id);
            }
        }
        // A project that lists its own sets has chosen them, so yours aren't added
        foreach (['package_sets' => 'package_set', 'skill_sets' => 'skill_set'] as $field => $kind) {
            if (array_key_exists($field, $choices)) {
                continue;
            }
            foreach ($defaults[$field] ?? [] as $handle) {
                $piece = Facet::visibleTo($this->user)->where('kind', $kind)->with('owner', 'latestVersion')->get()->first(fn (Facet $f) => $f->handle === $handle);
                if ($piece && $this->fits($piece->latestVersion)) {
                    $this->pieces->put($piece->id, $piece->latestVersion);
                }
            }
        }

        // Choices for this project
        foreach ($choices['tools'] ?? [] as $key => $on) {
            $handle = str_contains($key, '/') ? $key : "framework/{$key}";
            // A tool this server doesn't have changes nothing when it's off
            if (! $on && ! $this->hasPiece($handle, ['tool'])) {
                continue;
            }
            $tool = $this->findPiece($handle, ['tool'], 'tools');
            if ($on) {
                $this->pieces->put($tool->id, $tool->latestVersion);
            } elseif (in_array($tool->key, $required, true)) {
                throw ValidationException::withMessages(['tools' => "{$tool->name} is required by ".($workflow && in_array($tool->key, $this->workflowPiece($workflow)->facet->requires ?? [], true) ? "the {$this->workflowPiece($workflow)->facet->name} workflow" : 'the blueprint').'.']);
            } else {
                $this->pieces->forget($tool->id);
            }
            $chose[] = 'tools';
        }
        // A chosen set for another language is left out, with a notice, such
        // as an R package set in a Python project
        foreach (['package_sets' => 'package_set', 'skill_sets' => 'skill_set'] as $field => $kind) {
            foreach ($choices[$field] ?? [] as $handle) {
                $piece = $this->findPiece($handle, [$kind], $field);
                if ($this->fits($piece->latestVersion)) {
                    $this->pieces->put($piece->id, $piece->latestVersion);
                } else {
                    $this->notices[] = "{$piece->name} is for ".implode(' and ', array_map(fn ($l) => Project::LANGUAGE_NAMES[$l] ?? $l, $piece->language ?? [])).', so this '.Project::LANGUAGE_NAMES[$this->language].' project leaves it out.';
                }
                $chose[] = $field;
            }
        }

        $personal = $defaults ? $this->personalLayer($defaults, $workflow) : [];
        $project = $this->choicesLayer($choices, $workflow);
        // Building a blueprint: its settings are the blueprint's own
        if (! $this->withDefaults) {
            $this->settings = array_replace_recursive($this->settings, $project);
            $project = [];
        }
        if ($project) {
            $chose[] = 'settings';
        }
        $requires = $compatibility['requires'] ?? [];
        // The person's settings are theirs alone, and other people's content can't write files that run on their own
        $this->settings = Trust::withoutPersonal($this->settings);
        $requires = Trust::withoutPersonal($requires);
        $this->checkTrust($requires);
        $this->derived($personal, $project, $requires);
        $this->checkTools($language, array_keys(array_filter($choices['tools'] ?? [])));

        $pieces = $this->pieces->values()->all();
        $named = ['blueprint' => $this->settings, 'settings' => $personal, 'choices' => $project, 'requires' => $requires];
        $layers = [
            ['kind' => 'base', 'key' => $this->structure->facet->handle, 'version' => $this->structure->version, 'layer' => ProjectLayers::pieceLayer($this->structure->facet, $this->structure->layer)],
            ...collect($pieces)->flatMap(fn (FacetVersion $v) => ProjectLayers::entries($v, $language))->all(),
            ['kind' => 'blueprint', 'key' => $this->blueprint?->blueprint->handle ?? 'choices', 'layer' => $this->settings],
        ];
        if ($personal) {
            $layers[] = ['kind' => 'settings', 'key' => 'your defaults', 'layer' => $personal];
        }
        $slug = str((string) ($choices['name'] ?? 'project'))->slug()->value() ?: 'project';
        if ($project) {
            $layers[] = ['kind' => 'project', 'key' => "{$slug} choices", 'layer' => $project];
        }
        if ($requires) {
            $layers[] = ['kind' => 'blueprint', 'key' => ($this->blueprint?->blueprint->handle ?? 'blueprint').' requires', 'layer' => $requires];
        }
        $layers[] = ProjectLayers::projectLayer($slug, $language, $layers);

        if ($problems = Layer::envelopeProblems(['spec_version' => 1, 'name' => (string) ($choices['name'] ?? 'project'), 'layers' => $layers])) {
            throw ValidationException::withMessages(['settings' => implode("\n", $problems)]);
        }

        $unchanged = $this->blueprint && filled($choices['blueprint'] ?? null) && ! $chose;

        return [
            'envelope' => ProjectLayers::envelope(
                $this->user,
                (string) ($choices['name'] ?? 'Untitled project'),
                $layers,
                $unchanged ? ['key' => $this->blueprint->blueprint->handle, 'version' => $this->blueprint->version] : null,
            ),
            'blueprint' => $this->blueprint,
            'structure' => $this->structure,
            'pieces' => $pieces,
            'layer' => $this->settings,
            'language' => $language,
            'workflow' => $workflow,
            'unchanged' => $unchanged,
            'notices' => $this->notices,
            'layers' => $named,
        ];
    }

    /**
     * The workflow: this project's choice, else the person's for the
     * language, else the blueprint's default, else the first it supports.
     * A choice the blueprint or language can't take is refused; a default
     * it can't take is set aside with a notice.
     */
    private function workflow(array $choices, ?array $defaults, ?array $compatibility): string
    {
        $supported = array_values(array_filter($compatibility['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS, fn (string $w) => ProjectDefaults::workflowFits($w, $this->language)));
        if (! $supported) {
            throw ValidationException::withMessages(['workflow' => 'This blueprint has no workflow for '.Project::LANGUAGE_NAMES[$this->language].' projects.']);
        }

        if (filled($chosen = $choices['workflow'] ?? null)) {
            if (! in_array($chosen, $supported, true)) {
                throw ValidationException::withMessages(['workflow' => 'Choose '.implode(' or ', array_map(fn (string $w) => $this->workflowPiece($w)->facet->name, $supported)).' for this project.']);
            }

            return $chosen;
        }

        // Your workflow, then the blueprint's default, then Framework's
        $preferred = $defaults['workflows'][$this->language] ?? null;
        if ($preferred && in_array($preferred, $supported, true)) {
            return $preferred;
        }
        $fallback = collect([$compatibility['default_workflow'][$this->language] ?? null, ProjectDefaults::WORKFLOWS[$this->language]])
            ->first(fn ($w) => in_array($w, $supported, true)) ?? $supported[0];
        if ($preferred) {
            $this->notices[] = "This blueprint uses the {$this->workflowPiece($fallback)->facet->name} workflow, so your {$this->workflowPiece($preferred)->facet->name} default doesn't apply.";
        }

        return $fallback;
    }

    private function workflowPiece(string $workflow): FacetVersion
    {
        return $this->workflowPieces[$workflow] ??= Facet::whereNull('user_id')->where('kind', 'workflow')->where('category', $workflow)->with('latestVersion.facet')->firstOrFail()->latestVersion;
    }

    /**
     * The person's defaults layer for this project: their settings apart
     * from Framework's own, R Markdown under the Quarto workflow, and their
     * package list. R starts every project with Framework's own list, so a
     * package taken out of it is removed with a null.
     *
     * @return array<string, mixed>
     */
    private function personalLayer(array $defaults, ?string $workflow): array
    {
        $layer = ProjectDefaults::personalLayer($defaults, $this->language);

        if ($workflow && ProjectDefaults::notebookFormat($workflow, $this->language, $defaults['rmarkdown']) === 'rmarkdown') {
            $layer['scaffold']['notebook_format'] = 'rmarkdown';
        }

        $rows = $defaults['packages'][$this->language] ?? null;
        if ($rows !== null && $rows !== ProjectDefaults::packages(ProjectDefaults::DEFAULT_PACKAGES)[$this->language]) {
            $entries = ProjectDefaults::packageLayer($rows);
            if ($this->language === 'r') {
                $fromPieces = $this->pieces->flatMap(fn (FacetVersion $v) => array_keys($v->layer['packages']['default_packages'] ?? []))->all();
                foreach (array_column(ProjectDefaults::DEFAULT_PACKAGES['r'], 'name') as $name) {
                    if (! isset($entries[$name]) && ! in_array($name, $fromPieces, true)) {
                        $entries[$name] = null;
                    }
                }
            }
            if ($entries) {
                $layer['packages']['default_packages'] = $entries;
            }
        }

        return $layer;
    }

    /**
     * Choices for this project as a layer: sensitive data, Git, AI, hooks,
     * single skills, and R Markdown under the Quarto workflow.
     *
     * @return array<string, mixed>
     */
    private function choicesLayer(array $choices, ?string $workflow): array
    {
        $settings = $choices['settings'] ?? [];
        $layer = [];

        if (array_key_exists('sensitive', $settings)) {
            $layer['security']['sensitive'] = (bool) $settings['sensitive'];
        }
        if (array_key_exists('ai', $settings)) {
            $ai = is_array($settings['ai']) ? $settings['ai'] : ['enabled' => (bool) $settings['ai']];
            $layer['ai'] = array_filter([
                'enabled' => (bool) ($ai['enabled'] ?? true),
                'assistants' => Arr::only(array_map('boolval', $ai['assistants'] ?? []), ProjectDefaults::AGENTS) ?: null,
            ], fn ($v) => $v !== null);
        }
        if (array_key_exists('git', $settings)) {
            $layer['git']['use_git'] = (bool) $settings['git'];
        }
        foreach (array_intersect($settings['hooks'] ?? [], ProjectDefaults::HOOKS) as $hook) {
            $layer['git']['hooks'][$hook] = true;
        }

        foreach ($choices['skills'] ?? [] as $handle) {
            $skill = Skill::visibleTo($this->user)->with('owner', 'latestVersion')->get()->firstWhere('handle', $handle)
                ?? throw ValidationException::withMessages(['skills' => "There is no skill {$handle}."]);
            // A skill for another language is left out, as sets are
            if (! $skill->fits($this->language)) {
                $this->notices[] = "{$skill->name} is for ".implode(' and ', array_map(fn ($l) => Project::LANGUAGE_NAMES[$l] ?? $l, $skill->language)).', so this '.Project::LANGUAGE_NAMES[$this->language].' project leaves it out.';

                continue;
            }
            $layer['skills'][$skill->key] = $skill->layerEntry($this->language);
        }

        if (filled($format = $choices['notebook_format'] ?? null)) {
            if ($format !== ProjectDefaults::notebookFormat($workflow ?? '', $this->language, $format === 'rmarkdown')) {
                throw ValidationException::withMessages(['notebook_format' => 'R Markdown is for R projects with the Quarto workflow.']);
            }
            $layer['scaffold']['notebook_format'] = $format;
        }

        return $layer;
    }

    /**
     * What follows from the layers as a whole: sensitive data adds the
     * private and public folders and, with AI on, the sensitive-data skill;
     * AI off leaves out skill sets and skills; Git off leaves out hooks.
     */
    private function derived(array &$personal, array &$project, array $requires): void
    {
        $stack = [$this->structure->layer, $this->settings, $personal, $project, $requires];
        $value = function (string $path, $default) use (&$stack) {
            $out = $default;
            foreach ($stack as $layer) {
                if (Arr::has($layer, $path)) {
                    $out = Arr::get($layer, $path);
                }
            }

            return $out;
        };

        if ($value('ai.enabled', true) === false) {
            $this->pieces = $this->pieces->reject(fn (FacetVersion $v) => $v->facet->kind === 'skill_set');
            unset($this->settings['skills'], $project['skills']);
        }

        if ($value('git.use_git', true) === false) {
            unset($personal['git']['hooks'], $project['git']['hooks']);
        }

        if ($value('security.sensitive', false) === true) {
            if (! isset($this->structure->layer['directories']['inputs_private_raw'])) {
                $layer = Facet::whereNull('user_id')->where('kind', 'setting')->where('key', 'sensitive_data')->firstOrFail()->latestVersion;
                $this->pieces->put($layer->facet_id, $layer);
            }
            if ($value('ai.enabled', true) !== false) {
                $project['skills']['framework-sensitive-data'] = ['package' => true];
            }
        }
    }

    /**
     * Refuse files that run on their own when they come from a blueprint or
     * piece someone else owns. Framework's own content is trusted.
     */
    private function checkTrust(array $requires): void
    {
        $theirs = fn ($owner) => $owner !== null && $owner !== $this->user->id;
        $blocked = [];

        if ($this->blueprint && $theirs($this->blueprint->blueprint->user_id)) {
            $blocked = [...Trust::blockedFiles($this->settings), ...Trust::blockedFiles($requires)];
        }
        foreach ($this->pieces as $piece) {
            if ($theirs($piece->facet->user_id)) {
                array_push($blocked, ...Trust::blockedFiles($piece->layer ?? []));
            }
        }

        if ($blocked) {
            throw ValidationException::withMessages(['blueprint' => 'This blueprint or one of its pieces adds files that would run on their own, which content from other people can\'t do: '.implode(', ', array_unique($blocked)).'.']);
        }
    }

    /** @param list<string> $languages */
    private function languageList(array $languages): string
    {
        $names = array_map(fn (string $l) => Project::LANGUAGE_NAMES[$l], $languages);

        return count($names) > 1 ? implode(', ', array_slice($names, 0, -1)).(count($names) > 2 ? ',' : '').' and '.end($names) : $names[0];
    }

    /**
     * Make a project from resolved choices. An unchanged blueprint is pinned
     * by version; anything else records its exact pieces.
     *
     * @param  array<string, mixed>  $resolved  from resolve()
     */
    public function createProject(string $name, array $resolved, ?string $description = null, array $attributes = []): Project
    {
        $slug = $this->availableSlug($name, $attributes['slug'] ?? null);
        unset($attributes['slug']);

        return $this->user->projects()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'language' => $resolved['language'],
            'project_type' => $resolved['structure']->layer['type'] ?? 'bare',
            'blueprint_version_id' => $resolved['blueprint']?->id,
            // The project keeps what it was resolved from
            'resolved' => json_encode(['name' => $name] + $resolved['envelope'], JSON_UNESCAPED_SLASHES),
            'composition' => $resolved['unchanged'] ? null : [
                'structure_version_id' => $resolved['structure']->id,
                'piece_version_ids' => array_map(fn (FacetVersion $v) => $v->id, $resolved['pieces']),
                'layer' => $resolved['layer'],
            ],
        ] + $attributes);
    }

    /**
     * The project's slug: the one asked for, or one made from the name. It
     * must be free, including among projects in the Trash, which keep theirs
     * so old published links never point at a new project.
     */
    public function availableSlug(string $name, ?string $slug = null): string
    {
        $field = $slug === null ? 'name' : 'slug';
        $slug ??= str($name)->slug()->value();

        if ($slug === '' || $slug === 'create') {
            throw ValidationException::withMessages([$field => 'Choose a different name.']);
        }
        if ($this->user->projects()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([$field => $field === 'slug' ? "You already have a project at {$slug}." : 'You already have a project with this name.']);
        }
        if ($this->user->projects()->onlyTrashed()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([$field => $field === 'slug' ? "You used {$slug} for a project you deleted. Choose a different slug." : 'You used this name for a project you deleted. Choose a different name.']);
        }

        return $slug;
    }

    /**
     * Tools chosen here must match the language and bring what they need,
     * and Stata has none. A blueprint's own tools were checked when it was
     * saved, or predate the rules.
     *
     * @param  list<string>  $chosen  tool keys or handles switched on in this request
     */
    private function checkTools(string $language, array $chosen): void
    {
        $chosen = array_map(fn (string $key) => str_contains($key, '/') ? $key : "framework/{$key}", $chosen);
        $tools = $this->pieces->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool');
        $keys = $tools->map(fn (FacetVersion $v) => $v->facet->key)->all();
        $tools = $tools->filter(fn (FacetVersion $v) => in_array($v->facet->handle, $chosen, true) || $this->withDefaults === false);

        foreach ($tools as $tool) {
            $facet = $tool->facet;

            if ($this->withDefaults && $language === 'stata') {
                throw ValidationException::withMessages(['tools' => 'Stata projects have no tools.']);
            }
            // A blueprint can carry tools for several languages; a project can't
            if ($this->withDefaults && $facet->language && ! in_array($language, $facet->language, true)) {
                throw ValidationException::withMessages(['tools' => "{$facet->name} is not available for ".Project::LANGUAGE_NAMES[$language].' projects.']);
            }
            if ($clash = array_values(array_intersect($facet->conflicts ?? [], $keys))) {
                throw ValidationException::withMessages(['tools' => "{$facet->name} can't be combined with the {$clash[0]} tool."]);
            }
            foreach ($facet->requires ?? [] as $required) {
                if (! in_array($required, $keys, true)) {
                    throw ValidationException::withMessages(['tools' => "{$facet->name} needs the {$required} tool."]);
                }
            }
        }
    }

    private function fits(FacetVersion $version): bool
    {
        return ProjectLayers::fits($version, $this->language);
    }

    private function findBlueprint(string $handle): BlueprintVersion
    {
        $blueprint = Blueprint::visibleTo($this->user)->with('owner', 'latestVersion.base.facet.owner', 'latestVersion.facetVersions.facet.owner')
            ->get()->first(fn (Blueprint $b) => $b->handle === $handle || ($b->isSystem() && $b->key === $handle));

        return $blueprint?->latestVersion ?? throw ValidationException::withMessages(['blueprint' => "There is no blueprint {$handle}."]);
    }

    /** @param list<string> $kinds */
    private function findPiece(string $handle, array $kinds, string $field): Facet
    {
        $piece = $this->lookUpPiece($handle, $kinds);
        if ($piece) {
            return $piece;
        }
        // For tools, say which ones exist (GET /api/v1/tools lists them too)
        $known = $kinds === ['tool']
            ? ' Tools: '.Facet::visibleTo($this->user)->where('kind', 'tool')->orderBy('position')->pluck('key')->implode(', ').'.'
            : '';

        throw ValidationException::withMessages([$field => "There is no {$this->noun($kinds[0])} {$handle}.{$known}"]);
    }

    private function hasPiece(string $handle, array $kinds): bool
    {
        return $this->lookUpPiece($handle, $kinds) !== null;
    }

    private function lookUpPiece(string $handle, array $kinds): ?Facet
    {
        return Facet::visibleTo($this->user)->whereIn('kind', $kinds)->with('owner', 'latestVersion')->get()
            ->first(fn (Facet $f) => $f->handle === $handle || ($f->isSystem() && $f->key === $handle));
    }

    private function noun(string $kind): string
    {
        return ['structure' => 'project structure', 'tool' => 'tool', 'package_set' => 'package set', 'skill_set' => 'skill set', 'workflow' => 'workflow'][$kind] ?? $kind;
    }
}
