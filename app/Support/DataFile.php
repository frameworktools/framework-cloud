<?php

namespace App\Support;

use Throwable;

/**
 * A CSV or TSV file as the typed table representation the R and Python
 * packages send: inferred column types, a profile of every column computed
 * over all rows, and the first rows for the reader.
 */
class DataFile
{
    public const MAX_ROWS = 500_000;

    public const MAX_COLUMNS = 200;

    public const SHOWN_ROWS = 1000;

    private const MISSING = ['', 'NA', 'N/A', 'NULL', 'null', 'NaN'];

    private const BOOLEANS = ['true' => true, 'TRUE' => true, 'True' => true, 'false' => false, 'FALSE' => false, 'False' => false];

    public static function isTabular(?string $filename, ?string $contentType = null): bool
    {
        $type = strtolower(explode(';', (string) $contentType)[0]);

        return in_array($type, ['text/csv', 'text/tab-separated-values'], true)
            || in_array(strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION)), ['csv', 'tsv'], true);
    }

    public static function delimiter(?string $filename, ?string $contentType = null): string
    {
        return str_starts_with(strtolower((string) $contentType), 'text/tab-separated') || str_ends_with(strtolower((string) $filename), '.tsv') ? "\t" : ',';
    }

    /** The representation as JSON, or null when the file is too large or not a table. */
    public static function representation(string $path, string $delimiter, string $filename): ?string
    {
        try {
            return self::build($path, $delimiter, $filename);
        } catch (Throwable) {
            return null;
        }
    }

    private static function build(string $path, string $delimiter, string $filename): ?string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }
        try {
            $header = fgetcsv($handle, null, $delimiter, '"', '');
            if (! is_array($header) || $header === [null]) {
                return null;
            }
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            }
            $width = count($header);
            if ($width > self::MAX_COLUMNS) {
                return null;
            }
            $names = self::names($header);
            $values = array_fill(0, $width, []);
            $count = 0;
            while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (++$count > self::MAX_ROWS) {
                    return null;
                }
                for ($i = 0; $i < $width; $i++) {
                    $cell = $row[$i] ?? '';
                    $values[$i][] = in_array(trim((string) $cell), self::MISSING, true) ? null : (string) $cell;
                }
            }
        } finally {
            fclose($handle);
        }

        $columns = [];
        $converted = [];
        foreach ($names as $i => $name) {
            $type = self::type($values[$i]);
            $converted[$i] = array_map(fn ($value) => self::convert($value, $type), $values[$i]);
            // Whole numbers too long for an integer keep their exact digits as text
            if ($type === 'number' && array_filter($converted[$i], 'is_string') !== []) {
                $type = 'string';
                $converted[$i] = $values[$i];
            }
            $columns[] = ['name' => $name, 'type' => $type, 'summary' => self::summary($converted[$i], $type)];
            unset($values[$i]);
        }
        $shown = min($count, self::SHOWN_ROWS);
        $rows = [];
        for ($r = 0; $r < $shown; $r++) {
            $rows[] = array_map(fn ($column) => $column[$r], $converted);
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'tsv' || $delimiter === "\t" ? 'tsv' : 'csv';

        $json = json_encode([
            'schema' => 'framework.artifact/v1', 'kind' => 'table',
            'source' => ['language' => 'file', 'class' => [$extension]],
            'tables' => [['title' => $filename, 'role' => 'data', 'columns' => $columns, 'rows' => $rows, 'row_count' => $shown, 'total_rows' => $count]],
            'metrics' => [],
        ], JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json !== false && strlen($json) <= ArtifactRepresentation::MAX_BYTES ? $json : null;
    }

    /** Header names, with blanks named column_N and repeats numbered. */
    private static function names(array $header): array
    {
        $names = [];
        $seen = [];
        foreach ($header as $i => $name) {
            $name = trim((string) $name);
            $name = $name === '' ? 'column_'.($i + 1) : $name;
            $base = $name;
            for ($n = 2; isset($seen[$name]); $n++) {
                $name = $base.'_'.$n;
            }
            $seen[$name] = true;
            $names[] = $name;
        }

        return $names;
    }

    private static function type(array $values): string
    {
        $present = array_filter($values, fn ($value) => $value !== null);
        if ($present === []) {
            return 'string';
        }
        $checks = [
            'boolean' => fn ($v) => array_key_exists($v, self::BOOLEANS),
            // A leading zero marks an identifier such as 007, which stays text
            'number' => fn ($v) => is_numeric($v) && ! preg_match('/^\s|\s$/', $v) && ! preg_match('/^[+-]?0\d/', $v) && ! preg_match('/^0x/i', $v),
            'date' => fn ($v) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)),
            'datetime' => fn ($v) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $v),
        ];
        foreach ($checks as $type => $check) {
            $all = true;
            foreach ($present as $value) {
                if (! $check($value)) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return $type;
            }
        }

        return 'string';
    }

    private static function convert(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => self::BOOLEANS[$value],
            'number' => self::number($value),
            default => $value,
        };
    }

    private static function number(string $value): int|float|string
    {
        if (preg_match('/^[+-]?\d+$/', $value)) {
            $int = filter_var($value, FILTER_VALIDATE_INT);

            // Beyond PHP's integer range the digits are kept exactly as text
            return $int === false ? $value : $int;
        }
        $float = (float) $value;

        return is_finite($float) ? $float : $value;
    }

    private static function summary(array $values, string $type): array
    {
        $present = array_values(array_filter($values, fn ($value) => $value !== null));
        $summary = ['count' => count($values), 'missing' => count($values) - count($present),
            'distinct' => count(array_unique(array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, $present)))];
        if ($present === []) {
            return $summary;
        }
        if ($type === 'number') {
            $numbers = array_values(array_filter($present, fn ($v) => ! is_string($v)));
            if ($numbers === []) {
                return $summary;
            }
            sort($numbers);
            $n = count($numbers);
            $mean = array_sum($numbers) / $n;
            $squares = 0.0;
            foreach ($numbers as $number) {
                $squares += ($number - $mean) ** 2;
            }
            $summary += ['mean' => $mean];
            if ($n > 1) {
                $summary['sd'] = sqrt($squares / ($n - 1));
            }
            $summary += ['min' => $numbers[0], 'q1' => self::quantile($numbers, 0.25), 'median' => self::quantile($numbers, 0.5),
                'q3' => self::quantile($numbers, 0.75), 'max' => $numbers[$n - 1]];
            $summary['histogram'] = self::histogram($numbers, $summary['distinct']);

            return $summary;
        }
        if (in_array($type, ['date', 'datetime'], true)) {
            $sorted = $present;
            sort($sorted, SORT_STRING);
            $summary += ['min' => $sorted[0], 'max' => end($sorted)];
        }
        // When every value is different (IDs, timestamps), a list of values seen once says nothing
        if ($summary['distinct'] < count($present) || count($present) === 1) {
            $summary['top'] = self::top($present);
        }

        return $summary;
    }

    private static function quantile(array $sorted, float $p): float|int
    {
        $h = (count($sorted) - 1) * $p;
        $low = (int) floor($h);
        $high = min($low + 1, count($sorted) - 1);
        $value = $sorted[$low] + ($h - $low) * ($sorted[$high] - $sorted[$low]);

        // A whole-number column keeps whole-number quantiles where they land on one
        return is_int($sorted[$low]) && is_int($sorted[$high]) && floor($value) == $value ? (int) $value : $value;
    }

    private static function histogram(array $sorted, int $distinct): array
    {
        $min = $sorted[0];
        $max = end($sorted);
        $bins = max(1, min(20, $distinct));
        if ($max == $min) {
            return ['edges' => [$min, $max], 'counts' => [count($sorted)]];
        }
        $width = ($max - $min) / $bins;
        $edges = [];
        for ($i = 0; $i <= $bins; $i++) {
            $edges[] = match ($i) {
                0 => $min,
                $bins => $max,
                default => $min + $i * $width,
            };
        }
        $counts = array_fill(0, $bins, 0);
        foreach ($sorted as $value) {
            $counts[min($bins - 1, (int) floor(($value - $min) / $width))]++;
        }

        return ['edges' => $edges, 'counts' => $counts];
    }

    private static function top(array $present): array
    {
        $counts = [];
        $originals = [];
        foreach ($present as $value) {
            $key = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $originals[$key] ??= $value;
        }
        arsort($counts);
        $top = [];
        foreach (array_slice($counts, 0, 8, true) as $key => $count) {
            $top[] = ['value' => $originals[$key], 'count' => $count];
        }

        return $top;
    }
}
