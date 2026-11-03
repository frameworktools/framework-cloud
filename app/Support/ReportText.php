<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class ReportText
{
    public static function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)->allowLinkSchemes(['http', 'https', 'mailto'])->allowRelativeLinks();
        foreach (['p', 'br', 'h2', 'h3', 'strong', 'em', 'u', 's', 'sub', 'sup', 'ul', 'li', 'blockquote', 'code', 'pre', 'hr'] as $tag) {
            $config = $config->allowElement($tag);
        }
        $config = $config->allowElement('ol', ['start'])->allowElement('a', ['href', 'title'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->dropElement('script')->dropElement('style')->dropElement('iframe')->dropElement('object')->dropElement('svg');

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    public static function html(array $block): string
    {
        $text = $block['text'] ?? '';

        if (($block['format'] ?? null) !== 'html') {
            $text = Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
            $text = preg_replace('/<(\/?)(h1)(?=[\s>])/i', '<$1h2', $text);
        }

        return self::sanitize($text);
    }
}
