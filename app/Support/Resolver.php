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

    public function __construct(private User $user) {}

    /**
     * @param  array{name?: string, language?: string, blueprint?: string, structure?: string, tools?: array<string, bool>, package_sets?: list<string>, skill_sets?: list<string>, skills?: list<string>, settings?: array<string, mixed>}  $choices
     * @return array{envelope: array<string, mixed>, blueprint: ?BlueprintVersion, structure: FacetVersion, pieces: list<FacetVersion>, layer: array<string, mixed>, language: string}
     */
    public function resolve(array $choices): array
    {
        $this->pieces = collect();

        if (filled($choices['blueprint'] ?? null)) {
            $this->blueprint = $this->findBlueprint($choices['blueprint']);
            $this->structure = $this->blueprint->base;
            $this->pieces = $this->blueprint->facetVersions->keyBy('facet_id');
            $this->settings = $this->blueprint->layer ?? [];
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
            throw ValidationException::withMessages(['structure' => $problem]);
        }

        foreach ($choices['tools'] ?? [] as $key => $on) {
            $tool = $this->findPiece(str_contains($key, '/') ? $key : "framework/{$key}", ['tool'], 'tools');
            $on ? $this->pieces->put($tool->id, $tool->latestVersion) : $this->pieces->forget($tool->id);
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

        $this->applySettings($choices['settings'] ?? []);
        $this->checkTools($language);

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

        $unchanged = $this->blueprint && $this->unchangedFromBlueprint($choices);

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
        ];
    }

    /**
     * Settings go in the blueprint layer. Sensitive data also adds the
     * private and public folders and, when AI is on, the sensitive-data skill.
     */
    private function applySettings(array $settings): void
    {
        if (array_key_exists('sensitive', $settings)) {
            $this->settings['security']['sensitive'] = (bool) $settings['sensitive'];
        }

        if (array_key_exists('ai', $settings)) {
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

        if (array_key_exists('git', $settings)) {
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

    /** Tools must match the language, bring what they need, and Stata has none. */
    private function checkTools(string $language): void
    {
        $tools = $this->pieces->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool');
        $keys = $tools->map(fn (FacetVersion $v) => $v->facet->key)->all();

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

    private function unchangedFromBlueprint(array $choices): bool
    {
        return blank($choices['structure'] ?? null) && empty($choices['tools']) && empty($choices['package_sets'])
            && empty($choices['skill_sets']) && empty($choices['skills']) && empty($choices['settings']);
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
