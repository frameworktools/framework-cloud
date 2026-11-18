<?php

namespace App\Support;

/**
 * Tables of a model fit by posterior sampling (model.inference = "bayesian"), as the artifact
 * page shows them: the same columns named for what they hold (posterior mean and SD, credible
 * interval, R-hat, effective sample sizes). The ratio scale uses the package's ratio_ columns,
 * summaries of the exponentiated draws, never the exponent of the summaries. framework-embed
 * labels and warns the same way.
 */
final class PosteriorTable
{
    /** Above this, R-hat says the chains may not have converged. */
    public const RHAT_LIMIT = 1.01;

    private const LABELS = [
        'estimate' => 'Posterior mean', 'std_error' => 'Posterior SD', 'conf_low' => 'CrI lower', 'conf_high' => 'CrI upper',
        'rhat' => 'R-hat', 'ess_bulk' => 'Bulk ESS', 'ess_tail' => 'Tail ESS',
    ];

    private const RATIO_COLUMNS = ['ratio_estimate', 'ratio_std_error', 'ratio_conf_low', 'ratio_conf_high'];

    public static function is(?array $model): bool
    {
        return ($model['inference'] ?? null) === 'bayesian';
    }

    /** Whether the table carries the package's ratio-scale summaries. */
    public static function hasRatio(array $table): bool
    {
        return in_array('ratio_estimate', array_column($table['columns'], 'name'), true);
    }

    /** The table on the model scale: the ratio_ columns left out, the rest labeled. */
    public static function modelScale(array $table, ?string $estimateLabel = null): array
    {
        $keep = array_keys(array_filter($table['columns'], fn ($column) => ! in_array($column['name'], self::RATIO_COLUMNS, true)));

        return self::labeled(self::only($table, $keep), $estimateLabel);
    }

    /** The table on the ratio scale: the ratio_ columns in place of the model-scale ones. */
    public static function ratioScale(array $table, string $ratio): array
    {
        $names = array_column($table['columns'], 'name');
        foreach (['estimate', 'std_error', 'conf_low', 'conf_high'] as $name) {
            $from = array_search('ratio_'.$name, $names, true);
            $to = array_search($name, $names, true);
            if ($from === false || $to === false) {
                continue;
            }
            foreach ($table['rows'] as &$row) {
                $row[$to] = $row[$from] ?? null;
            }
            unset($row);
        }

        return self::modelScale($table, $ratio);
    }

    /**
     * What a reader should see beside a posterior: unconverged chains and divergent transitions.
     *
     * @return list<string>
     */
    public static function warnings(array $tables, array $metrics): array
    {
        $high = 0;
        foreach ($tables as $table) {
            $index = array_search('rhat', array_column($table['columns'], 'name'), true);
            if ($index === false) {
                continue;
            }
            foreach ($table['rows'] as $row) {
                $value = $row[$index] ?? null;
                if ((is_int($value) || is_float($value)) && $value > self::RHAT_LIMIT) {
                    $high++;
                }
            }
        }
        $warnings = [];
        if ($high > 0) {
            $warnings[] = 'R-hat above 1.01 for '.$high.' '.($high === 1 ? 'parameter' : 'parameters').': the chains may not have converged.';
        }
        $divergent = collect($metrics)->firstWhere('name', 'divergent_transitions')['value'] ?? null;
        if ((is_int($divergent) || is_float($divergent)) && $divergent > 0) {
            $warnings[] = number_format($divergent).' divergent '.($divergent == 1 ? 'transition' : 'transitions').': the posterior may be biased.';
        }

        return $warnings;
    }

    private static function labeled(array $table, ?string $estimateLabel): array
    {
        foreach ($table['columns'] as &$column) {
            $label = $column['name'] === 'estimate' && $estimateLabel !== null ? $estimateLabel : (self::LABELS[$column['name']] ?? null);
            if ($label !== null) {
                $column['display_label'] = $label;
            }
        }
        unset($column);

        return $table;
    }

    /** @param list<int> $keep */
    private static function only(array $table, array $keep): array
    {
        $table['columns'] = array_values(array_map(fn ($i) => $table['columns'][$i], $keep));
        $table['rows'] = array_map(fn ($row) => array_map(fn ($i) => $row[$i] ?? null, $keep), $table['rows']);
        // The columns shown, not the ratio_ ones carried alongside them
        $table['column_count'] = count($keep);

        return $table;
    }
}
