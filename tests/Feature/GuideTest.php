<?php

namespace Tests\Feature;

use App\Http\Controllers\GuideController;
use Tests\TestCase;

class GuideTest extends TestCase
{
    public function test_every_listed_page_renders(): void
    {
        foreach (collect(GuideController::PAGES)->collapse()->keys() as $page) {
            $this->get($page === 'index' ? '/docs' : "/docs/{$page}")->assertOk();
        }
    }

    public function test_every_guide_file_is_listed(): void
    {
        $files = collect(glob(base_path('docs/guide/*.md')))->map(fn ($f) => basename($f, '.md'))->sort()->values();

        $this->assertEquals($files->all(), collect(GuideController::PAGES)->collapse()->keys()->sort()->values()->all());
    }

    public function test_links_between_pages_point_at_the_site(): void
    {
        $this->get('/docs/self-hosting')
            ->assertSee('href="'.url('/docs/install-docker').'"', false)
            ->assertDontSee('install-docker.md"', false);
    }

    public function test_unknown_pages_are_not_found(): void
    {
        $this->get('/docs/nope')->assertNotFound();
        $this->get('/docs/..%2F.env')->assertNotFound();
    }
}
