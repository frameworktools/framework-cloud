<?php

namespace App\Support;

use DateTimeImmutable;
use JsonException;

/** The portable, data-only representation emitted by framework clients. */
class ArtifactRepresentation
{
    public const MAX_BYTES = 8_388_608;

    private const MAX_LABEL = 200;

    private const MAX_STRING = 10000;

    public const ROLES = ['coefficients', 'random_effects', 'importance', 'smooth_terms', 'anova', 'data'];

    private const MODEL_FIELDS = ['type', 'family', 'link', 'response', 'exponentiate'];

    public static function parse(string $json): ?array
    {
        if (strlen($json) > self::MAX_BYTES) {
            return null;
        }
        try {
            $structure = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
            if (! self::validStructure($structure)) {
                return null;
            }
            $input = json_decode($json, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            unset($structure);
        } catch (JsonException) {
            return null;
        }
        if (! is_array($input) || array_is_list($input)
            || ($input['schema'] ?? null) !== 'framework.artifact/v1'
            || ! in_array($input['kind'] ?? null, ['table', 'model', 'test'], true)
            || ! is_array($input['source'] ?? null)
            || ! in_array($input['source']['language'] ?? null, ['r', 'python', 'file'], true)
            || ! self::isList($input['source']['class'] ?? null)
            || ! self::isList($input['tables'] ?? null)
            || ! self::isList($input['metrics'] ?? null)) {
            return null;
        }
        $truncated = false;
        $classes = [];
        foreach ($input['source']['class'] as $class) {
            if (! is_string($class)) {
                return null;
            }
            if (count($classes) < 20) {
                $classes[] = self::label($class, $truncated);
            } else {
                $truncated = true;
            }
        }
        $tables = [];
        foreach ($input['tables'] as $table) {
            if (! is_array($table) || ! is_string($table['title'] ?? null)
                || ! self::isList($table['columns'] ?? null) || $table['columns'] === []
                || ! self::isList($table['rows'] ?? null)
                || ! is_int($table['row_count'] ?? null) || $table['row_count'] !== count($table['rows'])) {
                return null;
            }
            $tableTruncated = false;
            // A data file sends its first rows and says how many it has in all
            $totalRows = $table['row_count'];
            if (array_key_exists('total_rows', $table)) {
                if (! is_int($table['total_rows']) || $table['total_rows'] < $table['row_count']) {
                    return null;
                }
                $totalRows = $table['total_rows'];
                $tableTruncated = $totalRows > $table['row_count'];
            }
            $columns = [];
            foreach ($table['columns'] as $column) {
                if (! is_array($column) || ! is_string($column['name'] ?? null)
                    || ! in_array($column['type'] ?? null, ['number', 'string', 'boolean', 'date', 'datetime'], true)) {
                    return null;
                }
                if (count($columns) < 50) {
                    $columns[] = array_filter([
                        'name' => self::label($column['name'], $tableTruncated), 'type' => $column['type'],
                        'label' => is_string($column['label'] ?? null) && $column['label'] !== '' ? self::label($column['label'], $tableTruncated) : null,
                        'summary' => self::summary($column['summary'] ?? null, $tableTruncated),
                    ], fn ($value) => $value !== null);
                } else {
                    $tableTruncated = true;
                }
            }
            $rows = [];
            $unsafeColumns = [];
            foreach ($table['rows'] as $index => $row) {
                if (! self::isList($row) || count($row) !== count($table['columns'])) {
                    return null;
                }
                foreach ($row as $i => $value) {
                    if ($table['columns'][$i]['type'] === 'number' && self::unsafeInteger($value)) {
                        $unsafeColumns[$i] = true;
                    }
                    if (! self::matches($value, $table['columns'][$i]['type'])) {
                        return null;
                    }
                }
                if ($index < 1000) {
                    $shown = [];
                    foreach (array_slice($row, 0, 50) as $value) {
                        $shown[] = self::scalar($value, $tableTruncated);
                    }
                    $rows[] = $shown;
                } else {
                    $tableTruncated = true;
                }
            }
            foreach ($unsafeColumns as $columnIndex => $_) {
                if ($columnIndex < 50) {
                    $columns[$columnIndex]['type'] = 'string';
                    foreach ($rows as &$shownRow) {
                        if ($shownRow[$columnIndex] !== null) {
                            $shownRow[$columnIndex] = is_float($shownRow[$columnIndex]) ? json_encode($shownRow[$columnIndex], JSON_PRESERVE_ZERO_FRACTION) : (string) $shownRow[$columnIndex];
                        }
                    }
                    unset($shownRow);
                }
            }
            $title = self::label($table['title'], $tableTruncated);
            if (count($tables) < 10) {
                $tables[] = ['title' => $title, 'role' => in_array($table['role'] ?? null, self::ROLES, true) ? $table['role'] : null, 'columns' => $columns, 'rows' => $rows,
                    'precision_note' => $unsafeColumns === [] ? null : 'Large integer columns are shown as text to preserve their exact values.',
                    'row_count' => $totalRows, 'column_count' => count($table['columns']), 'truncated' => $tableTruncated];
            } else {
                $truncated = true;
            }
            $truncated = $truncated || $tableTruncated;
        }
        $metrics = [];
        foreach ($input['metrics'] as $metric) {
            if (! is_array($metric) || ! is_string($metric['name'] ?? null)
                || ! array_key_exists('value', $metric) || ! self::isScalar($metric['value'])) {
                return null;
            }
            if (count($metrics) < 50) {
                $metrics[] = ['name' => self::label($metric['name'], $truncated), 'value' => self::scalar($metric['value'], $truncated)];
            } else {
                $truncated = true;
            }
        }
        $test = $input['kind'] === 'test' ? self::test($input['test'] ?? null, $truncated) : null;
        if (($input['kind'] === 'table' && $tables === [])
            || ($input['kind'] === 'model' && $tables === [] && $metrics === [])
            || ($input['kind'] === 'test' && $test === null)) {
            return null;
        }
        $model = null;
        if (is_array($input['model'] ?? null) && ! array_is_list($input['model'])) {
            foreach (self::MODEL_FIELDS as $field) {
                if (is_string($input['model'][$field] ?? null) && $input['model'][$field] !== '') {
                    $model[$field] = self::label($input['model'][$field], $truncated);
                }
            }
        }
        $output = ['schema' => $input['schema'], 'kind' => $input['kind'],
            'source' => ['language' => $input['source']['language'], 'class' => $classes],
            'model' => $model, 'test' => $test,
            'tables' => $tables, 'metrics' => $metrics, 'truncated' => $truncated];
        if (array_key_exists('formula', $input)) {
            if (! is_string($input['formula'])) {
                return null;
            }
            $output['formula'] = self::scalar($input['formula'], $truncated);
            $output['truncated'] = $truncated;
        }

        return $output;
    }

    /** A column's summary over all rows. Parts that don't fit the contract are dropped, not the document. */
    private static function summary(mixed $summary, bool &$truncated): ?array
    {
        if (! is_array($summary) || array_is_list($summary)) {
            return null;
        }
        $output = [];
        foreach (['count', 'missing', 'distinct'] as $key) {
            if (is_int($summary[$key] ?? null) && $summary[$key] >= 0) {
                $output[$key] = $summary[$key];
            }
        }
        foreach (['mean', 'sd', 'min', 'q1', 'median', 'q3', 'max'] as $key) {
            $value = $summary[$key] ?? null;
            if (self::isNumber($value)) {
                $output[$key] = $value;
            } elseif (in_array($key, ['min', 'max'], true) && is_string($value) && strlen($value) <= 40) {
                $output[$key] = $value;
            }
        }
        $histogram = $summary['histogram'] ?? null;
        if (is_array($histogram) && self::isList($histogram['edges'] ?? null) && self::isList($histogram['counts'] ?? null)
            && count($histogram['edges']) >= 2 && count($histogram['edges']) <= 51
            && count($histogram['counts']) === count($histogram['edges']) - 1) {
            $edges = $histogram['edges'];
            $counts = $histogram['counts'];
            $valid = array_filter($edges, self::isNumber(...)) === $edges
                && array_filter($counts, fn ($count) => is_int($count) && $count >= 0) === $counts;
            for ($i = 1; $valid && $i < count($edges); $i++) {
                $valid = $edges[$i] >= $edges[$i - 1];
            }
            if ($valid) {
                $output['histogram'] = ['edges' => $edges, 'counts' => $counts];
            }
        }
        if (self::isList($summary['top'] ?? null)) {
            $top = [];
            foreach (array_slice($summary['top'], 0, 8) as $entry) {
                if (is_array($entry) && array_key_exists('value', $entry) && self::isScalar($entry['value'])
                    && is_int($entry['count'] ?? null) && $entry['count'] >= 0) {
                    $value = is_string($entry['value']) ? self::limit($entry['value'], 80, $truncated) : $entry['value'];
                    $top[] = ['value' => $value, 'count' => $entry['count']];
                }
            }
            if ($top !== []) {
                $output['top'] = $top;
            }
        }

        return $output === [] ? null : $output;
    }

    /** A hypothesis test's result. Only the method is required. */
    private static function test(mixed $test, bool &$truncated): ?array
    {
        if (! is_array($test) || array_is_list($test) || ! is_string($test['method'] ?? null) || trim($test['method']) === '') {
            return null;
        }
        $named = function (mixed $list) use (&$truncated): array {
            $output = [];
            foreach (self::isList($list) ? array_slice($list, 0, 20) : [] as $entry) {
                if (is_array($entry) && is_string($entry['name'] ?? null) && self::isNumber($entry['value'] ?? null)) {
                    $output[] = ['name' => self::label($entry['name'], $truncated), 'value' => $entry['value']];
                }
            }

            return $output;
        };
        $output = ['method' => self::label(trim($test['method']), $truncated)];
        if (in_array($test['alternative'] ?? null, ['two.sided', 'less', 'greater'], true)) {
            $output['alternative'] = $test['alternative'];
        }
        $statistic = $test['statistic'] ?? null;
        if (is_array($statistic) && is_string($statistic['name'] ?? null) && self::isNumber($statistic['value'] ?? null)) {
            $output['statistic'] = ['name' => self::label($statistic['name'], $truncated), 'value' => $statistic['value']];
        }
        foreach (['parameters', 'estimates', 'null_value'] as $key) {
            if (($list = $named($test[$key] ?? null)) !== []) {
                $output[$key] = $list;
            }
        }
        if (self::isNumber($test['p_value'] ?? null) && $test['p_value'] >= 0 && $test['p_value'] <= 1) {
            $output['p_value'] = $test['p_value'];
        }
        $interval = $test['conf_int'] ?? null;
        if (is_array($interval) && self::isNumber($interval['level'] ?? null) && $interval['level'] > 0 && $interval['level'] < 1) {
            $bounds = array_filter(['low' => $interval['low'] ?? null, 'high' => $interval['high'] ?? null], self::isNumber(...));
            if ($bounds !== [] && (count($bounds) < 2 || $bounds['low'] <= $bounds['high'])) {
                $output['conf_int'] = ['level' => $interval['level']] + $bounds;
            }
        }

        return $output;
    }

    private static function isNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }

    private static function validStructure(mixed $input): bool
    {
        if (! $input instanceof \stdClass || ! ($input->source ?? null) instanceof \stdClass
            || ! is_array($input->source->class ?? null) || ! is_array($input->tables ?? null)
            || ! is_array($input->metrics ?? null)) {
            return false;
        }
        foreach ($input->tables as $table) {
            if (! $table instanceof \stdClass || ! is_array($table->columns ?? null) || ! is_array($table->rows ?? null)) {
                return false;
            }
            foreach ($table->columns as $column) {
                if (! $column instanceof \stdClass) {
                    return false;
                }
            }
            foreach ($table->rows as $row) {
                if (! is_array($row)) {
                    return false;
                }
            }
        }
        foreach ($input->metrics as $metric) {
            if (! $metric instanceof \stdClass) {
                return false;
            }
        }

        return true;
    }

    private static function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    private static function isScalar(mixed $value): bool
    {
        return $value === null || is_string($value) || is_bool($value) || is_int($value)
            || (is_float($value) && is_finite($value));
    }

    private static function matches(mixed $value, string $type): bool
    {
        if ($value === null) {
            return true;
        }

        return match ($type) {
            'number' => is_int($value) || (is_float($value) && is_finite($value)),
            'boolean' => is_bool($value),
            'string' => is_string($value),
            'date', 'datetime' => is_string($value) && self::isDate($value, $type === 'datetime'),
            default => false,
        };
    }

    private static function isDate(string $value, bool $datetime): bool
    {
        $pattern = $datetime
            ? '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/D'
            : '/^\d{4}-\d{2}-\d{2}$/D';
        if (! preg_match($pattern, $value)) {
            return false;
        }
        try {
            new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();

            return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
        } catch (\Exception) {
            return false;
        }
    }

    private static function label(string $value, bool &$truncated): string
    {
        return self::limit($value, self::MAX_LABEL, $truncated);
    }

    private static function unsafeInteger(mixed $value): bool
    {
        return is_int($value) && ($value > 9007199254740991 || $value < -9007199254740991);
    }

    private static function scalar(mixed $value, bool &$truncated): mixed
    {
        if (self::unsafeInteger($value)) {
            return (string) $value;
        }

        return is_string($value) ? self::limit($value, self::MAX_STRING, $truncated) : $value;
    }

    private static function limit(string $value, int $length, bool &$truncated): string
    {
        if (mb_strlen($value) > $length) {
            $truncated = true;

            return mb_substr($value, 0, $length);
        }

        return $value;
    }
}
