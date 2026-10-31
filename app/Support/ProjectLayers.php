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
    /** Folders that only R projects use */
    private const R_FOLDERS = ['notebooks', 'scripts', 'functions', 'outputs_notebooks', 'outputs_models', 'outputs_private_models', 'outputs_public_models'];

    public static function for(Project $project): array
    {
        $project->loadMissing('user', 'blueprintVersion.blueprint.owner', 'blueprintVersion.base.facet.owner', 'blueprintVersion.facetVersions.facet.owner');

        $user = $project->user;
        $version = $project->blueprintVersion;
        $fragments = $version ? $version->fragments() : collect();

        $layers = $fragments->map(fn (FacetVersion $fragment) => [
            'kind' => $fragment->facet->layerKind(),
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

        // Per-project overrides are not editable in the cloud yet. The language
        // always goes in, so every tool can tell which one builds the project.
        $language = $project->language ?? 'r';
        $layers[] = ['kind' => 'project', 'key' => $project->slug, 'layer' => ['language' => $language] + ($language === 'stata' ? self::withoutRFolders($layers) : [])];

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

    /**
     * Removes the R-only folders the layers beneath set, so a Stata project
     * doesn't get empty notebooks or functions folders. fw adds its own.
     */
    private static function withoutRFolders(array $layers): array
    {
        $removed = [];

        foreach ($layers as $entry) {
            foreach (['directories', 'render_dirs'] as $section) {
                foreach (array_keys(is_array($entry['layer'][$section] ?? null) ? $entry['layer'][$section] : []) as $key) {
                    if (in_array($key, self::R_FOLDERS, true)) {
                        $removed[$section][$key] = null;
                    }
                }
            }
        }

        return $removed;
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
