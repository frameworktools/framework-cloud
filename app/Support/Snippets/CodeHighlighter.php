<?php

namespace App\Support\Snippets;

use Phiki\Phiki;
use Throwable;

/**
 * Code as styled lines, colored with Phiki's TextMate grammars. Scopes map
 * to eight classes (kw, str, num, const, fn, var, op, com) so the reader and
 * every embed renderer color code the same way from data alone.
 */
class CodeHighlighter
{
    private const GRAMMARS = ['r' => 'r', 'python' => 'python', 'stata' => 'stata'];

    /** Prefixes that leave the next word in command position */
    private const STATA_PREFIXES = ['quietly', 'qui', 'quiet', 'quietl', 'noisily', 'noi', 'capture', 'captur', 'captu', 'capt', 'cap', 'by', 'bysort', 'bys', 'xi', 'svy', 'mi', 'estimate', 'est', 'nestreg', 'stepwise', 'bootstrap', 'jackknife', 'permute', 'simulate', 'statsby', 'rolling', 'fp', 'mfp', 'frame', 'version', 'timer', 'eststo', 'esttab'];

    private static ?Phiki $phiki = null;

    /** @return list<list<array{0: string, 1: string}>> */
    public static function lines(string $code, string $language): array
    {
        $out = new StyledText;
        $grammar = self::GRAMMARS[$language] ?? null;
        try {
            $tokens = $grammar ? (self::$phiki ??= new Phiki)->codeToTokens($code, $grammar) : null;
        } catch (Throwable $e) {
            report($e);
            $tokens = null;
        }
        if ($tokens === null) {
            $out->append($code);

            return $out->lines();
        }
        foreach ($tokens as $index => $line) {
            $commandPosition = true;
            if ($index > 0) {
                $out->append("\n");
            }
            foreach ($line as $token) {
                $text = str_replace("\n", '', $token->text);
                if ($text === '') {
                    continue;
                }
                $class = self::classFor($token->scopes, $text, $language);
                if ($language === 'stata') {
                    [$class, $commandPosition] = self::stataCommand($token->scopes, $text, $class, $commandPosition);
                }
                $out->append($text, $class);
            }
        }

        return $out->lines();
    }

    /** @param  list<string>  $scopes */
    private static function classFor(array $scopes, string $text, string $language): string
    {
        if ($language === 'stata' && $text === '$') {
            return 'var';
        }
        // R marks a whole call, arguments too; only a token whose innermost scope is the call is its name
        if (str_starts_with((string) end($scopes), 'meta.function-call.r')) {
            return 'fn';
        }
        // The innermost scope decides
        foreach (array_reverse($scopes) as $scope) {
            $class = match (true) {
                str_starts_with($scope, 'comment'), str_starts_with($scope, 'punctuation.definition.comment') => 'com',
                // Stata marks macro quotes as string punctuation and the name as a type
                $language === 'stata' && str_starts_with($scope, 'punctuation.definition.string') && ($text === '`' || $text === "'") => 'var',
                $language === 'stata' && (str_starts_with($scope, 'entity.name.type.class') || str_starts_with($scope, 'variable.other.global')) => 'var',
                str_starts_with($scope, 'string'), str_starts_with($scope, 'punctuation.definition.string') => 'str',
                str_starts_with($scope, 'constant.numeric') => 'num',
                str_starts_with($scope, 'constant'), str_starts_with($scope, 'support.constant') => 'const',
                str_starts_with($scope, 'keyword.operator') => 'op',
                str_starts_with($scope, 'keyword'), str_starts_with($scope, 'storage') => 'kw',
                str_starts_with($scope, 'entity.name.function'), str_starts_with($scope, 'support.function'),
                str_starts_with($scope, 'variable.function') => 'fn',
                str_starts_with($scope, 'variable.parameter'), str_starts_with($scope, 'variable.object'), str_starts_with($scope, 'variable.language') => 'var',
                default => null,
            };
            if ($class !== null) {
                return $class;
            }
        }

        return '';
    }

    /**
     * The Stata grammar colors any word that names a command, wherever it
     * stands: in `regress score income`, `score` too. A command keyword
     * counts only at the start of a command, after a prefix, or after a colon.
     *
     * @param  list<string>  $scopes
     * @return array{0: string, 1: bool}
     */
    private static function stataCommand(array $scopes, string $text, string $class, bool $commandPosition): array
    {
        if (trim($text) === '') {
            return [$class, $commandPosition];
        }
        $command = false;
        foreach ($scopes as $scope) {
            if (preg_match('/^keyword\.(control\.flow|functions)\./', $scope)) {
                $command = true;
            }
        }
        if ($command) {
            $word = strtolower(trim($text));

            return [$commandPosition ? 'kw' : '', in_array($word, self::STATA_PREFIXES, true)];
        }
        if (str_ends_with(rtrim($text), ':') || in_array(trim($text), ['{', '}'], true)) {
            return [$class, true];
        }

        return [$class, $commandPosition && in_array(strtolower(trim($text)), self::STATA_PREFIXES, true)];
    }
}
