<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\Project;
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
        $skills = Skill::visibleTo($user)->with('owner', 'latestVersion', 'tags')->orderByRaw('user_id is not null')->orderBy('position')->orderBy('name')->get();
        $themes = $skills->mapWithKeys(fn (Skill $skill) => [$skill->key => array_values(array_intersect($skill->tags->pluck('key')->all(), array_keys(SkillTags::CATEGORIES)))]);

        $piece = fn (Facet $f) => [
            'id' => $f->id,
            'key' => $f->handle,
            'name' => $f->name,
            'description' => $f->isSystem() && $f->kind === 'package_set' && $f->key === 'tidyverse-analysis' ? 'The tidyverse packages most analyses use.' : $f->description,
            'language' => $f->language,
            'requires' => $f->requires ?? [],
            'system' => $f->isSystem(),
            'category' => $f->category ?? ($f->kind === 'package_set' ? PackageCategories::for($f->key) : null),
            'editable' => $f->user_id === $user->id,
            'type' => $f->kind === 'structure' ? ($f->latestVersion?->layer['type'] ?? 'bare') : null,
            'sensitive' => $f->kind === 'structure' && isset($f->latestVersion?->layer['directories']['inputs_private_raw']),
            'summary' => LayerSummary::of($f->latestVersion?->catalogLayer() ?? []),
        ];

        return [
            'structures' => $pieces->where('kind', 'structure')->values()->map($piece),
            'tools' => $pieces->where('kind', 'tool')->reject(fn (Facet $f) => $f->key === 'targets')->values()->map(fn (Facet $f) => ['key' => $f->key] + $piece($f)),
            // Package names too, so the builder can count what stacked sets install
            'package_sets' => $pieces->where('kind', 'package_set')->values()
                ->map(fn (Facet $f) => $piece($f) + [
                    'packages' => $f->latestVersion?->manual_packages ?: array_keys($f->latestVersion?->layer['packages']['default_packages'] ?? []),
                    'package_members' => collect(Project::LANGUAGES)->mapWithKeys(fn ($language) => [$language => $language === 'stata' ? ($f->latestVersion?->manual_packages ?? []) : array_keys(PackageSetLayer::installOnly($f->latestVersion?->catalogLayer($language) ?? [])['packages']['default_packages'] ?? [])]),
                    'package_entries' => collect(Project::LANGUAGES)->mapWithKeys(fn ($language) => [$language => $language === 'stata' ? array_fill_keys($f->latestVersion?->manual_packages ?? [], ['source' => 'manual']) : PackageSetLayer::installOnly($f->latestVersion?->catalogLayer($language) ?? [])['packages']['default_packages'] ?? []]),
                    'tags' => PackageCategories::setTags($f->key, $f->category),
                ]),
            'skill_sets' => $pieces->where('kind', 'skill_set')->values()->map(fn (Facet $f) => $piece($f) + [
                'members' => collect(Project::LANGUAGES)->mapWithKeys(fn ($language) => [$language => array_keys($f->latestVersion?->catalogLayer($language)['skills'] ?? [])]),
                'tags' => collect(Project::LANGUAGES)->flatMap(fn ($language) => array_keys($f->latestVersion?->catalogLayer($language)['skills'] ?? []))
                    ->unique()->flatMap(fn ($key) => $themes[$key] ?? [])->unique()->values()->all(),
            ]),
            'categories' => SkillTags::categories(),
            'package_categories' => PackageCategories::options(),
            'package_set_tags' => PackageCategories::setTagOptions(),
            'packages' => self::packagesFrom($pieces),
            'skills' => $skills
                ->map(fn (Skill $s) => ['key' => $s->handle, 'skill' => $s->key, 'name' => $s->name, 'description' => $s->description, 'system' => $s->isSystem()] + Library::skill($s, $user)),
            // Framework's notebook, script, and slide templates, for the Templates tab to start from
            'templates' => Templates::defaults(),
        ];
    }

    /** Individual packages, reconciled across the visible sets for each language. */
    private static function packagesFrom($pieces): array
    {
        $packages = [];
        foreach ($pieces->where('kind', 'package_set') as $piece) {
            foreach ($piece->language ?: Project::LANGUAGES as $language) {
                foreach (PackageSetLayer::installOnly($piece->latestVersion?->catalogLayer($language) ?? [])['packages']['default_packages'] ?? [] as $name => $definition) {
                    if (! is_array($definition)) {
                        continue;
                    }
                    $key = $language.':'.$name;
                    $previous = $packages[$key] ?? [];
                    $packages[$key] = [
                        'key' => $name, 'name' => $name, 'language' => $language, 'definition' => $definition,
                        'categories' => array_values(array_unique([...($previous['categories'] ?? []), $piece->category ?? PackageCategories::for($piece->key)])),
                        'sets' => array_values(array_unique([...($previous['sets'] ?? []), $piece->handle])),
                    ];
                }
            }
        }

        // Default packages must be selectable even when no curated set
        // happens to contain them (for example, tibble).
        foreach (ProjectDefaults::DEFAULT_PACKAGES as $language => $rows) {
            foreach (ProjectDefaults::packageLayer(ProjectDefaults::packages([$language => $rows])[$language]) as $name => $definition) {
                $packages[$language.':'.$name] ??= [
                    'key' => $name, 'name' => $name, 'language' => $language,
                    'definition' => $definition, 'categories' => ['analysis'], 'sets' => [],
                ];
            }
        }

        return array_values($packages);
    }

    public static function packageOptions(User $user, string $language): array
    {
        $pieces = Facet::visibleTo($user)->where('kind', 'package_set')->with('owner', 'latestVersion')
            ->orderByRaw('user_id is not null')->orderBy('position')->get();

        return collect(self::packagesFrom($pieces))->where('language', $language)->keyBy('key')->all();
    }

    /** Auto-attach overrides do not turn inherited packages into direct selections. */
    public static function packageOverrides(array $entries, $pieces, string $language): array
    {
        $inherited = [];
        foreach ($pieces->filter(fn ($piece) => $piece->facet->kind === 'package_set') as $piece) {
            $inherited = array_replace($inherited, $piece->catalogLayer($language)['packages']['default_packages'] ?? []);
        }

        return collect($entries)->reject(fn ($entry, $name) => $language === 'r' && is_array($entry) && isset($inherited[$name])
            && Arr::except($entry, ['auto_attach']) === Arr::except($inherited[$name], ['auto_attach']))
            ->map(fn ($entry) => $entry !== null)->all();
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
        $compatibility = $version->compatibility();
        $language = count($compatibility['languages'] ?? []) === 1 ? $compatibility['languages'][0] : ($version->blueprint->language ?? 'r');

        // The preview and R clients inherit these beneath the blueprint's
        // own package choices. Show them in the editor so they can be refined.
        $packageEntries = array_replace(
            $language === 'r' ? ProjectDefaults::packageLayer(ProjectDefaults::packages(ProjectDefaults::DEFAULT_PACKAGES)['r']) : [],
            $layer['packages']['default_packages'] ?? [],
        );
        // Packages no visible set offers are the blueprint's own, each with its source
        $available = self::packageOptions($user, $language);
        $definitions = array_filter($packageEntries, fn ($entry, $name) => is_array($entry) && ! isset($available[$name]), ARRAY_FILTER_USE_BOTH);
        $packageEntries = array_diff_key($packageEntries, $definitions);

        // Single skills by handle: Framework's by key, a user's own by their handle
        $skills = collect($layer['skills'] ?? [])->filter(fn ($entry) => $entry !== null)->keys()
            ->map(fn ($key) => Skill::visibleTo($user)->where('key', $key)->with('owner')->orderByRaw('user_id is not null')->first()?->handle)
            ->filter()->values()->all();

        $excluded = collect($layer['skills'] ?? [])->filter(fn ($entry) => $entry === null)->keys()
            ->map(fn ($key) => Skill::visibleTo($user)->where('key', $key)->with('owner')->orderByRaw('user_id is not null')->first()?->handle)
            ->filter()->values()->all();

        return [
            'structure' => $version->base->facet->handle,
            'language' => $language,
            'notebook_format' => $layer['scaffold']['notebook_format'] ?? null,
            // A blueprint that doesn't name agents writes AGENTS.md for any agent
            'agents' => ProjectDefaults::agentDefaults($version) ?: (($layer['ai']['enabled'] ?? true) ? ['other'] : ['none']),
            'tools' => $pieces->filter(fn ($v) => $v->facet->kind === 'tool')->mapWithKeys(fn ($v) => [$v->facet->key => true])->all(),
            'package_sets' => $handles('package_set'),
            'package_overrides' => self::packageOverrides($packageEntries, $pieces, $language),
            'package_auto_attach' => $language === 'r' ? collect($packageEntries)->filter(fn ($entry) => is_array($entry) && array_key_exists('auto_attach', $entry))->map(fn ($entry) => (bool) $entry['auto_attach'])->all() : [],
            'package_definitions' => $definitions,
            'skill_sets' => $handles('skill_set'),
            'skills' => $skills,
            'excluded_skills' => $excluded,
            'settings' => [
                'sensitive' => (bool) ($layer['security']['sensitive'] ?? $structureLayer['security']['sensitive'] ?? false),
                'ai' => (bool) ($layer['ai']['enabled'] ?? true),
                'git' => (bool) ($layer['git']['use_git'] ?? true),
                'hooks' => array_keys(array_filter($layer['git']['hooks'] ?? [])),
            ],
            'extra_yaml' => Layer::toYaml(Arr::except($layer, [...self::SETTING_KEYS, 'scaffold.notebook_format', 'packages.default_packages'])),
            // What it supports; none listed means everything
            'languages' => $compatibility['languages'] ?? Project::LANGUAGES,
            'workflows' => $compatibility['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS,
            'default_workflow' => (object) $compatibility['default_workflow'],
        ];
    }
}
