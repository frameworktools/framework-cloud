<?php

namespace App\Models;

use App\Support\ProjectDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class BlueprintVersion extends Model
{
    protected $guarded = [];

    /**
     * The editor files a version's projects get, in the ide column, as
     * scaffold.ide names them: RStudio's .Rproj, VS Code and Positron's
     * .code-workspace, both, or none. Null leaves it to the person.
     */
    public const IDES = ['rstudio', 'vscode', 'both', 'none'];

    protected function casts(): array
    {
        return [
            'layer' => 'array',
            'languages' => 'array',
            'workflows' => 'array',
            'default_workflow' => 'array',
            'requires_tools' => 'array',
            'requires' => 'array',
            'agent_defaults' => 'array',
        ];
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(FacetVersion::class, 'base_version_id');
    }

    public function facetVersions(): BelongsToMany
    {
        return $this->belongsToMany(FacetVersion::class, 'blueprint_version_facets')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * The blueprint's fragments in merge order: the base, then each facet.
     *
     * @return Collection<int, FacetVersion>
     */
    /**
     * What the version declares it supports and needs, for resolution to
     * validate against. A field left empty means no restriction: every
     * language, or every workflow the language has.
     *
     * @return array{languages: ?list<string>, workflows: ?list<string>, default_workflow: array<string, string>, requires_tools: list<string>, requires: array<string, mixed>}
     */
    public function compatibility(): array
    {
        $languages = $this->languages ?: null;
        $workflows = $this->workflows ?: null;
        $defaults = $this->default_workflow ?? [];
        // Older personal blueprints allowed several languages and workflows.
        // Read them as one choice without rewriting their pinned versions.
        if ($this->blueprint->user_id !== null) {
            $language = count($languages ?? []) === 1 ? $languages[0] : $this->blueprint->language;
            $languages = [$language];
            $available = array_values(array_filter($workflows ?? ProjectDefaults::WORKFLOW_KEYS,
                fn ($workflow) => ProjectDefaults::workflowFits($workflow, $language)));
            $workflow = collect([$defaults[$language] ?? null, ProjectDefaults::WORKFLOWS[$language]])
                ->first(fn ($workflow) => in_array($workflow, $available, true)) ?? ($available[0] ?? null);
            $workflows = $workflow ? [$workflow] : [];
            $defaults = $workflow ? [$language => $workflow] : [];
        }

        return [
            'languages' => $languages,
            'workflows' => $workflows,
            'default_workflow' => $defaults,
            'requires_tools' => $this->blueprint->user_id !== null && $languages === ['stata'] ? [] : ($this->requires_tools ?? []),
            'requires' => $this->requires ?? [],
        ];
    }

    public function fragments(): Collection
    {
        return collect([$this->base])->concat($this->facetVersions);
    }
}
