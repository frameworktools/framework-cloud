<?php

namespace App\Support;

use App\Models\SkillTag;

class SkillTags
{
    public const DEFAULTS = [
        'framework' => 'Framework', 'visualization' => 'Visualization',
        'modeling' => 'Modeling', 'prose' => 'AI Prose', 'notebooks' => 'Notebooks',
        'data-reading' => 'Reading data', 'packages' => 'Packages',
        'outputs' => 'Outputs', 'publishing' => 'Publishing', 'privacy' => 'Privacy',
        'exploration' => 'Exploration', 'publication' => 'Publication',
    ];

    public const SKILLS = [
        'framework-data' => ['framework', 'data-reading'],
        'framework-packages' => ['framework', 'packages'],
        'framework-outputs' => ['framework', 'outputs', 'publishing'],
        'framework-sensitive-data' => ['framework', 'privacy'],
        'framework-workflow' => ['framework', 'notebooks'],
        'ggplot2-exploring' => ['visualization', 'exploration'],
        'ggplot2-publishing' => ['visualization', 'publication'],
        'framework-stata' => ['framework', 'notebooks'],
        'matplotlib-exploring' => ['visualization', 'exploration'],
        'stata-graphs' => ['visualization', 'exploration'],
        'framework-reports' => ['framework', 'publishing'],
        'writing' => ['prose', 'publication'],
    ];

    public static function seed(): void
    {
        foreach (self::DEFAULTS as $key => $name) {
            SkillTag::firstOrCreate(['key' => $key], ['name' => $name, 'position' => array_search($key, array_keys(self::DEFAULTS))]);
        }
    }
}
