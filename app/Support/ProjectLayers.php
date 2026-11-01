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

    /**
     * A project's layers. A project keeps the envelope it was resolved from
     * at creation, so later changes to anyone's defaults or to a blueprint
     * leave it alone. A project made before that has its envelope built from
     * what it recorded and the owner's defaults, once, and then keeps it.
     */
    public static function for(Project $project): array
    {
        if ($project->resolved) {
            return json_decode($project->resolved, true);
        }

        $project->loadMissing('user', 'blueprintVersion.blueprint.owner', 'blueprintVersion.base.facet.owner', 'blueprintVersion.facetVersions.facet.owner');

        $user = $project->user;
        $language = $project->language ?? 'r';
        $version = $project->blueprintVersion;
        $composition = $project->composition;
        $fragments = match (true) {
            (bool) $composition => self::compositionFragments($composition),
            (bool) $version => $version->fragments(),
            default => collect(),
        };

        $layers = $fragments->filter(fn (FacetVersion $fragment) => $fragment->facet->isStructure() || self::fits($fragment, $language))
            ->flatMap(fn (FacetVersion $fragment) => self::entries($fragment, $language))->all();

        // Recorded before workflows: the owner's workflow, else the blueprint's, else Framework's
        $defaults = ProjectDefaults::for($user);
        if (! $fragments->contains(fn (FacetVersion $f) => $f->facet->kind === 'workflow')) {
            $supported = array_values(array_filter($version?->workflows ?: ProjectDefaults::WORKFLOW_KEYS, fn (string $w) => ProjectDefaults::workflowFits($w, $language)));
            $workflow = collect([$defaults['workflows'][$language] ?? null, $version?->default_workflow[$language] ?? null, ProjectDefaults::WORKFLOWS[$language]])
                ->first(fn ($w) => in_array($w, $supported, true)) ?? $supported[0] ?? null;
            $piece = $workflow ? Facet::whereNull('user_id')->where('kind', 'workflow')->where('category', $workflow)->with('latestVersion.facet')->first()?->latestVersion : null;
            if ($piece) {
                $have = array_column($layers, 'key');
                foreach ($piece->facet->requires ?? [] as $key) {
                    $tool = Facet::whereNull('user_id')->where('kind', 'tool')->where('key', $key)->with('latestVersion.facet')->first()?->latestVersion;
                    if ($tool && ! in_array($tool->facet->handle, $have, true)) {
                        array_push($layers, ...self::entries($tool, $language));
                    }
                }
                array_push($layers, ...self::entries($piece, $language));
            }
        }

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

        if ($personal = ProjectDefaults::personalLayer($defaults, $language)) {
            $layers[] = ['kind' => 'settings', 'key' => 'your defaults', 'layer' => $personal];
        }
        $layers[] = self::projectLayer($project->slug, $language, $layers);

        $envelope = self::envelope($user, $project->name, $layers, $version && ! $composition ? ['key' => $version->blueprint->handle, 'version' => $version->version] : null);
        $project->forceFill(['resolved' => json_encode($envelope, JSON_UNESCAPED_SLASHES)])->saveQuietly();

        return $envelope;
    }

    /** The structure, then the pieces, in the order the project recorded them. */
    private static function compositionFragments(array $composition): Collection
    {
        $ids = [$composition['structure_version_id'], ...($composition['piece_version_ids'] ?? [])];
        $versions = FacetVersion::with('facet.owner')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $versions->get($id))->filter()->values();
    }

    /**
     * The topmost layer: the project's language, for Stata the R-only folders
     * removed, and, with the spec's tools key on, every tool for the
     * language named on or off, so no client has to guess. The person's
     * defaults sit beneath it in their own layer.
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

    /** Whether a piece applies to a project's language, such as uv only in Python. Stata has no tools. */
    public static function fits(FacetVersion $version, string $language): bool
    {
        return match (true) {
            $version->facet->kind === 'setting' => true,
            $version->facet->kind === 'tool' && $language === 'stata' => false,
            (bool) $version->facet->language => in_array($language, $version->facet->language, true),
            default => true,
        };
    }

    /**
     * A piece's layers in the envelope: its own, then the one it adds for the
     * project's language, such as IRkernel for Jupyter in an R project.
     *
     * @return list<array<string, mixed>>
     */
    public static function entries(FacetVersion $version, string $language): array
    {
        $facet = $version->facet;
        $layer = self::pieceLayer($facet, $version->layer);
        // R's released schema takes jupyter as a notebook format from the
        // framework commit that adds workflows; until then R picks the format
        // from the tools
        if ($facet->kind === 'workflow' && $language === 'r' && ! config('framework.spec_workflows') && ($layer['scaffold']['notebook_format'] ?? null) === 'jupyter') {
            unset($layer['scaffold']['notebook_format']);
            $layer = array_filter($layer);
        }
        $entries = [[
            'kind' => $facet->layerKind(),
            'key' => $facet->handle,
            'version' => $version->version,
            'layer' => $layer,
        ]];

        if ($extra = $version->language_layers[$language] ?? null) {
            $entries[] = ['kind' => 'facet', 'key' => "{$facet->handle}-{$language}", 'version' => $version->version, 'layer' => $extra];
        }

        return $entries;
    }

    /**
     * A piece's layer as clients receive it. With the spec's tools key on, a
     * tool layer says which tool it is, and a structure says its settings
     * layout and whether it has core files.
     */
    public static function pieceLayer(Facet $facet, array $layer): array
    {
        // Editor files and agents are the person's, never a piece's
        $layer = Trust::withoutPersonal($layer);

        if (! config('framework.spec_tools')) {
            return $layer;
        }

        if ($facet->kind === 'tool') {
            $layer['tools'][$facet->key] = true;
        }

        // The workflow names itself, and Scripts only sets the script notebook
        // format; a server that still serves clients older than the schema
        // with both keys leaves them out
        if ($facet->kind === 'workflow' && ! config('framework.spec_workflows')) {
            unset($layer['scaffold']['workflow']);
            if (($layer['scaffold']['notebook_format'] ?? null) === 'script') {
                unset($layer['scaffold']['notebook_format']);
            }
            $layer = array_filter($layer);
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
                // What the project was built from: the structure, pieces, and blueprint, not the person's or project's own layers
                'fragments' => array_values(array_map(fn (array $l) => Arr::only($l, ['kind', 'key', 'version']), array_filter($layers, fn (array $l) => in_array($l['kind'] ?? null, ['base', 'facet', 'blueprint'], true)))),
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
