<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Everything a person can choose from when building a blueprint or a new
 * project, and a blueprint version expressed as those same choices, so the
 * builder and the new-project page can start from one.
 */
class Catalog
{
    /** Settings the choices editor shows as switches; the rest of a blueprint layer is extra YAML. */
    private const SETTING_KEYS = ['security', 'ai', 'git', 'skills'];

    /** @return array<string, list<array<string, mixed>>> */
    public static function for(User $user): array
    {
        $pieces = Facet::visibleTo($user)->whereIn('kind', ['structure', 'tool', 'package_set', 'skill_set'])
            ->with('owner', 'latestVersion')
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get();

        $piece = fn (Facet $f) => [
            'id' => $f->id,
            'key' => $f->handle,
            'name' => $f->name,
            'description' => $f->description,
            'language' => $f->language,
            'requires' => $f->requires ?? [],
            'system' => $f->isSystem(),
            'type' => $f->kind === 'structure' ? ($f->latestVersion?->layer['type'] ?? 'bare') : null,
            'sensitive' => $f->kind === 'structure' && isset($f->latestVersion?->layer['directories']['inputs_private_raw']),
            'summary' => LayerSummary::of($f->latestVersion?->layer ?? []),
        ];

        return [
            'structures' => $pieces->where('kind', 'structure')->values()->map($piece),
            'tools' => $pieces->where('kind', 'tool')->values()->map(fn (Facet $f) => ['key' => $f->key] + $piece($f)),
            'package_sets' => $pieces->where('kind', 'package_set')->values()->map($piece),
            'skill_sets' => $pieces->where('kind', 'skill_set')->values()->map($piece),
            'skills' => Skill::visibleTo($user)->with('owner')->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get()
                ->map(fn (Skill $s) => ['key' => $s->handle, 'skill' => $s->key, 'name' => $s->name, 'description' => $s->description, 'system' => $s->isSystem()]),
        ];
    }

    /**
     * Blueprints to start from, each with its choices.
     *
     * @return list<array<string, mixed>>
     */
    public static function blueprints(User $user): array
    {
        return Blueprint::listedFor($user)->with(Library::BLUEPRINT_WITH)
            ->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')
            ->get()->filter(fn (Blueprint $b) => $b->latestVersion)
            ->map(fn (Blueprint $b) => Library::blueprint($b, $user) + ['choices' => self::choices($b->latestVersion, $user)])
            ->values()->all();
    }

    /**
     * A blueprint version as choices: its structure, tools, sets, single
     * skills, settings, and any other settings as YAML.
     *
     * @return array<string, mixed>
     */
    public static function choices(BlueprintVersion $version, User $user): array
    {
        $version->loadMissing('base.facet.owner', 'facetVersions.facet.owner', 'blueprint');
        $layer = $version->layer ?? [];
        $pieces = $version->facetVersions;
        $handles = fn (string $kind) => $pieces->filter(fn ($v) => $v->facet->kind === $kind)->map(fn ($v) => $v->facet->handle)->values()->all();
        $structureLayer = $version->base->layer ?? [];

        // Single skills by handle: Framework's by key, a user's own by their handle
        $skills = collect($layer['skills'] ?? [])->keys()
            ->reject(fn ($key) => $key === 'framework-sensitive-data')
            ->map(fn ($key) => Skill::visibleTo($user)->where('key', $key)->with('owner')->orderByRaw('user_id is not null')->first()?->handle)
            ->filter()->values()->all();

        return [
            'structure' => $version->base->facet->handle,
            'language' => $version->blueprint->language ?? 'r',
            'tools' => $pieces->filter(fn ($v) => $v->facet->kind === 'tool')->mapWithKeys(fn ($v) => [$v->facet->key => true])->all(),
            'package_sets' => $handles('package_set'),
            'skill_sets' => $handles('skill_set'),
            'skills' => $skills,
            'settings' => [
                'sensitive' => (bool) ($layer['security']['sensitive'] ?? $structureLayer['security']['sensitive'] ?? false),
                'ai' => (bool) ($layer['ai']['enabled'] ?? true),
                'git' => (bool) ($layer['git']['use_git'] ?? true),
            ],
            'extra_yaml' => Layer::toYaml(Arr::except($layer, self::SETTING_KEYS)),
        ];
    }
}
