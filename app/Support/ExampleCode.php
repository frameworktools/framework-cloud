<?php

namespace App\Support;

/** Escaped syntax highlighting for the landing page's fixed code examples. */
class ExampleCode
{
    public static function highlight(string $code, bool $numbers = true): string
    {
        $pattern = <<<'REGEX'
~("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\#[^\r\n]*|(?<!\S)//[^\r\n]*|\b(?:import|from|as|def|return|if|else|for|in|True|False|None|TRUE|FALSE|NULL)\b|\b\d+(?:\.\d+)?\b|--[a-z][a-z-]*|\b[A-Za-z_][\w.:]*(?=\s*\()|\b[A-Za-z_][\w_]*(?=:)|\bfw(?=\s)|\bfw_\w+)~u
REGEX;
        $lines = [];
        foreach (explode("\n", $code) as $index => $line) {
            $tokens = preg_split($pattern, $line, -1, PREG_SPLIT_DELIM_CAPTURE);
            $html = '';
            foreach ($tokens as $position => $token) {
                $escaped = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($position % 2 === 0) {
                    $html .= $escaped;

                    continue;
                }
                $color = match (true) {
                    str_starts_with($token, '#'), str_starts_with($token, '//') => 'text-slate-400',
                    str_starts_with($token, '"'), str_starts_with($token, "'") => 'text-emerald-300',
                    is_numeric($token) => 'text-amber-200',
                    in_array($token, ['import', 'from', 'as', 'def', 'return', 'if', 'else', 'for', 'in', 'True', 'False', 'None', 'TRUE', 'FALSE', 'NULL'], true) => 'text-violet-300',
                    default => 'text-sky-300',
                };
                $html .= '<span class="'.$color.'">'.$escaped.'</span>';
            }
            if (str_starts_with(ltrim($line), '✗')) {
                $html = '<span class="text-rose-300">'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>';
            }
            $number = $numbers ? '<span aria-hidden="true" class="select-none text-right text-slate-400">'.str_pad($index + 1, 2, '0', STR_PAD_LEFT).'</span>' : '';
            $class = $numbers ? 'grid grid-cols-[1.25rem_1fr] gap-3' : 'block';
            $lines[] = '<span class="'.$class.'">'.$number.'<span>'.($html ?: ' ').'</span></span>';
        }

        return implode('', $lines);
    }
}
