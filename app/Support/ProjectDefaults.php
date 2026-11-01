<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * What a user's new projects start with when they don't choose otherwise.
 * Some of it lives in the synced settings document that the R package also
 * reads (the default blueprint, git, renv, and AI); the rest is cloud-only in
 * users.project_defaults (package sets, skill sets, tools, sensitive, and
 * git hooks).
 */
class ProjectDefaults
{
    /** The blueprint for an account that never chose one: the standard project, as in R. */
    public const BLUEPRINT = 'project';

    /** The git hooks a user can turn on for new projects, as the R package names them. */
    public const HOOKS = ['data_security', 'check_sensitive_dirs'];

    /**
     * @return array{blueprint: string, package_sets: list<string>, skill_sets: list<string>, tools: array<string, bool>, settings: array{sensitive: bool, git: bool, ai: bool, hooks: list<string>}}
     */
    public static function for(User $user): array
    {
        $doc = $user->globalSettings()->document;
        $own = $user->project_defaults ?? [];

        return [
            'blueprint' => 'framework/'.Arr::get($doc, 'defaults.project_type', self::BLUEPRINT),
            'package_sets' => array_values($own['package_sets'] ?? []),
            'skill_sets' => array_values($own['skill_sets'] ?? []),
            'tools' => ['renv' => (bool) Arr::get($doc, 'defaults.use_renv', false)] + ($own['tools'] ?? []),
            'settings' => [
                'sensitive' => (bool) ($own['sensitive'] ?? false),
                'git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'ai' => (bool) Arr::get($doc, 'ai.enabled', true),
                'hooks' => array_keys(array_filter(Arr::only($own['hooks'] ?? [], self::HOOKS))),
            ],
        ];
    }

    /**
     * The same defaults from the Project defaults form's values, saved or
     * not, for previewing what a new project would get.
     *
     * @param  array<string, mixed>  $values  as DefaultsController hands them to the form
     * @return array{blueprint: string, package_sets: list<string>, skill_sets: list<string>, tools: array<string, bool>, settings: array{sensitive: bool, git: bool, ai: bool, hooks: list<string>}}
     */
    public static function fromValues(array $values): array
    {
        return [
            'blueprint' => 'framework/'.($values['project_type'] ?? self::BLUEPRINT),
            'package_sets' => array_values(array_filter((array) ($values['package_sets'] ?? []), 'is_string')),
            'skill_sets' => array_values(array_filter((array) ($values['skill_sets'] ?? []), 'is_string')),
            'tools' => ['renv' => (bool) ($values['use_renv'] ?? false)] + array_map('boolval', Arr::except((array) ($values['tools'] ?? []), ['renv'])),
            'settings' => [
                'sensitive' => (bool) ($values['sensitive'] ?? false),
                'git' => (bool) ($values['use_git'] ?? true),
                'ai' => (bool) ($values['ai_enabled'] ?? true),
                'hooks' => array_keys(array_filter(Arr::only((array) ($values['hooks'] ?? []), self::HOOKS))),
            ],
        ];
    }

    /**
     * The choices a resolve starts from. Without a blueprint or structure,
     * the default blueprint and the preferred pieces apply. Settings always
     * fall back to the defaults, with the blueprint's own settings in between.
     *
     * @param  array<string, mixed>  $choices
     * @param  array<string, mixed>|null  $defaults  from fromValues() to preview unsaved defaults
     * @return array<string, mixed>
     */
    public static function apply(User $user, array $choices, ?array $defaults = null): array
    {
        $defaults ??= self::for($user);

        if (blank($choices['blueprint'] ?? null) && blank($choices['structure'] ?? null)) {
            $choices['blueprint'] = $defaults['blueprint'];
            $choices['package_sets'] = array_values(array_unique([...$defaults['package_sets'], ...($choices['package_sets'] ?? [])]));
            $choices['skill_sets'] = array_values(array_unique([...$defaults['skill_sets'], ...($choices['skill_sets'] ?? [])]));
            $choices['tools'] = ($choices['tools'] ?? []) + $defaults['tools'];
            $choices['from_defaults'] = true;
        }

        $choices['default_settings'] = $defaults['settings'];

        return $choices;
    }
}
