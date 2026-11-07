<?php

namespace Tests\Unit;

use App\Support\DeferHidden;
use PHPUnit\Framework\TestCase;

class DeferHiddenTest extends TestCase
{
    public function test_large_hidden_panels_become_templates_and_small_ones_stay(): void
    {
        $rows = str_repeat('<span>x</span>', 15);
        $html = '<!DOCTYPE html><html><body>'
            .'<div data-panel="a">'.$rows.'</div>'
            .'<div data-panel="b" hidden>'.$rows.'<div data-panel="c" hidden>'.$rows.'</div></div>'
            .'<div data-panel="d" hidden><span>small</span></div>'
            .'<div hidden>'.$rows.'</div>'
            .'</body></html>';

        $out = DeferHidden::transform($html);

        // The visible panel and the small one are untouched
        $this->assertStringContainsString('<div data-panel="a"><span>x</span>', $out);
        $this->assertStringContainsString('<div data-panel="d" hidden=""><span>small</span></div>', $out);
        // The hidden panel's contents, nested panel included, are one template
        $this->assertStringContainsString('<div data-panel="b" hidden=""><template data-defer=""><span>x</span>', $out);
        $this->assertSame(1, substr_count($out, '<template data-defer'));
        // An element without a data- attribute is not a panel
        $this->assertStringContainsString('<div hidden=""><span>x</span>', $out);
    }
}
