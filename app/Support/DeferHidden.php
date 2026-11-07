<?php

namespace App\Support;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Cache;

/**
 * The landing page renders every language, example, and file of its demos,
 * and most start hidden. This moves a hidden panel's contents into a
 * <template data-defer>, so the browser parses them only when
 * resources/js/landing-hero.js shows the panel. Only panels (elements with a
 * data- attribute) with enough inside are deferred.
 */
class DeferHidden
{
    /** Fewer elements than this stay in the page */
    private const MIN_ELEMENTS = 12;

    public static function apply(string $html): string
    {
        return Cache::remember('defer-hidden:'.hash('xxh128', $html), now()->addDay(), fn () => self::transform($html));
    }

    public static function transform(string $html): string
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        foreach (iterator_to_array($document->querySelectorAll('[hidden]')) as $element) {
            if (! self::deferrable($element)) {
                continue;
            }
            // Through innerHTML, so the parser files the markup as the template's contents
            $element->innerHTML = '<template data-defer>'.$element->innerHTML.'</template>';
        }

        return $document->saveHtml();
    }

    private static function deferrable(Element $element): bool
    {
        // Already inside a deferred panel, or a template itself
        for ($parent = $element->parentElement; $parent; $parent = $parent->parentElement) {
            if ($parent->hasAttribute('hidden') && self::panel($parent)) {
                return false;
            }
        }

        return self::panel($element) && $element->querySelectorAll('*')->length >= self::MIN_ELEMENTS;
    }

    private static function panel(Element $element): bool
    {
        if (in_array(strtolower($element->tagName), ['template', 'input', 'option', 'script', 'style'], true)) {
            return false;
        }
        foreach ($element->getAttributeNames() as $name) {
            if (str_starts_with($name, 'data-')) {
                return true;
            }
        }

        return false;
    }
}
