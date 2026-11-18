<?php

namespace App\Support\Snippets;

/**
 * Text as lines of styled segments, the shape every code snippet block
 * shares: a list of lines, each a list of [classes, text] pairs. Classes are
 * a space-separated string, empty for plain text. Neighboring segments with
 * the same classes are merged, and empty segments are dropped.
 */
class StyledText
{
    /** @var list<list<array{0: string, 1: string}>> */
    private array $lines = [[]];

    public function append(string $text, string $classes = ''): void
    {
        if ($text === '') {
            return;
        }
        $parts = explode("\n", $text);
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $this->lines[] = [];
            }
            if ($part === '') {
                continue;
            }
            $line = &$this->lines[count($this->lines) - 1];
            $last = count($line) - 1;
            if ($last >= 0 && $line[$last][0] === $classes) {
                $line[$last][1] .= $part;
            } else {
                $line[] = [$classes, $part];
            }
            unset($line);
        }
    }

    /** Characters on the current line, for column directives. */
    public function column(): int
    {
        $width = 0;
        foreach ($this->lines[count($this->lines) - 1] as [, $text]) {
            $width += mb_strlen($text);
        }

        return $width;
    }

    /** Drop what the current line holds, as a carriage return overwrites it. */
    public function clearLine(): void
    {
        $this->lines[count($this->lines) - 1] = [];
    }

    /** @return list<list<array{0: string, 1: string}>> */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Lines with leading and trailing blank lines removed.
     *
     * @param  list<list<array{0: string, 1: string}>>  $lines
     * @return list<list<array{0: string, 1: string}>>
     */
    public static function trim(array $lines): array
    {
        while ($lines !== [] && trim(self::lineText(end($lines))) === '') {
            array_pop($lines);
        }
        while ($lines !== [] && trim(self::lineText($lines[0])) === '') {
            array_shift($lines);
        }

        return $lines;
    }

    /** @param  list<array{0: string, 1: string}>  $line */
    public static function lineText(array $line): string
    {
        return implode('', array_column($line, 1));
    }

    /** @param  list<list<array{0: string, 1: string}>>  $lines */
    public static function text(array $lines): string
    {
        return implode("\n", array_map(self::lineText(...), $lines));
    }

    /**
     * Escaped HTML for <pre>: one span per styled segment, each class prefixed
     * ("res bold" becomes class="t-res t-bold").
     *
     * @param  list<list<array{0: string, 1: string}>>  $lines
     */
    public static function html(array $lines): string
    {
        return implode("\n", array_map(fn (array $line) => implode('', array_map(function (array $segment) {
            $text = htmlspecialchars($segment[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($segment[0] === '') {
                return $text;
            }
            $classes = implode(' ', array_map(fn ($class) => 't-'.preg_replace('/[^a-z0-9-]/', '', $class), explode(' ', $segment[0])));

            return '<span class="'.$classes.'">'.$text.'</span>';
        }, $line)), $lines));
    }
}
