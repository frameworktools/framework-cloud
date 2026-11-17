<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * An embed's one-sentence text description, for screen readers and image alt
 * text. Built from the artifact's own data; terse and factual.
 */
class EmbedAlt
{
    private const MODEL_TYPES = [
        'linear' => 'Linear model', 'linear_ml' => 'Linear model', 'glm' => 'Generalized linear model',
        'mixed' => 'Mixed-effects model', 'survival' => 'Survival model', 'ordinal' => 'Ordinal regression',
        'multinomial' => 'Multinomial regression', 'additive' => 'Generalized additive model',
        'fixed_effects' => 'Fixed-effects regression', 'quantile' => 'Quantile regression', 'tree_ensemble' => 'Tree-based model',
    ];

    private const COLUMNS_NAMED = 5;

    public static function for(string $kind, string $title, ?array $data): string
    {
        $text = match ($kind) {
            'model' => $data ? self::model($data) : null,
            'table' => $data ? self::table($data) : null,
            'test' => $data ? self::test($data) : null,
            default => null,
        };

        return $text ?? $title;
    }

    private static function model(array $data): string
    {
        $model = $data['model'] ?? [];
        $label = self::modelLabel($model);
        if (filled($model['response'] ?? null)) {
            $label .= ' of '.$model['response'];
        }
        $coefficients = collect($data['tables'])->firstWhere('role', 'coefficients') ?? ($data['tables'][0] ?? null);
        if ($coefficients !== null) {
            $terms = (int) $coefficients['row_count'];
            $label .= ' with '.$terms.' '.Str::plural('term', $terms);
        }
        $text = $label.'.';
        if (filled($model['exponentiate'] ?? null)) {
            $text .= ' '.Str::ucfirst(Str::plural($model['exponentiate'])).' available.';
        }

        return $text;
    }

    private static function modelLabel(array $model): string
    {
        $type = $model['type'] ?? null;
        $family = strtolower($model['family'] ?? '');
        $link = strtolower($model['link'] ?? '');
        if ($type === 'glm' && $family === 'binomial' && in_array($link, ['logit', ''], true)) {
            return 'Logistic regression';
        }
        if ($type === 'glm' && $family === 'binomial' && $link === 'probit') {
            return 'Probit regression';
        }
        if ($type === 'glm' && $family === 'poisson') {
            return 'Poisson regression';
        }

        return self::MODEL_TYPES[$type] ?? 'Model';
    }

    private static function table(array $data): ?string
    {
        $table = $data['tables'][0] ?? null;
        if ($table === null) {
            return null;
        }
        $rows = (int) $table['row_count'];
        $columns = (int) $table['column_count'];
        $names = array_map(fn (array $column) => $column['name'], array_slice($table['columns'], 0, self::COLUMNS_NAMED));
        $more = $columns - count($names);
        if ($more > 0) {
            $names[] = $more.' more';
        }
        $list = count($names) > 2
            ? implode(', ', array_slice($names, 0, -1)).', and '.end($names)
            : implode(' and ', $names);

        return 'Table with '.number_format($rows).' '.Str::plural('row', $rows).' and '.$columns.' '.Str::plural('column', $columns).': '.$list.'.';
    }

    private static function test(array $data): ?string
    {
        $test = $data['test'] ?? null;
        if ($test === null) {
            return null;
        }
        $parts = [];
        if (isset($test['statistic'])) {
            $parts[] = $test['statistic']['name'].' = '.self::number($test['statistic']['value']);
        }
        if (isset($test['p_value'])) {
            $parts[] = $test['p_value'] < 0.001 ? 'p < 0.001' : 'p = '.self::number($test['p_value']);
        }

        return $parts === [] ? $test['method'].'.' : $test['method'].': '.implode(', ', $parts).'.';
    }

    /** Three significant digits, without exponents. */
    public static function number(int|float $value): string
    {
        if ($value == 0) {
            return '0';
        }
        $decimals = max(0, min(6, 2 - (int) floor(log10(abs($value)))));
        $text = number_format($value, $decimals, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
