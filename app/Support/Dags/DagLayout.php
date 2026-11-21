<?php

namespace App\Support\Dags;

/**
 * Where each node and edge of a DAG is drawn, in pixels. Computed once, so
 * the reader and every embed renderer draw the same picture from data alone.
 *
 * Node boxes fit their label in a monospace font at 14px. Positions come
 * from DAGitty's pos attributes when every node has one (y points down),
 * else from a layered left-to-right layout. Edges are SVG path strings that
 * run between box edges, bending around boxes they would cross.
 */
class DagLayout
{
    public const FONT_SIZE = 14;

    public const LABEL_CHARS = 32;

    private const CHAR_WIDTH = 8.4;

    private const PAD_X = 12;

    private const NODE_HEIGHT = 30;

    private const MIN_WIDTH = 36;

    private const MARGIN = 8;

    /** Space between columns and rows in the layered layout */
    private const COLUMN_GAP = 56;

    private const ROW_STEP = 64;

    /** Least space between boxes placed from pos attributes */
    private const GAP_X = 40;

    private const GAP_Y = 24;

    /** Bumped when a layout change should redraw published DAGs */
    public const VERSION = 2;

    private const HEAD_LENGTH = 9;

    private const HEAD_HALF_WIDTH = 4.5;

    /** Space between an edge's end and the box it meets */
    private const END_GAP = 2;

    /** Bends tried for an edge, as a share of its length, in order */
    private const BENDS = [0.2, -0.2, 0.35, -0.35, 0.5, -0.5, 0.7, -0.7];

    /**
     * @param  list<array{name: string, label: string, roles: list<string>, pos: array{0: float, 1: float}|null}>  $nodes
     * @param  list<array{from: string, to: string, type: string}>  $edges
     * @return array{width: int, height: int, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function layout(array $nodes, array $edges): array
    {
        $sizes = [];
        foreach ($nodes as $node) {
            $sizes[$node['name']] = [self::boxWidth(self::shownLabel($node['label'])), self::NODE_HEIGHT];
        }
        $centers = self::fromPositions($nodes, $sizes) ?? self::layered($nodes, $edges, $sizes);

        // Shift so the drawing starts at the margin, in whole pixels
        $minX = INF;
        $minY = INF;
        foreach ($centers as $name => [$x, $y]) {
            $minX = min($minX, $x - $sizes[$name][0] / 2);
            $minY = min($minY, $y - $sizes[$name][1] / 2);
        }
        $boxes = [];
        $width = 0;
        $height = 0;
        foreach ($centers as $name => [$x, $y]) {
            [$w, $h] = $sizes[$name];
            $left = (int) round($x - $w / 2 - $minX) + self::MARGIN;
            $top = (int) round($y - $h / 2 - $minY) + self::MARGIN;
            $boxes[$name] = [$left, $top, $w, $h];
            $width = max($width, $left + $w + self::MARGIN);
            $height = max($height, $top + $h + self::MARGIN);
        }

        $geometry = [];
        $pairs = [];
        foreach ($edges as $edge) {
            $pair = strcmp($edge['from'], $edge['to']) <= 0 ? $edge['from']."\0".$edge['to'] : $edge['to']."\0".$edge['from'];
            $drawnEdge = self::edge($edge, $boxes, $pairs[$pair] ?? null);
            // The bend taken, read in the pair's own direction, so the next edge between them bends the other way
            $pairs[$pair] = ($drawnEdge['bend'] ?? 0.0) * (strcmp($edge['from'], $edge['to']) <= 0 ? 1 : -1);
            $geometry[] = $drawnEdge;
        }

        // A bent edge may reach past the boxes: grow the drawing to hold it
        $low = [0.0, 0.0];
        $high = [(float) $width, (float) $height];
        foreach ($geometry as $edge) {
            foreach (self::extent($edge) as [$x, $y]) {
                $low = [min($low[0], $x - self::MARGIN), min($low[1], $y - self::MARGIN)];
                $high = [max($high[0], $x + self::MARGIN), max($high[1], $y + self::MARGIN)];
            }
        }
        $shift = [(int) ceil(-$low[0]), (int) ceil(-$low[1])];
        $width = (int) ceil($high[0]) + $shift[0];
        $height = (int) ceil($high[1]) + $shift[1];

        $placed = [];
        foreach ($nodes as $node) {
            [$x, $y, $w, $h] = $boxes[$node['name']];
            $placed[] = ['name' => $node['name'], 'x' => $x + $shift[0], 'y' => $y + $shift[1], 'width' => $w, 'height' => $h];
        }
        $drawn = [];
        foreach ($geometry as $edge) {
            $drawn[] = ['from' => $edge['from'], 'to' => $edge['to'], 'type' => $edge['type']] + self::paths($edge, $shift);
        }

        return ['width' => $width, 'height' => $height, 'nodes' => $placed, 'edges' => $drawn];
    }

    /** Points along an edge and its heads, for the drawing's bounds. */
    private static function extent(array $edge): array
    {
        [$start, $control, $end] = $edge['points'];
        $points = array_merge([$start, $end], ...$edge['heads']);
        if ($control !== null) {
            for ($step = 1; $step < 16; $step++) {
                $points[] = self::along($start, $control, $end, $step / 16);
            }
        }

        return $points;
    }

    /** @return array{path: string, heads: list<string>} */
    private static function paths(array $edge, array $shift): array
    {
        $point = fn (array $p) => self::number($p[0] + $shift[0]).' '.self::number($p[1] + $shift[1]);
        [$start, $control, $end] = $edge['points'];

        return [
            'path' => 'M '.$point($start).($control === null ? ' L '.$point($end) : ' Q '.$point($control).' '.$point($end)),
            'heads' => array_map(fn (array $head) => 'M '.implode(' L ', array_map($point, $head)).' Z', $edge['heads']),
        ];
    }

    private static function along(array $start, array $control, array $end, float $t): array
    {
        $u = 1 - $t;

        return [
            $u * $u * $start[0] + 2 * $u * $t * $control[0] + $t * $t * $end[0],
            $u * $u * $start[1] + 2 * $u * $t * $control[1] + $t * $t * $end[1],
        ];
    }

    /** The label as drawn: at most LABEL_CHARS characters, cut with an ellipsis. */
    public static function shownLabel(string $label): string
    {
        return mb_strlen($label) > self::LABEL_CHARS ? rtrim(mb_substr($label, 0, self::LABEL_CHARS - 1), ' ').'…' : $label;
    }

    /** An even width, so the label's center falls on a whole pixel. */
    private static function boxWidth(string $label): int
    {
        $width = (int) ceil(mb_strlen($label) * self::CHAR_WIDTH + 2 * self::PAD_X);
        $width += $width % 2;

        return max(self::MIN_WIDTH, $width);
    }

    /**
     * Centers from pos attributes, scaled until no two boxes overlap, or null
     * when a node has no position or two share one.
     *
     * @return array<string, array{0: float, 1: float}>|null
     */
    private static function fromPositions(array $nodes, array $sizes): ?array
    {
        foreach ($nodes as $node) {
            if ($node['pos'] === null) {
                return null;
            }
        }
        // Each axis scales on its own, so a tight row doesn't stretch the drawing's height.
        // Rows: the closest two sit ROW_STEP apart, center to center.
        $count = count($nodes);
        $pairs = [];
        $minDy = INF;
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                [$a, $b] = [$nodes[$i], $nodes[$j]];
                $dx = abs($a['pos'][0] - $b['pos'][0]);
                $dy = abs($a['pos'][1] - $b['pos'][1]);
                if ($dx < 1e-9 && $dy < 1e-9) {
                    return null;
                }
                if ($dy > 1e-9) {
                    $minDy = min($minDy, $dy);
                }
                $pairs[] = [$a['name'], $b['name'], $dx, $dy];
            }
        }
        $sy = is_finite($minDy) ? self::ROW_STEP / $minDy : 1.0;
        // Boxes stacked in one column must clear each other vertically
        foreach ($pairs as [$a, $b, $dx, $dy]) {
            if ($dx < 1e-9) {
                $sy = max($sy, (($sizes[$a][1] + $sizes[$b][1]) / 2 + self::GAP_Y) / $dy);
            }
        }
        // Columns: boxes that share a row, or nearly, must clear each other horizontally
        $sx = 0.0;
        foreach ($pairs as [$a, $b, $dx, $dy]) {
            $rowsApart = $dy * $sy >= ($sizes[$a][1] + $sizes[$b][1]) / 2 + self::GAP_Y;
            if (! $rowsApart && $dx > 1e-9) {
                $sx = max($sx, (($sizes[$a][0] + $sizes[$b][0]) / 2 + self::GAP_X) / $dx);
            }
        }
        // With no shared rows, keep the drawing's proportions, and never squash it far below them
        $sx = max($sx, $sy / 2);
        $centers = [];
        foreach ($nodes as $node) {
            $centers[$node['name']] = [$node['pos'][0] * $sx, $node['pos'][1] * $sy];
        }

        return $centers;
    }

    /**
     * Columns by longest directed path from a source, rows by barycenter.
     *
     * @return array<string, array{0: float, 1: float}>
     */
    private static function layered(array $nodes, array $edges, array $sizes): array
    {
        $names = array_column($nodes, 'name');
        $parents = [];
        $neighbors = [];
        foreach ($edges as $edge) {
            if ($edge['type'] === '->') {
                $parents[$edge['to']][] = $edge['from'];
            }
            $neighbors[$edge['from']][] = $edge['to'];
            $neighbors[$edge['to']][] = $edge['from'];
        }
        $column = [];
        $depth = function (string $name) use (&$depth, &$column, $parents): int {
            if (! isset($column[$name])) {
                $column[$name] = 0;
                foreach ($parents[$name] ?? [] as $parent) {
                    $column[$name] = max($column[$name], $depth($parent) + 1);
                }
            }

            return $column[$name];
        };
        $columns = [];
        foreach ($names as $name) {
            $columns[$depth($name)][] = $name;
        }
        ksort($columns);

        // Sweep right, then left, placing each node at its neighbors' mean row
        $row = [];
        foreach ($columns as $members) {
            foreach ($members as $index => $name) {
                $row[$name] = $index - (count($members) - 1) / 2;
            }
        }
        $keys = array_keys($columns);
        for ($sweep = 0; $sweep < 8; $sweep++) {
            $order = $sweep % 2 === 0 ? $keys : array_reverse($keys);
            foreach ($order as $c) {
                $members = $columns[$c];
                $score = [];
                foreach ($members as $index => $name) {
                    $around = array_map(fn ($n) => $row[$n], $neighbors[$name] ?? []);
                    $score[$name] = [$around === [] ? $row[$name] : array_sum($around) / count($around), $index];
                }
                usort($members, fn ($a, $b) => $score[$a] <=> $score[$b]);
                $columns[$c] = $members;
                foreach ($members as $index => $name) {
                    $row[$name] = $index - (count($members) - 1) / 2;
                }
            }
        }

        $centers = [];
        $x = 0.0;
        $previous = null;
        foreach ($columns as $members) {
            $widest = max(array_map(fn ($name) => $sizes[$name][0], $members));
            $x += $previous === null ? $widest / 2 : $previous / 2 + self::COLUMN_GAP + $widest / 2;
            foreach ($members as $name) {
                $centers[$name] = [$x, $row[$name] * self::ROW_STEP];
            }
            $previous = $widest;
        }
        // Keep the nodes' own order
        $ordered = [];
        foreach ($names as $name) {
            $ordered[$name] = $centers[$name];
        }

        return $ordered;
    }

    /**
     * @param  array<string, array{0: int, 1: int, 2: int, 3: int}>  $boxes
     * @param  float|null  $previous  The bend of an earlier edge between the same nodes, in their name order
     */
    private static function edge(array $edge, array $boxes, ?float $previous): array
    {
        $from = $boxes[$edge['from']];
        $to = $boxes[$edge['to']];
        $start = self::center($from);
        $end = self::center($to);
        $others = array_diff_key($boxes, [$edge['from'] => true, $edge['to'] => true]);
        $bidirected = $edge['type'] === '<->';

        $bends = self::BENDS;
        if ($previous !== null) {
            // A second edge between the same two nodes bends away from the first
            $previous *= strcmp($edge['from'], $edge['to']) <= 0 ? 1 : -1;
            $away = $previous > 0 ? -1 : 1;
            $bends = array_merge(
                array_values(array_filter(self::BENDS, fn ($b) => $b * $away > 0)),
                array_values(array_filter(self::BENDS, fn ($b) => $b * $away < 0 && abs($b) > abs($previous) + 0.1)),
            );
        }
        $bend = null;
        if ($bidirected || $previous !== null || ! self::clear($start, null, $end, $others)) {
            $bend = $bends[0];
            foreach ($bends as $candidate) {
                if (self::clear($start, self::control($start, $end, $candidate), $end, $others)) {
                    $bend = $candidate;
                    break;
                }
            }
        }
        $control = $bend === null ? null : self::control($start, $end, $bend);
        // Each end leaves its box toward the control point, or the other box
        $a = self::exit($from, $control ?? $end);
        $b = self::exit($to, $control ?? $start);
        $heads = [self::head($b, $control ?? $a)];
        $b = self::back($b, $control ?? $a, self::HEAD_LENGTH - 1);
        if ($bidirected) {
            $heads[] = self::head($a, $control ?? $b);
            $a = self::back($a, $control ?? $b, self::HEAD_LENGTH - 1);
        }

        return ['from' => $edge['from'], 'to' => $edge['to'], 'type' => $edge['type'], 'points' => [$a, $control, $b], 'heads' => $heads, 'bend' => $bend];
    }

    /** @return array{0: float, 1: float} */
    private static function center(array $box): array
    {
        return [(float) $box[0] + $box[2] / 2, (float) $box[1] + $box[3] / 2];
    }

    /** The control point of a curve bent by a share of the edge's length, to its left. */
    private static function control(array $start, array $end, float $bend): array
    {
        [$dx, $dy] = [$end[0] - $start[0], $end[1] - $start[1]];

        return [($start[0] + $end[0]) / 2 - $dy * $bend, ($start[1] + $end[1]) / 2 + $dx * $bend];
    }

    /** Whether a line or curve stays out of every other box. */
    private static function clear(array $start, ?array $control, array $end, array $others): bool
    {
        $control ??= [($start[0] + $end[0]) / 2, ($start[1] + $end[1]) / 2];
        for ($step = 1; $step < 32; $step++) {
            [$x, $y] = self::along($start, $control, $end, $step / 32);
            foreach ($others as [$left, $top, $w, $h]) {
                if ($x > $left - 4 && $x < $left + $w + 4 && $y > $top - 4 && $y < $top + $h + 4) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Where a line from the box's center toward a point leaves the box, plus a small gap. */
    private static function exit(array $box, array $toward): array
    {
        $center = self::center($box);
        [$dx, $dy] = [$toward[0] - $center[0], $toward[1] - $center[1]];
        $length = sqrt($dx * $dx + $dy * $dy);
        if ($length < 1e-9) {
            return $center;
        }
        $t = min(abs($dx) > 1e-9 ? ($box[2] / 2) / abs($dx) : INF, abs($dy) > 1e-9 ? ($box[3] / 2) / abs($dy) : INF);

        return [$center[0] + $dx * $t + $dx / $length * self::END_GAP, $center[1] + $dy * $t + $dy / $length * self::END_GAP];
    }

    /** A point moved back along the line from another point. */
    private static function back(array $point, array $from, float $distance): array
    {
        [$dx, $dy] = [$point[0] - $from[0], $point[1] - $from[1]];
        $length = sqrt($dx * $dx + $dy * $dy);
        if ($length < 1e-9) {
            return $point;
        }
        $distance = min($distance, $length / 2);

        return [$point[0] - $dx / $length * $distance, $point[1] - $dy / $length * $distance];
    }

    /** An arrowhead's three corners: its tip at $tip, pointing away from $from. */
    private static function head(array $tip, array $from): array
    {
        [$dx, $dy] = [$tip[0] - $from[0], $tip[1] - $from[1]];
        $length = sqrt($dx * $dx + $dy * $dy) ?: 1.0;
        [$ux, $uy] = [$dx / $length, $dy / $length];
        $baseX = $tip[0] - $ux * self::HEAD_LENGTH;
        $baseY = $tip[1] - $uy * self::HEAD_LENGTH;

        return [
            [$tip[0], $tip[1]],
            [$baseX - $uy * self::HEAD_HALF_WIDTH, $baseY + $ux * self::HEAD_HALF_WIDTH],
            [$baseX + $uy * self::HEAD_HALF_WIDTH, $baseY - $ux * self::HEAD_HALF_WIDTH],
        ];
    }

    /** One decimal at most, without a trailing .0 or a negative zero. */
    private static function number(float $value): string
    {
        $text = sprintf('%.1F', round($value, 1));
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $text === '-0' ? '0' : $text;
    }
}
