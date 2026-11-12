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
        $from = fn (FacetVersion $v, string $field) => $customized ? 'chosen' : 'blueprint';

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

        // The name names files such as the .Rproj, as creation does
        $spec = ['name' => $resolved['envelope']['name']] + DefaultsPreview::merge($resolved['envelope']['layers']);

        $tree = ProjectTree::for($spec, $language, $on, (bool) ($spec['security']['sensitive'] ?? false));
        $missingRenderDirectory = null;
        $renderDirectory = $spec['quarto']['render_dir'] ?? null;
        if ($language !== 'stata' && in_array('quarto', $on, true) && is_string($renderDirectory)) {
            $target = rtrim(preg_replace('#^(\./)+#', '', $renderDirectory), '/');
            if ($target !== '' && $target !== '.') {
                // A created child also creates its parent. Ignore folders deferred until use.
                $created = collect($tree)->filter(fn ($entry) => ! $entry['later'])
                    ->filter(fn ($entry) => $entry['type'] === 'dir' || str_contains($entry['path'], '/'))
                    ->map(fn ($entry) => $entry['type'] === 'dir' ? $entry['path'] : dirname($entry['path']))
                    ->merge(collect($spec['extra_directories'] ?? [])->pluck('path')->filter(fn ($path) => is_string($path)));
                if (! $created->contains(fn ($path) => $path === $target || str_starts_with($path, $target.'/'))) {
                    $missingRenderDirectory = $target;
                }
            }
        }

        $named = array_keys(array_filter((array) ($spec['ai']['assistants'] ?? [])));
        $aiOn = (bool) ($spec['ai']['enabled'] ?? true);

        $skillEntries = [...($resolved['layers']['blueprint']['skills'] ?? []), ...($resolved['layers']['choices']['skills'] ?? [])];
        $packageEntries = array_replace($resolved['layers']['blueprint']['packages']['default_packages'] ?? [], $resolved['layers']['choices']['packages']['default_packages'] ?? []);

        return [
            'language' => $language,
            // The files and folders creation writes, and where documents render
            'tree' => $tree,
            'missing_render_directory' => $missingRenderDirectory,
            'render_dirs' => collect((array) ($spec['render_dirs'] ?? []))
                ->map(fn ($target, $key) => ['from' => $spec['directories'][$key] ?? $key, 'to' => $target])->values()->all(),
            // Agents as the shared selector shows them; other agents is the blueprint's answer
            'agents' => [
                'enabled' => $aiOn,
                'named' => array_values(array_intersect(ProjectDefaults::AGENTS, $named)),
                'other' => $aiOn && (in_array('other', ProjectDefaults::agentDefaults($version), true) || ! $named),
            ],
            'structure' => $resolved['structure']->facet->handle,
            'workflow' => $resolved['workflow'] ? ['key' => $resolved['workflow'], 'name' => $workflow?->facet->name] : null,
            'tools' => (object) $tools,
            'package_sets' => $sets('package_set', 'package_sets'),
            'package_overrides' => Catalog::packageOverrides($packageEntries, $pieces, $language),
            'package_auto_attach' => $language === 'r' ? collect($packageEntries)->filter(fn ($entry) => is_array($entry) && array_key_exists('auto_attach', $entry))->map(fn ($entry) => (bool) $entry['auto_attach'])->all() : [],
            'skill_sets' => $sets('skill_set', 'skill_sets'),
            // Single skills only, by handle, as the form lists them; sets bring their own
            'skills' => collect($skillEntries)->filter(fn ($entry) => $entry !== null)->keys()
                ->reject(fn ($key) => $key === 'framework-sensitive-data')
                ->map(fn ($key) => Skill::visibleTo($user)->where('key', $key)->with('owner')->orderByRaw('user_id is not null')->first()?->handle)
                ->filter()->values()->all(),
            'excluded_skills' => collect($skillEntries)->filter(fn ($entry) => $entry === null)->keys()
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
