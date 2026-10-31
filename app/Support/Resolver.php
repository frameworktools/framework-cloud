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

    /** @param bool $withDefaults false when building a blueprint, which shouldn't carry anyone's defaults */
    public function __construct(private User $user, private bool $withDefaults = true) {}

    /**
     * @param  array{name?: string, language?: string, blueprint?: string, structure?: string, tools?: array<string, bool>, package_sets?: list<string>, skill_sets?: list<string>, skills?: list<string>, settings?: array<string, mixed>}  $choices
     * @return array{envelope: array<string, mixed>, blueprint: ?BlueprintVersion, structure: FacetVersion, pieces: list<FacetVersion>, layer: array<string, mixed>, language: string, unchanged: bool}
     */
    public function resolve(array $choices): array
    {
        if ($this->withDefaults) {
            $choices = ProjectDefaults::apply($this->user, $choices);
        }
        $this->pieces = collect();

        // Extra settings from YAML sit under the switches below
        if (filled($choices['extra_yaml'] ?? null)) {
            [$extra, $error] = Layer::fromYaml($choices['extra_yaml']);
            if ($error || ($problems = Layer::problems($extra, 'blueprint'))) {
                throw ValidationException::withMessages(['extra_yaml' => $error ?? implode("\n", $problems)]);
            }
        }

        if (filled($choices['blueprint'] ?? null)) {
            $this->blueprint = $this->findBlueprint($choices['blueprint']);
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
        }

        if (filled($choices['structure'] ?? null)) {
            $this->structure = $this->findPiece($choices['structure'], ['structure'], 'structure')->latestVersion;
        }

        if (! $this->structure) {
            throw ValidationException::withMessages(['structure' => 'Choose a blueprint or a project structure.']);
        }

        $language = $choices['language'] ?? $this->blueprint?->blueprint->language ?? 'r';
        if (! in_array($language, Project::LANGUAGES, true)) {
            throw ValidationException::withMessages(['language' => 'Choose r, python, or stata.']);
        }
        if ($problem = Project::typeProblem($language, $this->structure->layer['type'] ?? 'bare')) {
            throw ValidationException::withMessages(['language' => $problem]);
        }

        // A blueprint's pieces that don't fit the project's language are left
        // out quietly, such as an R blueprint's Quarto in a Stata project
        $this->pieces = $this->pieces->filter(fn (FacetVersion $v) => match (true) {
            $v->facet->kind === 'setting' => true,
            $v->facet->kind === 'tool' && $language === 'stata' => false,
            (bool) $v->facet->language => in_array($language, $v->facet->language, true),
            default => true,
        });

        foreach ($choices['tools'] ?? [] as $key => $on) {
            $tool = $this->findPiece(str_contains($key, '/') ? $key : "framework/{$key}", ['tool'], 'tools');
            if ($on && ! $this->pieces->has($tool->id)) {
                $this->pieces->put($tool->id, $tool->latestVersion);
            } elseif (! $on) {
                $this->pieces->forget($tool->id);
            }
        }

        foreach (['package_sets' => 'package_set', 'skill_sets' => 'skill_set'] as $field => $kind) {
            foreach ($choices[$field] ?? [] as $handle) {
                $piece = $this->findPiece($handle, [$kind], $field);
                $this->pieces->put($piece->id, $piece->latestVersion);
            }
        }

        foreach ($choices['skills'] ?? [] as $handle) {
            $skill = Skill::visibleTo($this->user)->with('owner', 'latestVersion')->get()->firstWhere('handle', $handle)
                ?? throw ValidationException::withMessages(['skills' => "There is no skill {$handle}."]);
            $this->settings['skills'][$skill->key] = $skill->layerEntry();
        }

        $this->applySettings(($choices['settings'] ?? []) + $this->changedDefaults($choices['default_settings'] ?? []));
        $this->checkTools($language, array_keys(array_filter($choices['tools'] ?? [])));

        $pieces = $this->pieces->values()->all();
        $layers = [
            ['kind' => 'base', 'key' => $this->structure->facet->handle, 'version' => $this->structure->version, 'layer' => $this->structure->layer],
            ...array_map(fn (FacetVersion $v) => ['kind' => $v->facet->layerKind(), 'key' => $v->facet->handle, 'version' => $v->version, 'layer' => $v->layer], $pieces),
            ['kind' => 'blueprint', 'key' => $this->blueprint?->blueprint->handle ?? 'choices', 'layer' => $this->settings],
        ];
        $layers[] = ProjectLayers::projectLayer(str((string) ($choices['name'] ?? 'project'))->slug()->value() ?: 'project', $language, $layers);

        if ($problems = Layer::envelopeProblems(['spec_version' => 1, 'name' => (string) ($choices['name'] ?? 'project'), 'layers' => $layers])) {
            throw ValidationException::withMessages(['settings' => implode("\n", $problems)]);
        }

        $unchanged = $this->blueprint && $this->unchangedFromBlueprint();

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
            'unchanged' => $unchanged,
        ];
    }

    /**
     * Make a project from resolved choices. An unchanged blueprint is pinned
     * by version; anything else records its exact pieces.
     *
     * @param  array<string, mixed>  $resolved  from resolve()
     */
    public function createProject(string $name, array $resolved, ?string $description = null, array $attributes = []): Project
    {
        $slug = str($name)->slug()->value();

        if ($slug === '' || $slug === 'create') {
            throw ValidationException::withMessages(['name' => 'Choose a different name.']);
        }
        if ($this->user->projects()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'You already have a project with this name.']);
        }

        // A deleted project keeps its name, so its old published links never point at a new project
        if ($this->user->projects()->onlyTrashed()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'You used this name for a project you deleted. Choose a different name.']);
        }

        return $this->user->projects()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'language' => $resolved['language'],
            'project_type' => $resolved['structure']->layer['type'] ?? 'bare',
            'blueprint_version_id' => $resolved['blueprint']?->id,
            'composition' => $resolved['unchanged'] ? null : [
                'structure_version_id' => $resolved['structure']->id,
                'piece_version_ids' => array_map(fn (FacetVersion $v) => $v->id, $resolved['pieces']),
                'layer' => $resolved['layer'],
            ],
        ] + $attributes);
    }

    /**
     * Settings go in the blueprint layer. Sensitive data also adds the
     * private and public folders and, when AI is on, the sensitive-data skill.
     */
    private function applySettings(array $settings): void
    {
        // A setting goes in only when it changes what the project would get
        $currentlySensitive = ProjectLayers::isSensitive([['layer' => $this->structure->layer], ['layer' => $this->settings]]);
        if (array_key_exists('sensitive', $settings) && (bool) $settings['sensitive'] !== $currentlySensitive) {
            $this->settings['security']['sensitive'] = (bool) $settings['sensitive'];
        }

        $aiUnchanged = ! is_array($settings['ai'] ?? null) && (bool) ($settings['ai'] ?? true) === ($this->settings['ai']['enabled'] ?? true);
        if (array_key_exists('ai', $settings) && ! $aiUnchanged) {
            $ai = is_array($settings['ai']) ? $settings['ai'] : ['enabled' => (bool) $settings['ai']];
            $this->settings['ai'] = array_filter([
                'enabled' => (bool) ($ai['enabled'] ?? true),
                'assistants' => Arr::only(array_map('boolval', $ai['assistants'] ?? []), ['claude', 'codex', 'copilot']) ?: null,
            ], fn ($v) => $v !== null);

            if (! $this->settings['ai']['enabled']) {
                unset($this->settings['skills']);
                $this->pieces = $this->pieces->reject(fn (FacetVersion $v) => $v->facet->kind === 'skill_set');
            }
        }

        if (array_key_exists('git', $settings) && (bool) $settings['git'] !== ($this->settings['git']['use_git'] ?? true)) {
            $this->settings['git']['use_git'] = (bool) $settings['git'];
        }

        $sensitive = ProjectLayers::isSensitive([['layer' => $this->structure->layer], ['layer' => $this->settings]]);
        $structureSplits = isset($this->structure->layer['directories']['inputs_private_raw']);

        if ($sensitive && ! $structureSplits) {
            $layer = Facet::whereNull('user_id')->where('kind', 'setting')->where('key', 'sensitive_data')->firstOrFail()->latestVersion;
            $this->pieces->put($layer->facet_id, $layer);
        }

        if ($sensitive && ($this->settings['ai']['enabled'] ?? true)) {
            $this->settings['skills']['framework-sensitive-data'] = ['package' => true];
        }
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

            if ($language === 'stata') {
                throw ValidationException::withMessages(['tools' => 'Stata projects have no tools.']);
            }
            if ($facet->language && ! in_array($language, $facet->language, true)) {
                throw ValidationException::withMessages(['tools' => "{$facet->name} is not available for ".Project::LANGUAGE_NAMES[$language].' projects.']);
            }
            foreach ($facet->requires ?? [] as $required) {
                if (! in_array($required, $keys, true)) {
                    throw ValidationException::withMessages(['tools' => "{$facet->name} needs the {$required} tool."]);
                }
            }
        }
    }

    /** Whether the choices came out the same as the blueprint, so the project can pin it. */
    private function unchangedFromBlueprint(): bool
    {
        return $this->structure->id === $this->blueprint->base_version_id
            && $this->pieces->pluck('id')->values()->all() === $this->blueprint->facetVersions->pluck('id')->values()->all()
            && $this->settings == ($this->blueprint->layer ?? []);
    }

    /**
     * Default settings that differ from Framework's own (git on, AI on,
     * sensitive off) and that the blueprint doesn't set. The rest would
     * change nothing.
     */
    private function changedDefaults(array $defaults): array
    {
        $builtIn = ['sensitive' => false, 'git' => true, 'ai' => true];
        $set = ['sensitive' => isset($this->settings['security']['sensitive']), 'git' => isset($this->settings['git']['use_git']), 'ai' => isset($this->settings['ai'])];

        return array_filter($defaults, fn ($value, $key) => isset($builtIn[$key]) && $value !== $builtIn[$key] && ! $set[$key], ARRAY_FILTER_USE_BOTH);
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
        $piece = Facet::visibleTo($this->user)->whereIn('kind', $kinds)->with('owner', 'latestVersion')->get()
            ->first(fn (Facet $f) => $f->handle === $handle || ($f->isSystem() && $f->key === $handle));

        return $piece ?? throw ValidationException::withMessages([$field => "There is no {$this->noun($kinds[0])} {$handle}."]);
    }

    private function noun(string $kind): string
    {
        return ['structure' => 'project structure', 'tool' => 'tool', 'package_set' => 'package set', 'skill_set' => 'skill set'][$kind] ?? $kind;
    }
}
