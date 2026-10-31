<?php

namespace App\Support\Docs;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;

/**
 * Renders docs-site Markdown: CommonMark with GFM, ids on headings, GitHub
 * alerts (> [!NOTE]) as callouts, and link targets rewritten by Links.
 */
class Markdown
{
    public const ALERTS = [
        'NOTE' => 'Note',
        'TIP' => 'Tip',
        'IMPORTANT' => 'Important',
        'WARNING' => 'Warning',
        'CAUTION' => 'Caution',
    ];

    /**
     * @return array{html: string, toc: list<array{level: int, id: string, text: string}>}
     */
    public static function render(string $markdown, ?Links $links = null): array
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'none',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($links) {
            // Collect first: alert() detaches nodes, which upsets a live walk
            $nodes = iterator_to_array($event->getDocument()->iterator(), false);
            foreach ($nodes as $node) {
                if ($node instanceof BlockQuote) {
                    self::alert($node);
                } elseif ($links !== null && ($node instanceof Link || $node instanceof Image)) {
                    $node->setUrl($links->resolve($node->getUrl()));
                }
            }
        });

        $html = (string) (new MarkdownConverter($environment))->convert($markdown);

        preg_match_all('#<h([23]) id="([^"]+)">(.*?)</h\1>#s', $html, $matches, PREG_SET_ORDER);
        $toc = array_map(fn ($m) => [
            'level' => (int) $m[1],
            'id' => $m[2],
            'text' => html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5),
        ], $matches);

        return ['html' => $html, 'toc' => $toc];
    }

    /** A Markdown fragment from reference.json, without headings for the TOC */
    public static function html(?string $markdown, ?Links $links = null): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        return self::render($markdown, $links)['html'];
    }

    /** > [!NOTE] … becomes a blockquote with callout classes and a title line */
    /** One line of Markdown (a summary) as inline HTML, without the paragraph. */
    public static function inline(string $markdown, ?Links $links = null): string
    {
        return trim(preg_replace('#^<p>(.*)</p>$#s', '$1', trim(self::render($markdown, $links)['html'])));
    }

    /** One line of Markdown as plain text, for meta descriptions, menus, and search. */
    public static function plain(string $markdown): string
    {
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $markdown);

        return trim(str_replace(['`', '**', '__'], '', $text));
    }

    private static function alert(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        $marker = $paragraph instanceof Paragraph ? $paragraph->firstChild() : null;

        if (! $marker instanceof Text || ! preg_match('/^\[!([A-Z]+)\]\s*/', $marker->getLiteral(), $m) || ! isset(self::ALERTS[$m[1]])) {
            return;
        }

        $rest = substr($marker->getLiteral(), strlen($m[0]));
        if ($rest === '') {
            $next = $marker->next();
            $marker->detach();
            if ($next instanceof Newline) {
                $next->detach();
            }
            if ($paragraph->firstChild() === null) {
                $paragraph->detach();
            }
        } else {
            $marker->setLiteral($rest);
        }

        $kind = strtolower($m[1]);
        $quote->data->append('attributes/class', "callout callout-{$kind}");

        $title = new Paragraph;
        $title->data->set('attributes/class', 'callout-title');
        $title->appendChild(new Text(self::ALERTS[$m[1]]));
        $quote->prependChild($title);
    }
}
