<?php

namespace App\Models;

use App\Models\Concerns\HasVersions;
use App\Models\Concerns\Shareable;
use Database\Factories\BlueprintFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * A Blueprint is a recipe for a project: a base facet (the project type),
 * an ordered stack of facets on top, and an optional layer of its own
 * tweaks. Each version pins exact facet versions, so a project records
 * precisely what it was built from.
 *
 * System blueprints mirror the framework R package's built-in types, one
 * per base, and back the v1 API that framework 1.x reads.
 */
class Blueprint extends Model
{
    /** @use HasFactory<BlueprintFactory> */
    use HasFactory, HasVersions, Shareable;

    protected $guarded = [];

    protected $appends = ['handle'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** The system blueprints that stand for the R package's project types. */
    public function scopeSystemTypes(Builder $query): Builder
    {
        return $query->whereNull('user_id')->whereIn('key', Project::TYPES)->orderBy('position');
    }

    protected function versionModel(): string
    {
        return BlueprintVersion::class;
    }

    protected function versionContent(): array
    {
        return ['base_version_id', 'facet_version_ids', 'layer'];
    }

    protected function versionSnapshot(Model $version): array
    {
        return [
            'base_version_id' => $version->base_version_id,
            'facet_version_ids' => $version->facetVersions()->pluck('facet_versions.id')->all(),
            'layer' => $version->layer,
        ];
    }

    protected function storeVersion(array $attributes): Model
    {
        $facetVersionIds = Arr::pull($attributes, 'facet_version_ids', []);
        $version = $this->versions()->create($attributes);

        foreach (array_values($facetVersionIds) as $position => $id) {
            $version->facetVersions()->attach($id, ['position' => $position]);
        }

        return $version;
    }

    /**
     * Publish a composition. Returns the current version when nothing changed.
     *
     * @param  list<int>  $facetVersionIds  in merge order
     */
    public function publish(FacetVersion $base, array $facetVersionIds, array $layer = [], ?string $notes = null): BlueprintVersion
    {
        return $this->publishVersion([
            'base_version_id' => $base->id,
            'facet_version_ids' => array_map('intval', array_values($facetVersionIds)),
            'layer' => $layer,
            'notes' => $notes,
        ]);
    }

    /**
     * The shape framework 1.x reads from GET /api/v1/blueprints and the v1
     * project pull: the base layer's structure, its AGENTS.md master, and
     * skill text keyed by name. v1 reads only that shape, so skills come from
     * every piece (the structure, skill sets, and the blueprint's own layer),
     * in merge order, and the rest of each piece is left out.
     *
     * @return array<string, mixed>
     */
    public function toV1Array(): array
    {
        $version = $this->latestVersion;
        $layer = $version?->base?->layer ?? [];
        $entries = $version
            ? $version->fragments()->map(fn ($fragment) => $fragment->layer['skills'] ?? [])->push($version->layer['skills'] ?? [])->reduce(fn (array $all, $more) => array_merge($all, is_array($more) ? $more : []), [])
            : [];

        $skills = [];
        foreach ($entries as $name => $entry) {
            $content = match (true) {
                ! is_array($entry) => null,
                ! empty($entry['package']) => Skill::whereNull('user_id')->where('key', $name)
                    ->first()?->latestVersion?->content,
                default => $entry['content'] ?? null,
            };

            if ($content !== null) {
                $skills[$name] = $content;
            }
        }

        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'structure' => [
                'directories' => $layer['directories'] ?? [],
                'render_dirs' => $layer['render_dirs'] ?? [],
                'quarto' => $layer['quarto'] ?? null,
            ],
            'agents_md' => $layer['files']['AGENTS.md']['content'] ?? null,
            'skills' => $skills,
            'is_system' => $this->is_system,
        ];
    }
}
