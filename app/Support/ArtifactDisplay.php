<?php

namespace App\Support;

use App\Support\Dags\ArtifactDag;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * What an embedded artifact shows. Framework Cloud owns these settings: the
 * owner sets them on the artifact's Settings tab, packages set them when they
 * publish, and embed documents carry only what they allow. Every setting is on
 * by default; an artifact stores only those that differ (documents.display).
 * See docs/plans/2026-11-21-artifact-display.md.
 */
class ArtifactDisplay
{
    /** Every setting, with the embed kinds it applies to. Order is the order shown. */
    private const SETTINGS = [
        'title' => ['kinds' => ['model', 'table', 'test', 'code_snippet', 'dag', 'image', 'vega-lite'], 'label' => 'Title', 'help' => 'The title above the artifact.'],
        'description' => ['kinds' => '*', 'label' => 'Description', 'help' => 'The description under the artifact.'],
        'author' => ['kinds' => '*', 'label' => 'Author', 'help' => 'Your name, when your profile is public.'],
        'details' => ['kinds' => ['model', 'test'], 'label' => 'Details', 'help' => 'Fit statistics and other tables, in a closed section.'],
        'variable_tips' => ['kinds' => ['model'], 'label' => 'Variable tips', 'help' => 'Each term’s variable, label, and summary in a tip.'],
        'column_summaries' => ['kinds' => ['table'], 'label' => 'Column summaries', 'help' => 'Each column’s summary in a tip on its header.'],
        'output' => ['kinds' => ['code_snippet'], 'label' => 'Output', 'help' => 'Off, the output starts hidden behind Show output.'],
        'links' => ['kinds' => ['code_snippet'], 'label' => 'Notebook and model links', 'help' => 'Links under the code.'],
        'legend' => ['kinds' => ['dag'], 'label' => 'Legend', 'help' => 'Exposure, outcome, and other roles under the drawing.'],
        'analysis' => ['kinds' => ['dag'], 'label' => 'Analysis', 'help' => 'Adjustment sets and testable implications.'],
        'dagitty' => ['kinds' => ['dag'], 'label' => 'DAGitty specification', 'help' => 'The model text, with a copy button.'],
        'code' => ['kinds' => ['dag'], 'label' => 'Source code', 'help' => 'The code that built the graph, with a copy button.'],
        'interactive' => ['kinds' => ['vega-lite'], 'label' => 'Interactive chart', 'help' => 'Off, the static image shows instead.'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::SETTINGS);
    }

    /**
     * The settings an artifact offers, with their current values. A setting
     * that has nothing to act on (code a DAG doesn't have, an image a chart
     * lacks) is left out.
     *
     * @return list<array{key: string, label: string, help: string, value: bool}>
     */
    public static function options(string $kind, ?array $data, bool $hasImage, ?array $display): array
    {
        $out = [];
        foreach (self::SETTINGS as $key => $setting) {
            if ($setting['kinds'] !== '*' && ! in_array($kind, $setting['kinds'], true)) {
                continue;
            }
            if (! self::offered($key, $kind, $data, $hasImage)) {
                continue;
            }
            $label = $setting['label'];
            if ($key === 'details') {
                $label = $kind === 'test' ? 'Test details' : 'Model details';
            }
            if ($key === 'code') {
                $label = (ArtifactDag::LANGUAGES[$data['code']['language'] ?? ''] ?? 'Source').' code';
            }
            $out[] = ['key' => $key, 'label' => $label, 'help' => $setting['help'], 'value' => ($display[$key] ?? true) !== false];
        }

        return $out;
    }

    private static function offered(string $key, string $kind, ?array $data, bool $hasImage): bool
    {
        return match ($key) {
            'variable_tips' => ! empty($data['variables']),
            'column_summaries' => collect($data['tables'] ?? [])->contains(fn ($table) => collect($table['columns'] ?? [])->contains(fn ($column) => isset($column['summary']))),
            'links' => ! empty($data['display']['links']),
            'analysis' => isset($data['analysis']['adjustment_sets']) || ! empty($data['analysis']['implications']),
            'code' => isset($data['code']),
            'interactive' => $hasImage,
            default => true,
        };
    }

    /**
     * Settings as sent by a package or the API: a JSON object, or its text, of
     * keys to booleans. Null when none were sent.
     *
     * @return array<string, bool>|null
     *
     * @throws ValidationException
     */
    public static function parse(mixed $input, string $field = 'display'): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }
        if (is_string($input)) {
            try {
                $input = json_decode($input, true, 4, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([$field => 'Display settings are a JSON object of names to true or false.']);
            }
        }
        if (! is_array($input) || ($input !== [] && array_is_list($input))) {
            throw ValidationException::withMessages([$field => 'Display settings are a JSON object of names to true or false.']);
        }
        $out = [];
        foreach ($input as $key => $value) {
            if (! array_key_exists((string) $key, self::SETTINGS)) {
                throw ValidationException::withMessages([$field => "Unknown display setting \"{$key}\". Settings: ".implode(', ', self::keys()).'.']);
            }
            if (! is_bool($value)) {
                throw ValidationException::withMessages([$field => "Display setting \"{$key}\" takes true or false."]);
            }
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /**
     * The stored settings after a change: keys the change names take its
     * values, and settings back at their default are dropped.
     *
     * @param  array<string, bool>|null  $stored
     * @param  array<string, bool>  $change
     * @return array<string, bool>|null
     */
    public static function merge(?array $stored, array $change): ?array
    {
        $merged = array_filter(array_merge($stored ?? [], $change), fn (bool $value) => $value === false);

        return $merged === [] ? null : $merged;
    }

    /** An embed document with what the settings turn off left out. */
    public static function apply(array $document, ?array $display): array
    {
        $off = fn (string $key) => ($display[$key] ?? true) === false;
        if (! $display) {
            return $document;
        }
        if ($off('title') && $document['kind'] !== 'page') {
            $document['title'] = null;
        }
        if ($off('description')) {
            $document['description'] = null;
        }
        if ($off('author')) {
            $document['author'] = null;
        }
        // A chart turned static is its own fallback image
        if ($off('interactive') && $document['kind'] === 'vega-lite' && is_array($document['image'] ?? null)) {
            $document['kind'] = 'image';
            $document['spec'] = null;
        }
        $data = $document['data'];
        if (! is_array($data)) {
            return $document;
        }
        switch ($document['kind']) {
            case 'model':
            case 'test':
                if ($off('details')) {
                    $data['display'] = ['details' => false] + (is_array($data['display'] ?? null) ? $data['display'] : []);
                }
                if ($document['kind'] === 'model' && $off('variable_tips')) {
                    unset($data['variables'], $data['terms']);
                }
                break;
            case 'table':
                if ($off('column_summaries')) {
                    foreach ($data['tables'] ?? [] as $t => $table) {
                        foreach ($table['columns'] ?? [] as $c => $column) {
                            unset($data['tables'][$t]['columns'][$c]['summary']);
                        }
                    }
                }
                break;
            case 'code_snippet':
                if ($off('output')) {
                    $data['display']['output'] = false;
                }
                if ($off('links')) {
                    $data['display']['links'] = [];
                }
                break;
            case 'dag':
                if ($off('legend')) {
                    $data['display'] = ['legend' => false];
                }
                foreach (['analysis', 'dagitty', 'code'] as $key) {
                    if ($off($key)) {
                        unset($data[$key]);
                    }
                }
                break;
        }
        $document['data'] = $data;

        return $document;
    }
}
