<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Shapes library rows (blueprints, facets, skills) for the Inertia pages,
 * and holds the small rules they share.
 */
class Library
{
    /** A key from the name, unique among the owner's rows of that kind. */
    public static function uniqueKey(Builder $query, string $name, ?int $ownerId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'untitled';
        $key = $base;

        for ($n = 2; ; $n++) {
            $taken = (clone $query)
                ->when($ownerId, fn ($q) => $q->where('user_id', $ownerId), fn ($q) => $q->whereNull('user_id'))
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('key', $key)
                ->exists();

            if (! $taken) {
                return $key;
            }

            $key = "{$base}-{$n}";
        }
    }

    public static function owner(Blueprint|Facet|Skill $row): ?array
    {
        return $row->owner ? ['name' => $row->owner->name, 'handle' => $row->owner->handle] : null;
    }

    public static function facetVersion(FacetVersion $version): array
    {
        $facet = $version->facet;

        return [
            'id' => $version->id,
            'version' => $version->version,
            'latest_version' => $facet->latestVersion->version,
            'latest_version_id' => $facet->latestVersion->id,
            'facet_id' => $facet->id,
            'handle' => $facet->handle,
            'name' => $facet->name,
            'kind' => $facet->kind,
            'category' => $facet->category,
            'summary' => LayerSummary::of($version->layer),
        ];
    }

    public static function facet(Facet $facet, ?User $user): array
    {
        $latest = $facet->latestVersion;

        return [
            'id' => $facet->id,
            'key' => $facet->key,
            'handle' => $facet->handle,
            'kind' => $facet->kind,
            'name' => $facet->name,
            'description' => $facet->description,
            'category' => $facet->category,
            'question' => $facet->question,
            'language' => $facet->language,
            'requires' => $facet->requires,
            'visibility' => $facet->visibility,
            'is_system' => $facet->isSystem(),
            'owner' => self::owner($facet),
            'editable' => $facet->isEditableBy($user),
            'version' => $latest?->version,
            'version_id' => $latest?->id,
            'summary' => $latest ? LayerSummary::of($latest->layer) : [],
            'source_url' => $facet->source_url,
        ];
    }

    public static function blueprint(Blueprint $blueprint, ?User $user): array
    {
        $latest = $blueprint->latestVersion;

        return [
            'id' => $blueprint->id,
            'key' => $blueprint->key,
            'handle' => $blueprint->handle,
            'name' => $blueprint->name,
            'description' => $blueprint->description,
            'language' => $blueprint->language ?? 'r',
            'visibility' => $blueprint->visibility,
            'is_system' => $blueprint->isSystem(),
            'owner' => self::owner($blueprint),
            'editable' => $blueprint->isEditableBy($user),
            'version' => $latest?->version,
            'base' => $latest?->base?->facet?->name,
            'type' => $latest?->base?->layer['type'] ?? 'bare',
            'facets' => $latest ? $latest->facetVersions->map(fn ($v) => $v->facet->name)->all() : [],
        ];
    }

    /** One version with its fragments in merge order, for the detail and builder pages. */
    public static function blueprintVersion(BlueprintVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'notes' => $version->notes,
            'created_at' => $version->created_at,
            'base' => self::facetVersion($version->base),
            'facets' => $version->facetVersions->map(fn ($v) => self::facetVersion($v))->all(),
            'layer_yaml' => Layer::toYaml($version->layer ?? []),
            'layer_summary' => LayerSummary::of($version->layer ?? []),
        ];
    }

    public static function skill(Skill $skill, ?User $user): array
    {
        return [
            'id' => $skill->id,
            'key' => $skill->key,
            'handle' => $skill->handle,
            'name' => $skill->name,
            'description' => $skill->description,
            'visibility' => $skill->visibility,
            'is_system' => $skill->isSystem(),
            'owner' => self::owner($skill),
            'editable' => $skill->isEditableBy($user),
            'version' => $skill->latestVersion?->version,
            'source_url' => $skill->source_url,
        ];
    }

    /** Eager loads that blueprint cards and versions need. */
    public const BLUEPRINT_WITH = [
        'owner',
        'latestVersion.base.facet.latestVersion',
        'latestVersion.facetVersions.facet.latestVersion',
        'latestVersion.facetVersions.facet.owner',
        'latestVersion.base.facet.owner',
    ];
}
