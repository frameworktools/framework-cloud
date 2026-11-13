<?php

namespace App\Support;

use App\Models\SkillTag;

class SkillTags
{
    public const CATEGORIES = [
        'framework' => 'Framework', 'data-cleaning' => 'Data cleaning',
        'modeling' => 'Modeling', 'visualization' => 'Visualization',
        'writing' => 'Writing', 'reports' => 'Reports', 'quarto' => 'Quarto',
        'jupyter' => 'Jupyter', 'git' => 'Git', 'privacy' => 'Privacy',
    ];

    public static function categories(): array
    {
        return collect(self::CATEGORIES)->map(fn ($name, $key) => ['key' => $key, 'name' => $name])->values()->all();
    }

    public const DEFAULTS = [
        'framework' => 'Framework', 'visualization' => 'Visualization',
        'modeling' => 'Modeling', 'prose' => 'AI Prose', 'notebooks' => 'Notebooks',
        'data-reading' => 'Reading data', 'packages' => 'Packages',
        'outputs' => 'Outputs', 'publishing' => 'Publishing', 'privacy' => 'Privacy',
        'exploration' => 'Exploration', 'publication' => 'Publication',
        'data-cleaning' => 'Data cleaning', 'writing' => 'Writing', 'reports' => 'Reports',
        'quarto' => 'Quarto', 'jupyter' => 'Jupyter', 'git' => 'Git',
    ];

    public const SKILLS = [
        'framework-data' => ['framework', 'data-reading'],
        'framework-packages' => ['framework', 'packages'],
        'framework-outputs' => ['framework', 'outputs'],
        'framework-cache' => ['framework'], 'framework-databases' => ['framework'],
        'framework-publishing' => ['framework', 'publishing'],
        'framework-sensitive-data' => ['framework', 'privacy'],
        'framework-workflow' => ['framework', 'notebooks'],
        'ggplot2-figures' => ['visualization', 'exploration', 'publication'],
        'framework-stata' => ['framework', 'notebooks'],
        'matplotlib-figures' => ['visualization', 'exploration', 'publication'],
        'stata-graphs' => ['visualization', 'exploration', 'publication'],
        'framework-reports' => ['framework', 'reports', 'publishing'],
        'writing' => ['writing', 'publication'],
        'cleaning-data' => ['data-cleaning'], 'model-results' => ['modeling', 'publication'],
        'report-structure' => ['reports'], 'quarto-documents' => ['quarto', 'notebooks'],
        'jupyter-notebooks' => ['jupyter', 'notebooks'], 'git-workflow' => ['git'],
    ];

    public static function seed(): void
    {
        foreach (self::DEFAULTS as $key => $name) {
            SkillTag::firstOrCreate(['key' => $key], ['name' => $name, 'position' => array_search($key, array_keys(self::DEFAULTS))]);
        }
    }
}
