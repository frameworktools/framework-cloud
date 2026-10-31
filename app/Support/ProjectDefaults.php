<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * What a user's new projects start with when they don't choose otherwise.
 * Some of it lives in the synced settings document that the R package also
 * reads (the default blueprint, git, renv, and AI); the rest is cloud-only in
 * users.project_defaults (package sets, skill sets, tools, and sensitive).
 */
class ProjectDefaults
{
    /**
     * @return array{blueprint: string, package_sets: list<string>, skill_sets: list<string>, tools: array<string, bool>, settings: array{sensitive: bool, git: bool, ai: bool}}
     */
    public static function for(User $user): array
    {
        $doc = $user->globalSettings()->document;
        $own = $user->project_defaults ?? [];

        return [
            'blueprint' => 'framework/'.Arr::get($doc, 'defaults.project_type', 'bare'),
            'package_sets' => array_values($own['package_sets'] ?? []),
            'skill_sets' => array_values($own['skill_sets'] ?? []),
            'tools' => ['renv' => (bool) Arr::get($doc, 'defaults.use_renv', false)] + ($own['tools'] ?? []),
            'settings' => [
                'sensitive' => (bool) ($own['sensitive'] ?? false),
                'git' => (bool) Arr::get($doc, 'defaults.use_git', true),
                'ai' => (bool) Arr::get($doc, 'ai.enabled', true),
            ],
        ];
    }

    /**
     * The choices a resolve starts from. Without a blueprint or structure,
     * the default blueprint and the preferred pieces apply. Settings always
     * fall back to the defaults, with the blueprint's own settings in between.
     *
     * @param  array<string, mixed>  $choices
     * @return array<string, mixed>
     */
    public static function apply(User $user, array $choices): array
    {
        $defaults = self::for($user);

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
