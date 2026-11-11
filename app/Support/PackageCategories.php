<?php

namespace App\Support;

class PackageCategories
{
    public const CATEGORIES = [
        'analysis' => 'Data analysis', 'data' => 'Data processing', 'databases' => 'Databases',
        'modeling' => 'Modeling', 'visualization' => 'Visualization', 'tables' => 'Tables',
        'specialized' => 'Specialized analysis', 'apps' => 'Apps', 'other' => 'Other',
    ];

    private const SETS = [
        'analysis' => ['tidy-core', 'tidyverse-analysis', 'tidyverse-extended', 'python-data-analysis', 'polars-python'],
        'data' => ['large-data-r'],
        'databases' => ['tidy-database', 'python-database', 'tidy-omop'],
        'modeling' => ['regression-r', 'easystats-r', 'tidymodels', 'bayes-r', 'causal-inference-r', 'survival-r', 'survey-r', 'python-ml', 'python-bayes'],
        'visualization' => ['python-viz'],
        'tables' => ['tables-r'],
        'specialized' => ['spatial-r', 'text-r', 'python-spatial'],
        'apps' => ['shiny-r', 'plumber-r', 'shiny-python', 'fastapi-python'],
    ];

    public static function for(string $key): string
    {
        foreach (self::SETS as $category => $members) {
            if (in_array($key, $members, true)) {
                return $category;
            }
        }

        return 'other';
    }

    public static function options(): array
    {
        return collect(self::CATEGORIES)->map(fn ($name, $key) => ['key' => $key, 'name' => $name])->values()->all();
    }

    public static function setTags(string $key, ?string $category = null): array
    {
        $tags = match ($category ?? self::for($key)) {
            'analysis', 'data', 'databases' => ['data-management'],
            'modeling', 'specialized' => ['statistical-modeling'],
            'tables' => ['notebooks'],
            'apps' => ['apps'],
            'visualization' => ['data-visualization'],
            default => [],
        };
        if (in_array($key, ['tidy-core', 'tidyverse-analysis', 'tidyverse-extended', 'tidy-database', 'tidy-omop'], true)) {
            $tags[] = 'tidyverse';
        }
        if (in_array($key, ['tidy-core', 'tidyverse-analysis', 'tidyverse-extended', 'easystats-r'], true)) {
            $tags[] = 'data-visualization';
        }
        if ($key === 'python-data-analysis') {
            $tags[] = 'pandas';
        }
        if ($key === 'polars-python') {
            $tags[] = 'polars';
        }

        return array_values(array_unique($tags));
    }

    public static function setTagOptions(): array
    {
        return [
            ['key' => 'tidyverse', 'name' => 'Tidyverse', 'kind' => 'ecosystem'],
            ['key' => 'pandas', 'name' => 'pandas', 'kind' => 'ecosystem'],
            ['key' => 'polars', 'name' => 'Polars', 'kind' => 'ecosystem'],
            ['key' => 'data-management', 'name' => 'Data management', 'kind' => 'theme'],
            ['key' => 'statistical-modeling', 'name' => 'Statistical modeling', 'kind' => 'theme'],
            ['key' => 'notebooks', 'name' => 'Notebooks', 'kind' => 'theme'],
            ['key' => 'apps', 'name' => 'Apps', 'kind' => 'theme'],
            ['key' => 'data-visualization', 'name' => 'Data visualization', 'kind' => 'theme'],
        ];
    }
}
