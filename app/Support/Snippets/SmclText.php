<?php

namespace App\Support\Snippets;

/**
 * Stata's SMCL output turned into styled lines, keeping Stata's layout:
 * box characters become line drawing, column and alignment directives become
 * spaces, and {res}, {err}, {inp}, {bf}, {it}, and {ul} become classes.
 * Links keep their text. A directive this class does not know keeps its
 * text, so nothing Stata printed is lost.
 */
class SmclText
{
    /** Line width when the snippet does not say, Stata's default linesize less the margin */
    public const DEFAULT_WIDTH = 79;

    /** Directives that set the text's color class until the next one */
    private const COLORS = [
        'txt' => '', 'text' => '', 'res' => 'res', 'result' => 'res', 'err' => 'err', 'error' => 'err',
        'inp' => 'inp', 'input' => 'inp', 'com' => 'inp', 'hi' => 'bold',
    ];

    /** {c …} names and the characters they draw */
    private const CHARACTERS = [
        '|' => '│', '-' => '─', '+' => '┼', 'TT' => '┬', 'BT' => '┴', 'LT' => '├', 'RT' => '┤',
        'TLC' => '┌', 'TRC' => '┐', 'BLC' => '└', 'BRC' => '┘', '-(' => '{', ')-' => '}',
        'S|' => '$', "'g" => '`', 'g' => '`', 'r' => "'", 'ss' => 'ß',
    ];

    /** Directives that print nothing */
    private const SILENT = [
        'smcl', 'sf', 'reset', 'p', 'p_end', 'pstd', 'psee', 'phang', 'phang2', 'phang3', 'pmore', 'pmore2', 'pin', 'pin2',
        'marker', 'title', 'synoptset', 'synopthdr', 'synoptline', 'p2colset', 'p2colreset', 'p2line', 'tab', 'asis', 's6hlp',
        'bind', 'cmd', 'ul off', 'ul on', 'bf', 'it', 'rm', 'vieweralsosee', 'viewerjumpto', 'viewerdialog',
    ];

    /** Directives whose argument is shown as a link would be, without the link */
    private const LINKS = [
        'help', 'helpb', 'manhelp', 'manhelpi', 'manlink', 'manlinki', 'mansection', 'browse', 'view', 'net', 'ado',
        'update', 'search', 'stata', 'dialog', 'back', 'clearmore', 'matacmd', 'newvar', 'var', 'varname', 'vars', 'varlist',
        'depvar', 'depvars', 'indepvars', 'opt', 'opth', 'cmdab', 'hilite', 'helpi',
    ];

    private StyledText $out;

    /** @var array{color: string, bold: bool, italic: bool, underline: bool} */
    private array $style = ['color' => '', 'bold' => false, 'italic' => false, 'underline' => false];

    private function __construct(private readonly int $width) {}

    /** @return list<list<array{0: string, 1: string}>> */
    public static function lines(string $smcl, ?int $width = null): array
    {
        $parser = new self($width ?? self::DEFAULT_WIDTH);
        $parser->out = new StyledText;
        $parser->render(str_replace(["\r\n", "\r"], "\n", $smcl));

        return $parser->out->lines();
    }

    /** Whether the output's first printed text is in {err}: Stata reporting an error. */
    public static function isError(string $smcl): bool
    {
        foreach (self::lines($smcl) as $line) {
            foreach ($line as [$classes, $text]) {
                if (trim($text) !== '') {
                    return in_array('err', explode(' ', $classes), true);
                }
            }
        }

        return false;
    }

    private function render(string $smcl): void
    {
        $length = strlen($smcl);
        $i = 0;
        while ($i < $length) {
            $open = strpos($smcl, '{', $i);
            if ($open === false) {
                $this->emit(substr($smcl, $i));

                return;
            }
            $this->emit(substr($smcl, $i, $open - $i));
            $close = self::closing($smcl, $open);
            if ($close === null) {
                $this->emit(substr($smcl, $open));

                return;
            }
            $this->directive(substr($smcl, $open + 1, $close - $open - 1));
            $i = $close + 1;
        }
    }

    /** The position of the brace that closes the one at $open, or null. */
    private static function closing(string $smcl, int $open): ?int
    {
        $depth = 0;
        $quoted = false;
        for ($i = $open, $n = strlen($smcl); $i < $n; $i++) {
            $char = $smcl[$i];
            if ($char === '"') {
                $quoted = ! $quoted;
            } elseif ($char === "\n" && $depth === 1) {
                // Directives never span lines; a lone brace is text
                return null;
            } elseif (! $quoted && $char === '{') {
                $depth++;
            } elseif (! $quoted && $char === '}' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function directive(string $body): void
    {
        [$head, $argument] = self::split($body);
        $head = trim($head);
        $name = strtolower(strtok($head, ' ') ?: '');
        $parameters = trim(substr($head, strlen($name)));

        if (array_key_exists($name, self::COLORS) && $parameters === '') {
            $argument === null ? $this->setColor($name) : $this->styled(fn () => $this->setColor($name), $argument);

            return;
        }
        if (in_array($name, ['bf', 'it', 'ul'], true)) {
            $key = ['bf' => 'bold', 'it' => 'italic', 'ul' => 'underline'][$name];
            if ($argument !== null) {
                $this->styled(fn () => $this->style[$key] = true, $argument);
            } elseif ($name !== 'ul' || $parameters === 'on') {
                $this->style[$key] = true;
            } else {
                $this->style[$key] = false;
            }

            return;
        }
        if ($name === 'sf' || $name === 'rm' || $name === 'reset') {
            [$this->style['bold'], $this->style['italic']] = [false, false];
            if ($name === 'reset') {
                $this->style = ['color' => '', 'bold' => false, 'italic' => false, 'underline' => false];
            }

            return;
        }
        match ($name) {
            'c', 'char' => $this->emit(self::character($parameters)),
            'hline' => $this->emit(str_repeat('─', $parameters === '' ? max(0, $this->width - $this->out->column()) : max(0, (int) $parameters))),
            '.-' => $this->emit(str_repeat('─', max(0, $this->width - $this->out->column()))),
            'col', 'column' => $this->emit(str_repeat(' ', max(0, (int) $parameters - 1 - $this->out->column()))),
            'space' => $this->emit(str_repeat(' ', max(0, (int) $parameters))),
            'break', 'newline' => $this->emit("\n"),
            'dup' => $argument !== null ? $this->emitRepeated(max(0, min(1000, (int) $parameters)), $argument) : null,
            'ralign', 'lalign', 'center', 'centre' => $this->aligned($name, (int) $parameters, $argument ?? ''),
            'right' => $this->aligned('ralign', $this->width - $this->out->column(), $argument ?? ''),
            default => $this->other($name, $parameters, $argument, $body),
        };
    }

    private function other(string $name, string $parameters, ?string $argument, string $body): void
    {
        if (in_array($name, self::LINKS, true)) {
            $this->render($argument ?? trim($parameters, '" '));

            return;
        }
        if (in_array($name, self::SILENT, true) || preg_match('/^p\d*$|^p2col|^synopt|^marker|^title|^dlgtab/', $name)) {
            if ($argument !== null) {
                $this->render($argument);
            }

            return;
        }
        // Not a directive this class knows: show what Stata would, the text with its braces
        $argument !== null && $parameters === '' && $name === ''
            ? $this->render($argument)
            : $this->emit('{'.$body.'}');
    }

    /** Head and argument: "res:12.3" → ["res", "12.3"]; the colon is the first outside quotes and braces. */
    private static function split(string $body): array
    {
        $quoted = false;
        $depth = 0;
        for ($i = 0, $n = strlen($body); $i < $n; $i++) {
            $char = $body[$i];
            if ($char === '"') {
                $quoted = ! $quoted;
            } elseif (! $quoted && $char === '{') {
                $depth++;
            } elseif (! $quoted && $char === '}') {
                $depth--;
            } elseif (! $quoted && $depth === 0 && $char === ':') {
                return [substr($body, 0, $i), substr($body, $i + 1)];
            }
        }

        return [$body, null];
    }

    private static function character(string $name): string
    {
        if (array_key_exists($name, self::CHARACTERS)) {
            return self::CHARACTERS[$name];
        }
        if (preg_match('/^(?:0x([0-9a-fA-F]{1,4})|([0-9]{1,5}))$/', $name, $m)) {
            $code = $m[1] !== '' ? hexdec($m[1]) : (int) $m[2];

            return $code >= 32 && $code !== 127 ? mb_chr($code, 'UTF-8') : '';
        }

        return '{c '.$name.'}';
    }

    private function setColor(string $name): void
    {
        $this->style['color'] = self::COLORS[$name];
    }

    /** Render an argument in a changed style, then go back to the style before it. */
    private function styled(callable $change, string $argument): void
    {
        $saved = $this->style;
        $change();
        $this->render($argument);
        $this->style = $saved;
    }

    private function aligned(string $name, int $width, string $argument): void
    {
        $inner = new self($this->width);
        $inner->out = new StyledText;
        $inner->style = $this->style;
        $inner->render($argument);
        $segments = $inner->out->lines()[0] ?? [];
        $pad = max(0, $width - mb_strlen(StyledText::lineText($segments)));
        [$left, $right] = match ($name) {
            'ralign' => [$pad, 0],
            'lalign' => [0, $pad],
            default => [intdiv($pad, 2), $pad - intdiv($pad, 2)],
        };
        $this->emit(str_repeat(' ', $left));
        foreach ($segments as [$classes, $text]) {
            $this->out->append($text, $classes);
        }
        $this->emit(str_repeat(' ', $right));
    }

    private function emitRepeated(int $count, string $argument): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->render($argument);
        }
    }

    private function emit(string $text): void
    {
        $this->out->append($text, $this->classes());
    }

    private function classes(): string
    {
        return implode(' ', array_unique(array_filter([
            $this->style['color'],
            $this->style['bold'] ? 'bold' : null,
            $this->style['italic'] ? 'italic' : null,
            $this->style['underline'] ? 'underline' : null,
        ])));
    }
}
