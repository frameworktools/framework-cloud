<?php

namespace App\Support\Dags;

use InvalidArgumentException;

/**
 * DAGitty model text as nodes and edges. Reads what R's dagitty and
 * dagitty.net write: an optional `dag` header, node statements with
 * attributes, and edges chained (a -> b -> c) or grouped ({a b} -> c).
 * Only directed acyclic graphs are accepted.
 */
class DagittyParser
{
    public const MAX_NODES = 100;

    public const MAX_EDGES = 400;

    public const MAX_BYTES = 65_536;

    public const ROLES = ['exposure', 'outcome', 'latent', 'adjusted', 'selected'];

    private const MAX_NAME = 100;

    private const GRAPH_TYPES = ['dag', 'pdag', 'mag', 'pag'];

    /** @var list<array{0: string, 1: string, 2: int}> type, value, line */
    private array $tokens = [];

    private int $at = 0;

    /** @var array<string, array{name: string, roles: list<string>, pos: array{0: float, 1: float}|null}> */
    private array $nodes = [];

    /** @var array<string, array{from: string, to: string, type: string}> */
    private array $edges = [];

    /**
     * @return array{nodes: list<array{name: string, roles: list<string>, pos: array{0: float, 1: float}|null}>, edges: list<array{from: string, to: string, type: string}>}
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $text): array
    {
        if (strlen($text) > self::MAX_BYTES) {
            throw new InvalidArgumentException('DAGitty code can be at most 64 KB.');
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('DAGitty code must be UTF-8 text.');
        }
        $parser = new self;
        $parser->tokens = self::tokenize($text);

        return $parser->graph();
    }

    /** @return list<array{0: string, 1: string, 2: int}> */
    private static function tokenize(string $text): array
    {
        $tokens = [];
        $line = 1;
        $length = strlen($text);
        $i = 0;
        while ($i < $length) {
            $char = $text[$i];
            if ($char === "\n") {
                $line++;
                $i++;

                continue;
            }
            if (ctype_space($char) || $char === ';') {
                $i++;

                continue;
            }
            // Comments are not DAGitty syntax, but people write them; skip # and // to the end of the line
            if ($char === '#' || ($char === '/' && ($text[$i + 1] ?? '') === '/')) {
                while ($i < $length && $text[$i] !== "\n") {
                    $i++;
                }

                continue;
            }
            foreach (['<->', '->', '<-', '--'] as $operator) {
                if (substr($text, $i, strlen($operator)) === $operator) {
                    $tokens[] = ['edge', $operator, $line];
                    $i += strlen($operator);

                    continue 2;
                }
            }
            if (str_contains('{}[],=', $char)) {
                $tokens[] = ['punct', $char, $line];
                $i++;

                continue;
            }
            if ($char === '"') {
                $value = '';
                $start = $line;
                $i++;
                while ($i < $length && $text[$i] !== '"') {
                    if ($text[$i] === '\\' && $i + 1 < $length) {
                        $i++;
                    }
                    if ($text[$i] === "\n") {
                        $line++;
                    }
                    $value .= $text[$i];
                    $i++;
                }
                if ($i >= $length) {
                    throw new InvalidArgumentException("DAGitty code, line {$start}: a quoted name is never closed.");
                }
                $i++;
                $tokens[] = ['string', $value, $start];

                continue;
            }
            $value = '';
            while ($i < $length && ! ctype_space($text[$i]) && ! str_contains('{}[],=";#', $text[$i])
                && ! in_array(substr($text, $i, 2), ['->', '<-', '--'], true)) {
                $value .= $text[$i];
                $i++;
            }
            if ($value === '') {
                throw new InvalidArgumentException("DAGitty code, line {$line}: unexpected \"{$char}\".");
            }
            $tokens[] = ['word', $value, $line];
        }

        return $tokens;
    }

    private function graph(): array
    {
        $first = $this->peek();
        if ($first !== null && $first[0] === 'word' && in_array(strtolower($first[1]), self::GRAPH_TYPES, true)
            && $this->isOpenBrace($this->peek(1), $this->peek(2))) {
            $type = strtolower($first[1]);
            if ($type !== 'dag') {
                throw new InvalidArgumentException("This is a {$type} graph. Framework draws dag graphs only.");
            }
            $this->at++;
            // An optional graph name before the brace
            if ($this->peek()[0] !== 'punct') {
                $this->at++;
            }
            $this->expect('{');
            $this->statements('}');
            $this->expect('}');
            if ($this->peek() !== null) {
                $this->fail($this->peek(), 'unexpected text after the closing brace.');
            }
        } else {
            $this->statements(null);
        }
        if ($this->nodes === []) {
            throw new InvalidArgumentException('The DAGitty code names no variables.');
        }
        $this->checkAcyclic();

        return ['nodes' => array_values($this->nodes), 'edges' => array_values($this->edges)];
    }

    private function isOpenBrace(?array $next, ?array $after): bool
    {
        return ($next !== null && $next[0] === 'punct' && $next[1] === '{')
            || ($next !== null && in_array($next[0], ['word', 'string'], true) && $after !== null && $after[0] === 'punct' && $after[1] === '{');
    }

    private function statements(?string $end): void
    {
        while (($token = $this->peek()) !== null) {
            if ($end !== null && $token[0] === 'punct' && $token[1] === $end) {
                return;
            }
            $this->statement();
        }
        if ($end !== null) {
            throw new InvalidArgumentException('DAGitty code: the graph\'s braces are never closed.');
        }
    }

    private function statement(): void
    {
        // A graph attribute: bb="0,0,1,1"
        $token = $this->peek();
        $next = $this->peek(1);
        if ($token[0] === 'word' && $next !== null && $next[0] === 'punct' && $next[1] === '=') {
            $this->at += 2;
            $this->value();

            return;
        }
        $left = $this->operand();
        $attributes = $this->attributes();
        if ($attributes !== null) {
            foreach ($left as $name) {
                $this->apply($name, $attributes, $token);
            }
        }
        while (($operator = $this->peek()) !== null && $operator[0] === 'edge') {
            $this->at++;
            if ($operator[1] === '--') {
                $this->fail($operator, 'undirected edges (--) belong to other graph types. A DAG uses -> and <->.');
            }
            $right = $this->operand();
            foreach ($left as $a) {
                foreach ($right as $b) {
                    $this->edge($a, $b, $operator);
                }
            }
            // Edge attributes, such as a control point or a path coefficient, are read and ignored
            $this->attributes();
            $left = $right;
        }
    }

    /** @return list<string> */
    private function operand(): array
    {
        $token = $this->peek();
        if ($token === null) {
            throw new InvalidArgumentException('DAGitty code: a variable name is missing at the end.');
        }
        if ($token[0] === 'punct' && $token[1] === '{') {
            $this->at++;
            $names = [];
            while (($inner = $this->peek()) !== null && ! ($inner[0] === 'punct' && $inner[1] === '}')) {
                $names[] = $this->name();
                $attributes = $this->attributes();
                if ($attributes !== null) {
                    $this->apply(end($names), $attributes, $inner);
                }
            }
            $this->expect('}');
            if ($names === []) {
                $this->fail($token, 'an empty group {} names no variables.');
            }

            return $names;
        }

        return [$this->name()];
    }

    private function name(): string
    {
        $token = $this->peek();
        if ($token === null || ! in_array($token[0], ['word', 'string'], true)) {
            $this->fail($token, 'expected a variable name'.($token ? ', found "'.$token[1].'".' : '.'));
        }
        $this->at++;
        $name = trim($token[1]);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $this->fail($token, 'variable names are 1 to '.self::MAX_NAME.' characters without control characters.');
        }
        if (! isset($this->nodes[$name])) {
            if (count($this->nodes) >= self::MAX_NODES) {
                throw new InvalidArgumentException('A DAG can have at most '.self::MAX_NODES.' variables.');
            }
            $this->nodes[$name] = ['name' => $name, 'roles' => [], 'pos' => null];
        }

        return $name;
    }

    /** @return array<string, string|true>|null */
    private function attributes(): ?array
    {
        $token = $this->peek();
        if ($token === null || $token[0] !== 'punct' || $token[1] !== '[') {
            return null;
        }
        $this->at++;
        $attributes = [];
        while (($token = $this->peek()) !== null && ! ($token[0] === 'punct' && $token[1] === ']')) {
            if ($token[0] === 'punct' && $token[1] === ',') {
                $this->at++;

                continue;
            }
            if (! in_array($token[0], ['word', 'string'], true)) {
                $this->fail($token, 'expected an attribute, found "'.$token[1].'".');
            }
            $this->at++;
            $key = strtolower($token[1]);
            $next = $this->peek();
            if ($next !== null && $next[0] === 'punct' && $next[1] === '=') {
                $this->at++;
                $attributes[$key] = $this->value();
            } else {
                $attributes[$key] = true;
            }
        }
        $this->expect(']');

        return $attributes;
    }

    private function value(): string
    {
        $token = $this->peek();
        if ($token === null || ! in_array($token[0], ['word', 'string'], true)) {
            $this->fail($token, 'expected a value after =.');
        }
        $this->at++;

        return $token[1];
    }

    private function apply(string $name, array $attributes, array $token): void
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, self::ROLES, true) && $value === true) {
                if (! in_array($key, $this->nodes[$name]['roles'], true)) {
                    $this->nodes[$name]['roles'][] = $key;
                }
            } elseif ($key === 'pos' && is_string($value)) {
                $parts = array_map('trim', explode(',', $value));
                if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])
                    || ! is_finite((float) $parts[0]) || ! is_finite((float) $parts[1])) {
                    $this->fail($token, "pos for {$name} must be two numbers, such as pos=\"1,0\".");
                }
                $this->nodes[$name]['pos'] = [(float) $parts[0], (float) $parts[1]];
            }
        }
        // Keep roles in one order, so the same graph normalizes the same way
        $this->nodes[$name]['roles'] = array_values(array_intersect(self::ROLES, $this->nodes[$name]['roles']));
    }

    private function edge(string $a, string $b, array $operator): void
    {
        [$from, $to, $type] = match ($operator[1]) {
            '<-' => [$b, $a, '->'],
            '<->' => strcmp($a, $b) <= 0 ? [$a, $b, '<->'] : [$b, $a, '<->'],
            default => [$a, $b, '->'],
        };
        if ($from === $to) {
            $this->fail($operator, "{$from} points to itself.");
        }
        $key = $from."\0".$to."\0".$type;
        if (isset($this->edges[$key])) {
            return;
        }
        if (count($this->edges) >= self::MAX_EDGES) {
            throw new InvalidArgumentException('A DAG can have at most '.self::MAX_EDGES.' edges.');
        }
        $this->edges[$key] = ['from' => $from, 'to' => $to, 'type' => $type];
    }

    private function checkAcyclic(): void
    {
        $children = [];
        foreach ($this->edges as $edge) {
            if ($edge['type'] === '->') {
                $children[$edge['from']][] = $edge['to'];
            }
        }
        $state = [];
        $path = [];
        $visit = function (string $node) use (&$visit, &$state, &$path, $children): void {
            $state[$node] = 1;
            $path[] = $node;
            foreach ($children[$node] ?? [] as $child) {
                if (($state[$child] ?? 0) === 1) {
                    $cycle = array_slice($path, array_search($child, $path, true));
                    $cycle[] = $child;
                    throw new InvalidArgumentException('The graph has a cycle: '.implode(' -> ', $cycle).'. A DAG has none.');
                }
                if (($state[$child] ?? 0) === 0) {
                    $visit($child);
                }
            }
            array_pop($path);
            $state[$node] = 2;
        };
        foreach (array_keys($this->nodes) as $node) {
            if (($state[$node] ?? 0) === 0) {
                $visit((string) $node);
            }
        }
    }

    private function peek(int $ahead = 0): ?array
    {
        return $this->tokens[$this->at + $ahead] ?? null;
    }

    private function expect(string $punct): void
    {
        $token = $this->peek();
        if ($token === null || $token[0] !== 'punct' || $token[1] !== $punct) {
            $this->fail($token, "expected \"{$punct}\"".($token ? ', found "'.$token[1].'".' : '.'));
        }
        $this->at++;
    }

    private function fail(?array $token, string $message): never
    {
        throw new InvalidArgumentException('DAGitty code'.($token ? ', line '.$token[2] : '').': '.$message);
    }
}
