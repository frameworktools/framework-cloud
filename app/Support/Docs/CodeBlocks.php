<?php

namespace App\Support\Docs;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Phiki\Phiki;

/**
 * Fenced code with syntax colors, done once at import so pages ship plain
 * HTML. Token colors come from a dark theme; the block keeps the docs'
 * own background. Unknown languages and `text` stay uncolored.
 */
class CodeBlocks implements NodeRendererInterface
{
    public const THEME = 'github-dark-default';

    /** Fence names the docs use => Phiki grammar */
    private const ALIASES = [
        'sh' => 'shellscript',
        'bash' => 'shellscript',
        'shell' => 'shellscript',
        'zsh' => 'shellscript',
        'console' => 'shellscript',
        'qmd' => 'markdown',
        'md' => 'markdown',
        'yml' => 'yaml',
        'py' => 'python',
        'do' => 'stata',
        'ado' => 'stata',
    ];

    /** Each product's own language, for reference usage lines and examples */
    public const PRODUCT_LANGUAGES = [
        'r' => 'r',
        'py' => 'python',
        'cli' => 'shellscript',
        'stata' => 'stata',
    ];

    private static ?Phiki $phiki = null;

    /** Colored tokens for the inside of a <code> element, or null for an unknown language */
    public static function tokens(string $code, string $language): ?string
    {
        $grammar = self::ALIASES[$language] ?? $language;
        $phiki = self::$phiki ??= new Phiki;
        if (! $phiki->environment->grammars->has($grammar)) {
            return null;
        }

        $html = $phiki->codeToHtml(rtrim($code, "\n"), $grammar, self::THEME)->toString();

        return preg_match('#<code>(.*)</code>#s', $html, $m) ? $m[1] : null;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement|string
    {
        if (! $node instanceof FencedCode) {
            throw new InvalidArgumentException('Expected a fenced code block.');
        }

        $code = rtrim($node->getLiteral(), "\n");
        $language = strtolower($node->getInfoWords()[0] ?? '');
        $grammar = self::ALIASES[$language] ?? $language;
        $phiki = self::$phiki ??= new Phiki;

        if ($grammar === '' || ! $phiki->environment->grammars->has($grammar)) {
            $attributes = $language !== '' ? ['class' => 'language-'.$language] : [];

            return new HtmlElement('pre', [], new HtmlElement('code', $attributes, htmlspecialchars($code, ENT_QUOTES | ENT_HTML5)));
        }

        $html = $phiki->codeToHtml($code, $grammar, self::THEME)->toString();

        // Keep the docs' block background and text color; only tokens are colored
        return preg_replace('#^<pre class="[^"]*"([^>]*?) style="[^"]*"#', '<pre class="phiki language-'.$language.'"$1', $html, 1);
    }
}
