<?php

namespace Tests\Feature;

use Tests\TestCase;

class SelfHostingPageTest extends TestCase
{
    public function test_the_page_renders_and_links_to_the_guide(): void
    {
        $response = $this->get('/self-hosting')->assertOk();

        foreach (['install-docker', 'running-at-home', 'server-setup', 'configuration', 'maintenance', 'install-manual'] as $page) {
            $response->assertSee('href="'.url('/docs/'.$page), false);
            $this->get('/docs/'.$page)->assertOk();
        }
    }

    public function test_the_landing_page_sends_self_hosting_to_the_new_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.url('/self-hosting').'"', false)
            ->assertDontSee('href="#hosting"', false);
    }
}
