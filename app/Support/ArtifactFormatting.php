<?php

namespace App\Support;

final class ArtifactFormatting
{
    /** $decimal: the value's column holds fractions, so whole numbers there get the same decimal places. */
    public static function value(mixed $value, bool $decimal = false): string
    {
        if ($decimal && is_int($value)) {
            return number_format($value, 3, '.', '');
        }
        if ($value === null) {
            return 'NA';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_float($value)) {
            return number_format($value, 3, '.', '');
        }

        return (string) $value;
    }

    public static function coefficientLabel(string $name): string
    {
        return [
            'term' => 'Term', 'estimate' => 'Estimate', 'std_error' => 'Std. error', 'std.error' => 'Std. error',
            'statistic' => 'Test statistic', 'p_value' => 'p-value', 'p.value' => 'p-value',
            'conf_low' => 'CI lower', 'conf.low' => 'CI lower', 'conf_high' => 'CI upper', 'conf.high' => 'CI upper',
            'component' => 'Component', 'group' => 'Group', 'variance' => 'Variance', 'std_dev' => 'Std. deviation',
            'correlation' => 'Correlation', 'edf' => 'EDF', 'ref_df' => 'Reference df', 'feature' => 'Feature', 'importance' => 'Importance',
        ][$name] ?? ucfirst(str_replace('_', ' ', $name));
    }
}
