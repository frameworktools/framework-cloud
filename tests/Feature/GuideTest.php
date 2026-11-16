<?php

namespace Tests\Feature;

use App\Http\Controllers\GuideController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('href="/docs/install-docker"', false)
            ->assertDontSee('install-docker.md"', false);
    }

    public function test_unknown_pages_are_not_found(): void
    {
        $this->get('/docs/nope')->assertNotFound();
        $this->get('/docs/..%2F.env')->assertNotFound();
    }

    public function test_pages_use_the_docs_sites_layout(): void
    {
        $this->get('/docs/getting-started')
            ->assertOk()
            ->assertSee('<title>Getting started · Framework Cloud</title>', false)
            // Header: product switcher with Cloud current, search, sign-in
            ->assertSee('aria-label="Products"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-docs-search-open', false)
            ->assertSee('data-index="/docs/search.json"', false)
            ->assertSee('data-product="cloud"', false)
            ->assertSee('href="'.route('login').'"', false)
            // Sidebar of guide pages, with the current one marked
            ->assertSee('aria-label="Sidebar"', false)
            ->assertSee('<a href="/docs/getting-started"  aria-current="page"', false)
            ->assertSee('href="/docs/maintenance"', false)
            // Section eyebrow, the Markdown's h1, contents, previous and next
            ->assertSee('aria-label="On this page"', false)
            ->assertSee('href="#account-registration" data-toc-link', false)
            ->assertSee('<h2 id="account-registration">', false)
            ->assertSee('aria-label="Previous and next"', false)
            ->assertSeeInOrder(['Previous', 'href="/docs"', 'Overview', 'Next', 'href="/docs/signing-in"', 'Signing in'], false)
            ->assertSee('docs/guide/getting-started.md', false);

        // The index is the first page: a heading from its Markdown, no Previous
        $this->get('/docs')
            ->assertSee('<title>Documentation · Framework Cloud</title>', false)
            ->assertSee('Framework Cloud documentation</h1>', false)
            ->assertDontSee('>Previous<', false);
    }

    public function test_signed_in_readers_see_a_projects_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/docs')
            ->assertSee('href="'.route('projects').'"', false);
    }

    public function test_code_is_colored(): void
    {
        $this->get('/docs/getting-started')
            ->assertSee('<pre class="phiki language-r"', false)
            ->assertSee('<pre class="phiki language-python"', false)
            ->assertSee('<span class="token" style="color:', false);

        $this->get('/docs/install-docker')->assertSee('<pre class="phiki language-sh"', false);
        $this->get('/docs/stata')->assertSee('<pre class="phiki language-stata"', false);
    }

    public function test_r_then_python_blocks_become_tabs(): void
    {
        $this->get('/docs/getting-started')
            ->assertSee('data-code-tabs', false)
            ->assertSee('<div data-lang-panel="python" hidden><pre class="phiki language-python"', false);
    }

    public function test_package_docs_switch_to_the_cloud_guide(): void
    {
        $this->get('http://'.config('framework.docs_hosts.r').'/')
            ->assertSee('href="'.rtrim(config('app.url'), '/').'/docs"', false);
    }

    public function test_the_search_index_includes_the_guide(): void
    {
        $items = collect($this->getJson('/docs/search.json')->assertOk()->json('items'))->where('product', 'cloud');

        $this->assertNotNull($items->firstWhere('url', '/docs/install-docker'));
        $this->assertNotNull($items->firstWhere('url', '/docs/getting-started#account-registration'));
    }
}
