<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\FacetVersion;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Blueprint choices as the builder and the API send them: a structure, tools, package sets, skill
 * sets, skills, settings, and extra YAML. Validated and resolved the same way for both, so an agent
 * saves exactly what the builder would.
 */
class BlueprintInput
{
    /**
     * The builder sends choices; the resolver turns them into the structure,
     * pieces, and settings layer a version pins. Nobody's defaults apply.
     *
     * @return array{0: array<string, mixed>, 1: FacetVersion, 2: list<int>, 3: array<string, mixed>, 4: ?string}
     */
    public static function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(Blueprint::VISIBILITIES)],
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'structure' => ['required', 'string', 'max:200'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'package_sets.*' => ['string', 'max:200'],
            'skill_sets' => ['nullable', 'array'],
            'skill_sets.*' => ['string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:200'],
            'settings' => ['nullable', 'array'],
            'settings.sensitive' => ['boolean'],
            'settings.git' => ['boolean'],
            'settings.ai' => ['boolean'],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'languages' => ['sometimes', 'array', 'min:1'],
            'languages.*' => [Rule::in(Project::LANGUAGES)],
            'workflows' => ['sometimes', 'array', 'min:1'],
            'workflows.*' => [Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
        ]);
        $compatibility = self::compatibility($data);

        $resolved = (new Resolver($request->user(), withDefaults: false))->resolve(Arr::only($data, [
            'name', 'language', 'structure', 'tools', 'package_sets', 'skill_sets', 'skills', 'settings', 'extra_yaml',
        ]));

        return [
            array_map(fn ($v) => is_string($v) ? trim($v) : $v, Arr::only($data, ['name', 'description', 'visibility', 'language'])),
            $resolved['structure'],
            array_map(fn (FacetVersion $v) => $v->id, $resolved['pieces']),
            $resolved['layer'],
            $data['notes'] ?? null,
            $compatibility,
        ];
    }

    /**
     * What the blueprint supports, checked so every language it lists has a
     * workflow: Stata has only Scripts only. Everything listed is stored as
     * nothing listed, which means no restriction. What isn't sent stays as
     * it was.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function compatibility(array $data): array
    {
        if (! isset($data['languages']) && ! isset($data['workflows'])) {
            return [];
        }
        $languages = array_values(array_intersect(Project::LANGUAGES, $data['languages'] ?? Project::LANGUAGES));
        $workflows = array_values(array_intersect(ProjectDefaults::WORKFLOW_KEYS, $data['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS));

        if (! in_array($data['language'], $languages, true)) {
            throw ValidationException::withMessages(['languages' => 'Include the default language, '.Project::LANGUAGE_NAMES[$data['language']].'.']);
        }
        foreach ($languages as $language) {
            if (! array_filter($workflows, fn (string $w) => ProjectDefaults::workflowFits($w, $language))) {
                throw ValidationException::withMessages(['workflows' => Project::LANGUAGE_NAMES[$language].' projects need '.($language === 'stata' ? 'Scripts only.' : 'one of these workflows.')]);
            }
        }

        return [
            'languages' => count($languages) === count(Project::LANGUAGES) ? null : $languages,
            'workflows' => count($workflows) === count(ProjectDefaults::WORKFLOW_KEYS) ? null : $workflows,
        ];
    }

    /**
     * The files and folders a project from unsaved blueprint choices would get, for one language
     * and workflow. Nobody's defaults apply, as when the blueprint is saved.
     */
    public static function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(Project::LANGUAGES)],
            'workflow' => ['required', Rule::in(ProjectDefaults::WORKFLOW_KEYS)],
            'structure' => ['required', 'string', 'max:200'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['boolean'],
            'package_sets' => ['nullable', 'array'],
            'skill_sets' => ['nullable', 'array'],
            'skills' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'extra_yaml' => ['nullable', 'string', 'max:20000'],
        ]);

        try {
            $resolved = (new Resolver($request->user(), withDefaults: false))->resolve(['name' => 'project'] + $data);
        } catch (ValidationException $e) {
            return response()->json(['problem' => collect($e->errors())->flatten()->first()], 422);
        }
        $spec = ['name' => 'project'] + DefaultsPreview::merge($resolved['envelope']['layers']);
        $tools = collect($resolved['pieces'])->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool')->map(fn (FacetVersion $v) => $v->facet->key)->values()->all();
        $sensitive = (bool) ($spec['security']['sensitive'] ?? false);

        return response()->json([
            'tree' => ProjectTree::for($spec, $resolved['language'], $tools, $sensitive),
            'render_dirs' => collect((array) ($spec['render_dirs'] ?? []))->map(fn ($to, $key) => ['from' => $spec['directories'][$key] ?? $key, 'to' => $to])->values()->all(),
            'sensitive' => $sensitive,
        ]);
    }
}
