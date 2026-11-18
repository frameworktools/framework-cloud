<?php

namespace Tests\Unit;

use App\Support\Docs\Markdown;
use PHPUnit\Framework\TestCase;

class CodeBlockTitleTest extends TestCase
{
    public function test_a_fence_title_rides_on_the_pre(): void
    {
        $html = Markdown::render("```sh title=\".env\"\nAPP_URL=http://localhost\n```\n")['html'];

        $this->assertStringContainsString('<pre class="phiki language-sh" data-title=".env"', $html);
    }

    public function test_an_uncolored_block_keeps_its_title(): void
    {
        $html = Markdown::render("```text title=\"Readable URL\"\nhttps://example.org/@ada\n```\n")['html'];

        $this->assertStringContainsString('data-title="Readable URL"', $html);
    }

    public function test_a_fence_without_a_title_has_none(): void
    {
        $this->assertStringNotContainsString('data-title', Markdown::render("```r\nx <- 1\n```\n")['html']);
    }
}
