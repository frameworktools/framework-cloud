<?php

namespace App\Support\Docs;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Phiki\Phiki;

/**
 * Fenced code with syntax colors, done once at import so pages ship plain
 * HTML. Token colors come from a dark theme; the block keeps the docs'
 * own background. Unknown languages and `text` stay uncolored. Untagged
 * and indented blocks take the product's language when they look like code.
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

    /** @param  string|null  $default  the language for untagged blocks that look like code */
    public function __construct(private readonly ?string $default = null) {}

    /** The product's language, or null for an unknown product */
    public static function productLanguage(string $product): ?string
    {
        return self::PRODUCT_LANGUAGES[$product] ?? null;
    }

    /**
     * The home page's install command, colored in the product's language,
     * or as shell when it is a package manager's command (uv add, pip install).
     */
    public static function install(string $command, string $product): ?string
    {
        $language = preg_match('/^\s*(?:\$ )?(?:uv|pip3?|pipx|brew|curl|npm|conda)\s/', $command)
            ? 'shellscript'
            : (self::productLanguage($product) ?? '');

        return self::tokens($command, $language);
    }

    /**
     * Whether an untagged block reads as code rather than output, a file
     * tree, or prose: a call, an assignment, or a familiar first word.
     */
    public static function looksLikeCode(string $code): bool
    {
        if (preg_match('/[├└│]/u', $code)) {
            return false;
        }

        return (bool) preg_match('/[()=]|<-|^\s*(?:\$ |(?:fw|brew|uv|pip|git|cd|export|curl|net|ssc|use|sysuse|display|local|global|library|import|from|def|class|with|for|if|return)\b)/m', $code);
    }

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
        if (! $node instanceof FencedCode && ! $node instanceof IndentedCode) {
            throw new InvalidArgumentException('Expected a code block.');
        }

        $code = rtrim($node->getLiteral(), "\n");
        $language = $node instanceof FencedCode ? strtolower($node->getInfoWords()[0] ?? '') : '';
        if ($language === '' && $this->default !== null && self::looksLikeCode($code)) {
            $language = $this->default;
        }
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
