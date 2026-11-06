<?php

namespace Tests\Feature\Docs;

class DocsShareImageTest extends DocsTestCase
{
    public function test_a_shared_docs_page_shows_the_framework_image_and_its_own_title(): void
    {
        $this->import('cli');

        $this->docs('cli', '/guides/install')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.rtrim(config('app.url'), '/').'/images/og.png">', false)
            ->assertSee('property="og:title" content="Install fw', false);
    }
}
