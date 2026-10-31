<?php

namespace App\Support;

use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
        $composition = $project->composition;
        $fragments = match (true) {
            (bool) $composition => self::compositionFragments($composition),
            (bool) $version => $version->fragments(),
            default => collect(),
        };

        $layers = $fragments->map(fn (FacetVersion $fragment) => [
            'kind' => $fragment->facet->layerKind(),
            'key' => $fragment->facet->handle,
            'version' => $fragment->version,
            'layer' => self::pieceLayer($fragment->facet, $fragment->layer),
        ])->all();

        if ($composition) {
            $layers[] = ['kind' => 'blueprint', 'key' => $version?->blueprint->handle ?? 'choices', 'layer' => $composition['layer'] ?? []];
        } elseif ($version) {
            $layers[] = [
                'kind' => 'blueprint',
                'key' => $version->blueprint->handle,
                'version' => $version->version,
                'layer' => $version->layer ?? [],
            ];
        }

        // Per-project overrides are not editable in the cloud yet. The language
        // always goes in, so every tool can tell which one builds the project.
        $layers[] = self::projectLayer($project->slug, $project->language ?? 'r', $layers);

        return self::envelope($user, $project->name, $layers, $version && ! $composition ? ['key' => $version->blueprint->handle, 'version' => $version->version] : null);
    }

    /** The structure, then the pieces, in the order the project recorded them. */
    private static function compositionFragments(array $composition): Collection
    {
        $ids = [$composition['structure_version_id'], ...($composition['piece_version_ids'] ?? [])];
        $versions = FacetVersion::with('facet.owner')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $versions->get($id))->filter()->values();
    }

    /**
     * The topmost layer: the language, and for Stata the R-only folders
     * removed. With the spec's tools key on, it also names every tool for
     * the language as on or off, so no client has to guess.
     */
    public static function projectLayer(string $key, string $language, array $layers): array
    {
        $layer = ['language' => $language] + ($language === 'stata' ? self::withoutRFolders($layers) : []);

        if (config('framework.spec_tools') && $language !== 'stata') {
            $on = array_column(array_filter($layers, fn (array $l) => str_starts_with((string) ($l['key'] ?? ''), 'framework/') && isset($l['layer']['tools'])), 'layer');
            $layer['tools'] = Facet::whereNull('user_id')->where('kind', 'tool')->orderBy('position')->get()
                ->filter(fn (Facet $tool) => ! $tool->language || in_array($language, $tool->language, true))
                ->mapWithKeys(fn (Facet $tool) => [$tool->key => collect($on)->contains(fn (array $l) => ! empty($l['tools'][$tool->key]))])
                ->all();
        }

        return ['kind' => 'project', 'key' => $key, 'layer' => $layer];
    }

    /**
     * A piece's layer as clients receive it. With the spec's tools key on, a
     * tool layer says which tool it is, and a structure says its settings
     * layout and whether it has core files.
     */
    public static function pieceLayer(Facet $facet, array $layer): array
    {
        if (! config('framework.spec_tools')) {
            return $layer;
        }

        if ($facet->kind === 'tool') {
            $layer['tools'][$facet->key] = true;
        }

        if ($facet->kind === 'structure' && $facet->isSystem()) {
            $layer['settings_layout'] ??= in_array($facet->key, ['project', 'project_sensitive'], true) ? 'split' : 'flat';
            if ($facet->key === 'bare') {
                $layer['core_files'] ??= false;
            }
        }

        return $layer;
    }

    /**
     * The envelope around a list of layers: the author from the user's
     * profile, and provenance when the layers came from a blueprint.
     *
     * @param  array{key: string, version: int}|null  $blueprint
     */
    public static function envelope(User $user, string $name, array $layers, ?array $blueprint): array
    {
        // The Profile tab's byline, falling back to the account
        $author = Arr::get($user->globalSettings()->document, 'author', []) ?? [];
        $affiliations = collect($author['affiliations'] ?? [])
            ->filter(fn ($a) => is_array($a) && filled($a['name'] ?? null))
            ->map(fn (array $a) => array_filter(Arr::only($a, ['name', 'department', 'city', 'country']), 'filled'))
            ->values()->all();

        $envelope = [
            'spec_version' => 1,
            'name' => $name,
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
        if ($blueprint && ! self::isSensitive($layers)) {
            $envelope['source'] = [
                'origin' => 'cloud',
                'blueprint' => $blueprint,
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
