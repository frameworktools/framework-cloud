<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_links_show_the_framework_image_instead_of_a_screenshot(): void
    {
        $this->assertFileExists(public_path('images/og.png'));

        foreach (['/', '/docs', '/docs/self-hosting', '/privacy', '/terms'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<meta property="og:image" content="'.rtrim(config('app.url'), '/').'/images/og.png">', false)
                ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        }
    }

    public function test_the_homepage_preview_names_framework_and_its_tagline(): void
    {
        $this->get('/')->assertSee('<meta property="og:title" content="Framework: Structured data science workflows">', false);
    }
}
