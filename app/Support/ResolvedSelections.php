<?php

namespace App\Support;

use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\Skill;
use App\Models\User;

/**
 * A resolved project as the New project form shows and edits it: the
 * structure and workflow, every tool for the language as on or off with
 * why a required one can't be turned off, the package and skill sets with
 * where each came from, single skills, and the resulting settings.
 */
class ResolvedSelections
{
    /**
     * @param  array<string, mixed>  $resolved  from Resolver::resolve()
     * @return array<string, mixed>
     */
    public static function for(User $user, array $resolved, BlueprintVersion $version, bool $customized): array
    {
        $language = $resolved['language'];
        $pieces = collect($resolved['pieces']);
        $defaults = ProjectDefaults::for($user);
        $fromBlueprint = $version->facetVersions->pluck('facet_id')->all();
        $from = fn (FacetVersion $v, string $field) => match (true) {
            $customized => 'chosen',
            in_array($v->facet_id, $fromBlueprint, true) => 'blueprint',
            in_array($v->facet->handle, $defaults[$field] ?? [], true) => 'yours',
            default => 'blueprint',
        };

        // Why a tool can't be removed: the workflow or the blueprint needs it
        $workflow = $pieces->first(fn (FacetVersion $v) => $v->facet->kind === 'workflow');
        $required = [];
        foreach ($workflow?->facet->requires ?? [] as $key) {
            $required[$key] = "Required by the {$workflow->facet->name} workflow";
        }
        if (! $customized) {
            foreach ($version->compatibility()['requires_tools'] as $key) {
                $required[$key] ??= 'Required by the blueprint';
            }
        }

        $on = $pieces->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool')->map(fn (FacetVersion $v) => $v->facet->key)->all();
        $tools = $language === 'stata' ? [] : Facet::whereNull('user_id')->where('kind', 'tool')->orderBy('position')->get()
            ->filter(fn (Facet $t) => ! $t->language || in_array($language, $t->language, true))
            ->mapWithKeys(fn (Facet $t) => [$t->key => ['on' => in_array($t->key, $on, true), 'required' => $required[$t->key] ?? null]])
            ->all();

        $sets = fn (string $kind, string $field) => $pieces->filter(fn (FacetVersion $v) => $v->facet->kind === $kind)
            ->map(fn (FacetVersion $v) => ['key' => $v->facet->handle, 'name' => $v->facet->name, 'from' => $from($v, $field)])->values()->all();

        $spec = DefaultsPreview::merge($resolved['envelope']['layers']);

        return [
            'language' => $language,
            'structure' => $resolved['structure']->facet->handle,
            'workflow' => $resolved['workflow'] ? ['key' => $resolved['workflow'], 'name' => $workflow?->facet->name] : null,
            'tools' => (object) $tools,
            'package_sets' => $sets('package_set', 'package_sets'),
            'skill_sets' => $sets('skill_set', 'skill_sets'),
            // Single skills only, by handle, as the form lists them; sets bring their own
            'skills' => collect(array_keys([...($resolved['layers']['blueprint']['skills'] ?? []), ...($resolved['layers']['choices']['skills'] ?? [])]))
                ->reject(fn ($key) => $key === 'framework-sensitive-data')
                ->map(fn ($key) => Skill::visibleTo($user)->where('key', $key)->with('owner')->orderByRaw('user_id is not null')->first()?->handle)
                ->filter()->values()->all(),
            'settings' => [
                'sensitive' => (bool) ($spec['security']['sensitive'] ?? false),
                'git' => (bool) ($spec['git']['use_git'] ?? true),
                'ai' => (bool) ($spec['ai']['enabled'] ?? true),
            ],
            'notices' => $resolved['notices'],
        ];
    }
}
