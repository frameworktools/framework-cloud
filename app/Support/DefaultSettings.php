<?php

namespace App\Support;

class DefaultSettings
{
    /**
     * Mirrors the framework R package's settings meta.version.
     */
    public const SCHEMA_VERSION = '2';

    /**
     * The default settings document seeded for a new account.
     *
     * Deliberately thin: the R package ships full local defaults and stub
     * templates (AGENTS.md, AI skills, quarto configs) and overlays this
     * document on top of them. Only keys a user plausibly customizes in the
     * cloud belong here — this is the "default stubs users overwrite" layer.
     */
    public static function document(): array
    {
        return [
            'meta' => [
                'version' => self::SCHEMA_VERSION,
            ],
            'author' => [
                'name' => '',
                'email' => '',
                'affiliation' => '',
            ],
            'defaults' => [
                // No blueprint by default: library(framework) + a token is all you
                // need. Blueprints are opinionated offerings users opt into.
                'project_type' => 'bare',
                'notebook_format' => 'quarto',
                'ide' => 'positron',
                'use_git' => true,
                'use_renv' => false,
                'seed_on_scaffold' => true,
            ],
            'ai' => [
                'enabled' => true,
                'canonical_file' => 'AGENTS.md',
                'skills' => true,
            ],
            'quarto' => (object) [],
            'project_types' => [
                'bare' => [
                    'label' => 'Bare (no blueprint)',
                    'description' => 'Just settings.yml, an .Rproj, and git — bring your own house style.',
                ],
                'project' => [
                    'label' => 'Project',
                    'description' => 'Full data-analysis project structure.',
                ],
                'project_sensitive' => [
                    'label' => 'Sensitive project',
                    'description' => 'Project with hardened handling for sensitive data.',
                ],
                'course' => [
                    'label' => 'Course',
                    'description' => 'Slides, assignments, and course materials.',
                ],
                'presentation' => [
                    'label' => 'Presentation',
                    'description' => 'A single presentation.',
                ],
            ],
        ];
    }
}
