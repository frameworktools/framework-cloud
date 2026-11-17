<?php

namespace App\Support;

/**
 * The ratio view of a coefficient table, for models with an exponentiated scale (odds, hazard,
 * rate, and time ratios), as Stata's `or` option shows it: the same columns and rows, with the
 * estimate and interval exponentiated and the delta-method std. error, exp(b) * se. Statistics and
 * p-values are unchanged. framework-embed shows the same two views behind the same switch.
 */
final class RatioTable
{
    public const SCALES = ['Odds ratio' => 'Log odds', 'Rate ratio' => 'Log rate', 'Hazard ratio' => 'Log hazard', 'Time ratio' => 'Log time'];

    /** The ratio label for a model's exponentiate value, or null when it has no ratio scale. */
    public static function ratio(mixed $exponentiate): ?string
    {
        return is_string($exponentiate) && isset(self::SCALES[$exponentiate]) ? $exponentiate : null;
    }

    /** The table on the ratio scale, or null when it has no estimate column. */
    public static function from(array $table, string $ratio): ?array
    {
        $names = array_column($table['columns'], 'name');
        $estimate = self::index($names, 'estimate');
        if ($estimate === null) {
            return null;
        }
        $error = self::index($names, 'std_error', 'std.error');
        $bounds = array_filter([self::index($names, 'conf_low', 'conf.low'), self::index($names, 'conf_high', 'conf.high')], fn ($i) => $i !== null);
        $number = fn ($value) => (is_int($value) || is_float($value)) && is_finite((float) $value);
        $exp = function (mixed $value) use ($number): mixed {
            if (! $number($value)) {
                return $value;
            }
            $result = exp((float) $value);

            return is_finite($result) ? $result : null;
        };

        $table = self::renamed($table, $ratio);
        $table['rows'] = array_map(function (array $row) use ($estimate, $error, $bounds, $exp, $number): array {
            $b = $row[$estimate] ?? null;
            if ($error !== null && $number($b) && $number($row[$error] ?? null)) {
                $se = exp((float) $b) * $row[$error];
                $row[$error] = is_finite($se) ? $se : null;
            }
            foreach ($bounds as $index) {
                $row[$index] = $exp($row[$index] ?? null);
            }
            $row[$estimate] = $exp($b);

            return $row;
        }, $table['rows']);

        return $table;
    }

    /** The table with its estimate column named for its scale, such as "Log odds". */
    public static function renamed(array $table, string $label): array
    {
        $estimate = self::index(array_column($table['columns'], 'name'), 'estimate');
        if ($estimate !== null) {
            $table['columns'][$estimate]['name'] = strtolower(str_replace(' ', '_', $label));
        }

        return $table;
    }

    private static function index(array $names, string ...$candidates): ?int
    {
        foreach ($candidates as $name) {
            if (($index = array_search($name, $names, true)) !== false) {
                return $index;
            }
        }

        return null;
    }
}
