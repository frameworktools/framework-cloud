<?php

namespace App\Support;

use App\Models\FacetVersion;
use App\Models\Project;
use Illuminate\Support\Arr;

/**
 * The layers envelope for one project (schema: resources/schema/layers.v1.json):
 * its name, author, and unmerged layers bottom to top. The framework R
 * package adds its defaults beneath them and merges. This is what the cloud
 * commits as .framework/layers.json and what pull v2 returns.
 */
class ProjectLayers
{
    public static function for(Project $project): array
    {
        $project->loadMissing('user', 'blueprintVersion.blueprint.owner', 'blueprintVersion.base.facet.owner', 'blueprintVersion.facetVersions.facet.owner');

        $user = $project->user;
        $version = $project->blueprintVersion;
        $fragments = $version ? $version->fragments() : collect();

        $layers = $fragments->map(fn (FacetVersion $fragment) => [
            'kind' => $fragment->facet->kind,
            'key' => $fragment->facet->handle,
            'version' => $fragment->version,
            'layer' => $fragment->layer,
        ])->all();

        if ($version) {
            $layers[] = [
                'kind' => 'blueprint',
                'key' => $version->blueprint->handle,
                'version' => $version->version,
                'layer' => $version->layer ?? [],
            ];
        }

        // Per-project overrides are not editable in the cloud yet
        $layers[] = ['kind' => 'project', 'key' => $project->slug, 'layer' => []];

        // The Profile tab's byline, falling back to the account
        $author = Arr::get($user->globalSettings()->document, 'author', []) ?? [];
        $affiliations = collect($author['affiliations'] ?? [])
            ->filter(fn ($a) => is_array($a) && filled($a['name'] ?? null))
            ->map(fn (array $a) => array_filter(Arr::only($a, ['name', 'department', 'city', 'country']), 'filled'))
            ->values()->all();

        $envelope = [
            'spec_version' => 1,
            'name' => $project->name,
            'author' => [
                'name' => (string) (($author['name'] ?? '') ?: $user->name),
                'email' => (string) (($author['email'] ?? '') ?: $user->email),
                'affiliation' => (string) ($author['affiliation'] ?? ''),
            ] + array_filter([
                'orcid' => (string) ($author['orcid'] ?? ''),
                'url' => (string) ($author['url'] ?? ''),
                // R replaces the list as a whole, so it always goes in full
                'affiliations' => $affiliations,
            ]),
            'layers' => $layers,
        ];

        // Provenance is on by default, and off for sensitive projects
        if ($version && ! self::isSensitive($layers)) {
            $envelope['source'] = [
                'origin' => 'cloud',
                'blueprint' => ['key' => $version->blueprint->handle, 'version' => $version->version],
                'fragments' => array_map(fn (array $l) => Arr::only($l, ['kind', 'key', 'version']), array_slice($layers, 0, -1)),
            ];
        }

        return $envelope;
    }

    /** Whether the merged spec will be sensitive: the topmost layer that sets the flag decides. */
    public static function isSensitive(array $layers): bool
    {
        $sensitive = false;

        foreach ($layers as $entry) {
            if (is_bool($entry['layer']['security']['sensitive'] ?? null)) {
                $sensitive = $entry['layer']['security']['sensitive'];
            }
        }

        return $sensitive;
    }

    public static function toJson(array $envelope): string
    {
        return json_encode(Layer::toJsonValue($envelope), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
