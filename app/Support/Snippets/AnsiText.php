<?php

namespace App\Support\Snippets;

/**
 * Console text with ANSI escape codes, as R and Python print it, turned into
 * styled lines. Bold, faint, italic, underline, and the eight foreground
 * colors become classes; bright colors map to the plain ones. Every other
 * escape sequence is dropped, and its text kept. A carriage return starts
 * the line over, as a progress bar redraws itself.
 */
class AnsiText
{
    private const COLORS = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'];

    /** CSI sequences, OSC sequences (hyperlinks, titles), and two-character escapes */
    private const ESCAPES = '/\e\[([0-9;:?]*)([ -\/]*)([@-~])|\e\][^\x07\e]*(?:\x07|\e\\\\)?|\e[@-Z\\\\-_]?/';

    /** @return list<list<array{0: string, 1: string}>> */
    public static function lines(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $out = new StyledText;
        $style = ['bold' => false, 'faint' => false, 'italic' => false, 'underline' => false, 'color' => null];
        $offset = 0;
        preg_match_all(self::ESCAPES, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            self::plain($out, substr($text, $offset, $match[0][1] - $offset), self::classes($style));
            $offset = $match[0][1] + strlen($match[0][0]);
            // Only SGR (ending in m, without intermediates) changes the style
            if (($match[3][0] ?? null) === 'm' && ($match[2][0] ?? '') === '') {
                $style = self::apply($style, $match[1][0]);
            }
        }
        self::plain($out, substr($text, $offset), self::classes($style));

        return $out->lines();
    }

    /** Text between escapes: carriage returns restart the line, other controls go. */
    private static function plain(StyledText $out, string $text, string $classes): void
    {
        $pieces = explode("\r", $text);
        foreach ($pieces as $index => $piece) {
            if ($index > 0) {
                $out->clearLine();
            }
            $out->append(preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $piece) ?? '', $classes);
        }
    }

    private static function apply(array $style, string $parameters): array
    {
        $codes = $parameters === '' ? [0] : array_map('intval', preg_split('/[;:]/', $parameters));
        for ($i = 0; $i < count($codes); $i++) {
            $code = $codes[$i];
            match (true) {
                $code === 0 => $style = ['bold' => false, 'faint' => false, 'italic' => false, 'underline' => false, 'color' => null],
                $code === 1 => $style['bold'] = true,
                $code === 2 => $style['faint'] = true,
                $code === 3 => $style['italic'] = true,
                $code === 4 => $style['underline'] = true,
                $code === 22 => [$style['bold'], $style['faint']] = [false, false],
                $code === 23 => $style['italic'] = false,
                $code === 24 => $style['underline'] = false,
                $code >= 30 && $code <= 37 => $style['color'] = self::COLORS[$code - 30],
                $code >= 90 && $code <= 97 => $style['color'] = self::COLORS[$code - 90],
                $code === 39 => $style['color'] = null,
                default => null,
            };
            // 256-color and true-color forms carry their own arguments, which are skipped
            if (in_array($code, [38, 48, 58], true)) {
                $i += ($codes[$i + 1] ?? null) === 5 ? 2 : (($codes[$i + 1] ?? null) === 2 ? 4 : 0);
            }
        }

        return $style;
    }

    private static function classes(array $style): string
    {
        return implode(' ', array_filter([
            $style['bold'] ? 'bold' : null,
            $style['faint'] ? 'faint' : null,
            $style['italic'] ? 'italic' : null,
            $style['underline'] ? 'underline' : null,
            $style['color'] ? 'ansi-'.$style['color'] : null,
        ]));
    }
}
