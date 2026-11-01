<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * What a new project would get with a blueprint as the default, resolved
 * the way project creation resolves it: the blueprint, then the person's
 * defaults (saved or still in the form), for one language. Read-only.
 */
class DefaultsPreview
{
    /** Entries replaced whole when a later layer sets them, as in the layer merge R, Python, and fw share. */
    private const ATOMIC = ['files', 'skills', 'extra_directories', 'packages/default_packages'];

    /**
     * @param  array<string, mixed>  $values  the Project defaults form's values
     * @return array<string, mixed>
     */
    public static function for(User $user, Blueprint $blueprint, string $language, array $values): array
    {
        $type = $blueprint->latestVersion->base->layer['type'] ?? 'bare';
        if ($problem = Project::typeProblem($language, $type)) {
            return ['available' => false, 'problem' => $problem];
        }

        $defaults = ProjectDefaults::fromValues(['project_type' => $blueprint->key] + $values);

        try {
            $resolved = (new Resolver($user, true, $defaults))->resolve(['name' => 'project', 'language' => $language]);
        } catch (ValidationException $e) {
            return ['available' => false, 'problem' => collect($e->errors())->flatten()->first()];
        }

        $layers = $resolved['envelope']['layers'];
        // R starts every project with Framework's own package list, beneath the layers
        $spec = self::merge($resolved['language'] === 'r'
            ? [['layer' => ['packages' => ['default_packages' => ProjectDefaults::packageLayer(ProjectDefaults::packages(ProjectDefaults::DEFAULT_PACKAGES)['r'])]]], ...$layers]
            : $layers);
        $version = $blueprint->latestVersion;
        $own = $version->facetVersions->pluck('id')->all();
        $from = fn (FacetVersion $v) => in_array($v->id, $own, true) ? 'blueprint' : 'defaults';
        $ofKind = fn (string $kind) => collect($resolved['pieces'])->filter(fn (FacetVersion $v) => $v->facet->kind === $kind)->values();
        $packages = fn (?array $layer) => array_keys($layer['packages']['default_packages'] ?? []);

        $skillSets = $ofKind('skill_set');
        $inSets = $skillSets->flatMap(fn (FacetVersion $v) => array_keys($v->layer['skills'] ?? []))->all();
        $aiOn = (bool) ($spec['ai']['enabled'] ?? true);

        // Settings the blueprint (or its structure) sets itself; the rest come from the defaults
        $blueprintLayer = $version->layer ?? [];
        $structureLayer = $version->base->layer ?? [];
        $sensitive = ProjectLayers::isSensitive($layers);
        $setsSensitive = isset($blueprintLayer['security']['sensitive']) || isset($structureLayer['security']['sensitive']);

        return [
            'available' => true,
            'language' => $resolved['language'],
            'tree' => ProjectTree::for($spec, $resolved['language'], $ofKind('tool')->map(fn (FacetVersion $v) => $v->facet->key)->all(), $sensitive),
            // Every package the project starts with, and where each comes from
            'packages' => collect($spec['packages']['default_packages'] ?? [])->map(fn ($entry, $name) => [
                'name' => (string) $name,
                'source' => $entry['source'] ?? null,
                'auto_attach' => (bool) ($entry['auto_attach'] ?? false),
                'from' => collect($resolved['pieces'])->first(fn (FacetVersion $v) => isset($v->layer['packages']['default_packages'][$name]))?->facet->name,
            ])->values()->all(),
            'package_sets' => $ofKind('package_set')->map(fn (FacetVersion $v) => [
                'name' => $v->facet->name, 'id' => $v->facet->id, 'packages' => $packages($v->layer), 'from' => $from($v),
            ])->all(),
            // Packages that come with other pieces, such as ggplot2 with its skill set
            'other_packages' => collect($resolved['pieces'])->reject(fn (FacetVersion $v) => $v->facet->kind === 'package_set')
                ->flatMap(fn (FacetVersion $v) => array_map(fn ($p) => ['package' => $p, 'from' => $v->facet->name], $packages($v->layer)))->values()->all(),
            'skill_sets' => $skillSets->map(fn (FacetVersion $v) => [
                'name' => $v->facet->name, 'id' => $v->facet->id, 'from' => $from($v),
                'skills' => array_map(self::skillName(...), array_keys($v->layer['skills'] ?? [])),
            ])->all(),
            'skills' => $aiOn ? array_map(self::skillName(...), array_values(array_diff(array_keys($spec['skills'] ?? []), $inSets))) : [],
            'agents' => $aiOn,
            'tools' => $ofKind('tool')->map(fn (FacetVersion $v) => ['name' => $v->facet->name, 'from' => $from($v)])->all(),
            // A tool the blueprint has that the defaults turn off
            'tools_off' => $version->facetVersions->filter(fn (FacetVersion $v) => $v->facet->kind === 'tool' && ProjectLayers::fits($v, $resolved['language']))
                ->reject(fn (FacetVersion $v) => collect($resolved['pieces'])->contains('id', $v->id))
                ->map(fn (FacetVersion $v) => $v->facet->name)->values()->all(),
            'settings' => [
                'git' => ['on' => (bool) ($spec['git']['use_git'] ?? true), 'from' => isset($blueprintLayer['git']['use_git']) ? 'blueprint' : 'defaults'],
                'sensitive' => ['on' => $sensitive, 'from' => $setsSensitive ? 'blueprint' : 'defaults'],
                'agents' => ['on' => $aiOn, 'from' => isset($blueprintLayer['ai']) ? 'blueprint' : 'defaults'],
                'hooks' => [
                    'on' => array_keys(array_filter((array) ($spec['git']['hooks'] ?? []))),
                    'from' => isset($blueprintLayer['git']['hooks']) ? 'blueprint' : 'defaults',
                ],
            ],
        ];
    }

    /**
     * The layers merged into one spec: maps merge by key, a null removes,
     * lists and the atomic entries are replaced whole.
     *
     * @param  list<array<string, mixed>>  $layers
     * @return array<string, mixed>
     */
    public static function merge(array $layers): array
    {
        $spec = [];
        foreach ($layers as $entry) {
            $spec = self::mergeInto($spec, (array) ($entry['layer'] ?? []), '');
        }

        return $spec;
    }

    private static function mergeInto(array $base, array $over, string $path): array
    {
        foreach ($over as $key => $value) {
            $here = ltrim("{$path}/{$key}", '/');
            if ($value === null) {
                unset($base[$key]);
            } elseif (is_array($value) && ! array_is_list($value) && ! in_array($path, self::ATOMIC, true) && is_array($base[$key] ?? null)) {
                $base[$key] = self::mergeInto($base[$key], $value, $here);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function skillName(string $key): string
    {
        return Skill::whereNull('user_id')->where('key', $key)->value('name') ?? $key;
    }
}
