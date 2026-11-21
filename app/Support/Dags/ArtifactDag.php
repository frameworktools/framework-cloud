<?php

namespace App\Support\Dags;

use App\Support\Snippets\CodeHighlighter;
use App\Support\Snippets\StyledText;
use InvalidArgumentException;
use JsonException;

/**
 * The dag artifact: a causal diagram with the DAGitty code that defines it,
 * and optionally the code that built it and what the producer computed from
 * it. Clients send a .dagitty file or framework.artifact/v1 JSON. This class
 * validates either and builds the normalized form the reader and embeds
 * draw, laid out once. See docs/plans/2026-11-21-dag-artifacts.md.
 */
class ArtifactDag
{
    public const KIND = 'dag';

    public const EXTENSION = 'dagitty';

    public const MAX_BYTES = 1_048_576;

    public const MAX_CODE_LINES = 400;

    public const MAX_SETS = 50;

    public const MAX_IMPLICATIONS = 200;

    public const LANGUAGES = ['r' => 'R', 'python' => 'Python', 'stata' => 'Stata'];

    public const ROLE_LABELS = ['exposure' => 'Exposure', 'outcome' => 'Outcome', 'adjusted' => 'Adjusted', 'latent' => 'Latent', 'selected' => 'Selected'];

    private const MAX_LABEL = 200;

    /** Whether JSON says it is a DAG, valid or not. */
    public static function claims(string $json): bool
    {
        return preg_match('/"kind"\s*:\s*"dag"/', $json) === 1;
    }

    public static function isDagittyFile(?string $filename): bool
    {
        return strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION)) === self::EXTENSION;
    }

    /**
     * The normalized DAG from JSON, or an exception whose message says what is wrong.
     *
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('A DAG can be at most 1 MB.');
        }
        try {
            $input = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The DAG is not valid JSON.');
        }
        if (! is_array($input) || array_is_list($input)) {
            throw new InvalidArgumentException('The DAG is not a JSON object.');
        }

        return self::normalize($input);
    }

    /**
     * The normalized DAG from DAGitty text alone.
     *
     * @throws InvalidArgumentException
     */
    public static function fromDagitty(string $text): array
    {
        return self::normalize(['schema' => 'framework.artifact/v1', 'kind' => self::KIND, 'dagitty' => $text]);
    }

    /** @throws InvalidArgumentException */
    public static function normalize(array $input): array
    {
        if (($input['schema'] ?? null) !== 'framework.artifact/v1' || ($input['kind'] ?? null) !== self::KIND) {
            throw new InvalidArgumentException('A DAG needs schema framework.artifact/v1 and kind dag.');
        }
        $text = $input['dagitty'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new InvalidArgumentException('A DAG needs dagitty, the graph as DAGitty model text.');
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $graph = DagittyParser::parse($text);

        $labels = is_array($input['labels'] ?? null) && ! array_is_list($input['labels']) ? $input['labels'] : [];
        $nodes = [];
        foreach ($graph['nodes'] as $node) {
            $label = $labels[$node['name']] ?? null;
            $label = is_string($label) && trim($label) !== '' ? mb_substr(trim(preg_replace('/\s+/', ' ', $label)), 0, self::MAX_LABEL) : null;
            $nodes[] = $node + ['label' => $label ?? $node['name']];
        }
        $layout = DagLayout::layout($nodes, $graph['edges']);
        $placed = [];
        foreach ($nodes as $index => $node) {
            $placed[] = array_filter([
                'name' => $node['name'],
                'label' => $node['label'] !== $node['name'] ? $node['label'] : null,
                'roles' => $node['roles'],
            ], fn ($value) => $value !== null) + array_diff_key($layout['nodes'][$index], ['name' => true]);
        }
        $names = array_map('strval', array_column($graph['nodes'], 'name'));

        return array_filter([
            'schema' => 'framework.artifact/v1',
            'kind' => self::KIND,
            'dag' => ['width' => $layout['width'], 'height' => $layout['height'], 'nodes' => $placed, 'edges' => $layout['edges']],
            'dagitty' => ['text' => $text, 'lines' => self::dagittyLines($text)],
            'code' => self::code($input['code'] ?? null),
            'analysis' => self::analysis($input['analysis'] ?? null, $graph['nodes'], $names),
        ], fn ($value) => $value !== null);
    }

    private static function code(mixed $code): ?array
    {
        if (! is_array($code) || ! is_string($code['text'] ?? null) || trim($code['text']) === '') {
            return null;
        }
        $language = $code['language'] ?? null;
        if (! is_string($language) || ! array_key_exists($language, self::LANGUAGES)) {
            throw new InvalidArgumentException('A DAG\'s code needs language: r, python, or stata.');
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $code['text']), "\n");
        if (substr_count($text, "\n") + 1 > self::MAX_CODE_LINES) {
            throw new InvalidArgumentException('A DAG\'s code can have at most '.self::MAX_CODE_LINES.' lines.');
        }
        $lines = StyledText::trim(CodeHighlighter::lines($text, $language));

        return ['language' => $language, 'text' => StyledText::text($lines), 'lines' => $lines];
    }

    /**
     * Exposures and outcomes from the graph, with the producer's adjustment
     * sets and implications. Names that aren't nodes are dropped.
     */
    private static function analysis(mixed $analysis, array $nodes, array $names): ?array
    {
        $role = fn (string $role) => array_values(array_map('strval', array_column(array_filter($nodes, fn ($node) => in_array($role, $node['roles'], true)), 'name')));
        $exposures = $role('exposure');
        $outcomes = $role('outcome');
        $analysis = is_array($analysis) ? $analysis : [];
        $known = array_flip($names);
        $nameList = function (mixed $list) use ($known): ?array {
            if (! is_array($list) || ! array_is_list($list)) {
                return null;
            }
            $out = [];
            foreach ($list as $name) {
                if ((! is_string($name) && ! is_int($name)) || ! isset($known[(string) $name])) {
                    return null;
                }
                $out[] = (string) $name;
            }

            return array_values(array_unique($out));
        };

        $sets = null;
        if ($exposures !== [] && $outcomes !== [] && is_array($analysis['adjustment_sets'] ?? null) && array_is_list($analysis['adjustment_sets'])) {
            $sets = [];
            foreach (array_slice($analysis['adjustment_sets'], 0, self::MAX_SETS) as $set) {
                if (($members = $nameList($set)) !== null) {
                    $sets[] = $members;
                }
            }
        }
        $implications = null;
        if (is_array($analysis['implications'] ?? null) && array_is_list($analysis['implications'])) {
            $implications = [];
            foreach (array_slice($analysis['implications'], 0, self::MAX_IMPLICATIONS) as $entry) {
                $pair = is_array($entry) ? $nameList([$entry['x'] ?? null, $entry['y'] ?? null]) : null;
                $given = is_array($entry) ? $nameList($entry['given'] ?? []) : null;
                if ($pair !== null && count($pair) === 2 && $given !== null) {
                    $implications[] = ['x' => $pair[0], 'y' => $pair[1], 'given' => $given];
                }
            }
        }
        if ($exposures === [] && $outcomes === [] && $implications === null) {
            return null;
        }

        return array_filter([
            'effect' => in_array($analysis['effect'] ?? null, ['total', 'direct'], true) ? $analysis['effect'] : 'total',
            'exposures' => $exposures,
            'outcomes' => $outcomes,
            'adjustment_sets' => $sets,
            'implications' => $implications,
        ], fn ($value) => $value !== null);
    }

    /**
     * DAGitty text as styled lines: the graph type a keyword, arrows
     * operators, quoted text strings, and attribute names constants.
     *
     * @return list<list<array{0: string, 1: string}>>
     */
    public static function dagittyLines(string $text): array
    {
        $out = new StyledText;
        $inAttributes = false;
        $pattern = '/("(?:[^"\\\\]|\\\\.)*"?)|(<->|->|<-|--)|(\[|\])|((?:#|\/\/)[^\n]*)|([^\s\[\]"{}=,;#<>-]+|[<>-])|(\s+|[{}=,;])/u';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        $first = true;
        foreach ($matches as $match) {
            $token = $match[0];
            $class = '';
            if (($match[1] ?? '') !== '') {
                $class = 'str';
            } elseif (($match[2] ?? '') !== '') {
                $class = 'op';
            } elseif (($match[3] ?? '') !== '') {
                $inAttributes = $token === '[';
            } elseif (($match[4] ?? '') !== '') {
                $class = 'com';
            } elseif (($match[5] ?? '') !== '') {
                if ($first && in_array(strtolower($token), ['dag', 'pdag', 'mag', 'pag'], true)) {
                    $class = 'kw';
                } elseif ($inAttributes) {
                    $class = 'const';
                }
                $first = false;
            }
            $out->append($token, $class);
        }

        return StyledText::trim($out->lines());
    }

    /** "Causal diagram of 3 variables and 3 arrows. Exposure: smoking. Outcome: cancer." */
    public static function alt(array $dag): string
    {
        $nodes = $dag['dag']['nodes'];
        $edges = $dag['dag']['edges'];
        $plural = fn (int $n, string $word) => $n.' '.$word.($n === 1 ? '' : 's');
        $text = 'Causal diagram of '.$plural(count($nodes), 'variable').' and '.$plural(count($edges), 'arrow').'.';
        foreach (['exposure' => 'Exposure', 'outcome' => 'Outcome'] as $role => $label) {
            $named = array_map(fn ($node) => $node['label'] ?? $node['name'], array_filter($nodes, fn ($node) => in_array($role, $node['roles'], true)));
            if ($named !== []) {
                $text .= ' '.$label.': '.implode(', ', $named).'.';
            }
        }

        return $text;
    }
}
