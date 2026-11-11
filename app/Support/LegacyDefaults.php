<?php

namespace App\Support;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Account defaults as they were stored before blueprints held everything:
 * the synced settings document (editor, Git, renv, scaffold, Quarto, and
 * agents) and users.project_defaults (package lists, sets, tools, hooks).
 * New projects no longer read them. fw:convert-defaults reads them once to
 * turn each person's own defaults into a personal blueprint.
 */
class LegacyDefaults
{
    /**
     * @return array{publish_destination: string, blueprint: string, language: ?string, workflows: array<string, string>, rmarkdown: bool, package_sets: list<string>, skill_sets: list<string>, packages: array<string, list<array<string, mixed>>>, tools: array<string, bool>, ai_other: bool, settings: array{git: bool, ai: bool, hooks: list<string>, ide: string, scaffold: array<string, bool|string>, agents: list<string>, quarto: array<string, array<string, mixed>>}}
     */
    public static function for(User $user): array
    {
        $doc = $user->globalSettings()->document;
        $own = $user->project_defaults ?? [];

        return [
            'publish_destination' => (string) ($own['publish_destination'] ?? ''),
            'blueprint' => self::ownBlueprint($user, $own['blueprint_id'] ?? null) ?? 'framework/'.Arr::get($doc, 'defaults.project_type', ProjectDefaults::BLUEPRINT),
            'language' => in_array($own['language'] ?? null, Project::LANGUAGES, true) ? $own['language'] : null,
            'workflows' => ProjectDefaults::chosenWorkflows(Arr::get($doc, 'defaults.workflows')),
            'rmarkdown' => Arr::get($doc, 'defaults.notebook_format') === 'rmarkdown',
            'package_sets' => array_values($own['package_sets'] ?? []),
            'skill_sets' => array_values($own['skill_sets'] ?? []),
            'packages' => ProjectDefaults::packages($own['packages'] ?? ProjectDefaults::DEFAULT_PACKAGES),
            'tools' => ['renv' => (bool) Arr::get($doc, 'defaults.use_renv', false)] + array_map('boolval', $own['tools'] ?? []),
            'ai_other' => (bool) ($own['ai_other'] ?? false),
            'settings' => [
                'git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'ai' => (bool) Arr::get($doc, 'ai.enabled', true),
                'hooks' => array_keys(array_filter(Arr::only($own['hooks'] ?? [], ProjectDefaults::HOOKS))),
                'ide' => (string) Arr::get($doc, 'defaults.ide', ProjectDefaults::IDE),
                'scaffold' => ProjectDefaults::scaffold((array) ($doc['defaults'] ?? [])),
                'agents' => ProjectDefaults::agents(Arr::get($doc, 'ai.assistants')),
                'quarto' => ProjectDefaults::quarto((array) ($doc['quarto'] ?? [])),
            ],
        ];
    }

    /**
     * What an account that never changed anything had, with the same
     * default blueprint: the baseline a person's defaults are compared to.
     *
     * @param  array<string, mixed>  $defaults  from for()
     * @return array<string, mixed>
     */
    public static function untouched(array $defaults): array
    {
        return [
            'publish_destination' => '',
            'blueprint' => $defaults['blueprint'],
            'language' => $defaults['language'],
            'workflows' => [],
            'rmarkdown' => false,
            'package_sets' => [],
            'skill_sets' => [],
            'packages' => ProjectDefaults::packages(ProjectDefaults::DEFAULT_PACKAGES),
            'tools' => ['renv' => false],
            'ai_other' => false,
            'settings' => [
                'git' => true,
                'ai' => true,
                'hooks' => [],
                'ide' => ProjectDefaults::IDE,
                'scaffold' => ProjectDefaults::SCAFFOLD,
                'agents' => [],
                'quarto' => ProjectDefaults::QUARTO,
            ],
        ];
    }

    /** The handle of the person's own default blueprint, while it still exists. */
    private static function ownBlueprint(User $user, mixed $id): ?string
    {
        if (! is_int($id)) {
            return null;
        }

        return Blueprint::where('user_id', $user->id)->find($id)?->handle;
    }
}
