<?php

namespace Tests\Feature;

use Tests\Feature\Docs\DocsTestCase;

/**
 * Until the R package's docs-site.tar.gz is imported, r.framework.pub is
 * built from resources/docs/guides and database/docs.db. The real content
 * is the fixture.
 */
class DocsSiteTest extends DocsTestCase
{
    public function test_home_lists_guides_and_categories(): void
    {
        $this->docs('r')
            ->assertOk()
            ->assertSee('Framework for R')
            ->assertSee('remotes::install_github(&quot;frameworktools/framework&quot;)', false)
            ->assertSee('Conventions')
            ->assertSee('Reference');
    }

    public function test_guides_render_markdown(): void
    {
        $this->docs('r', '/guides/conventions')
            ->assertOk()
            ->assertSee('convention over configuration')
            ->assertSee('scaffold()')
            // The old hand-made footer is gone; the layout's previous and next take its place
            ->assertDontSee('display: flex', false)
            ->assertSee('Next');
    }

    public function test_old_guide_links_point_at_the_new_paths(): void
    {
        $this->docs('r', '/guides/caching')
            ->assertSee('href="/guides/connections"', false)
            ->assertDontSee('href="/docs/', false);

        $this->docs('r', '/guides/setup')->assertSee('src="/images/setup-gui.png"', false);
        $this->docs('r', '/images/setup-gui.png')->assertOk();
    }

    public function test_unknown_guide_404s(): void
    {
        $this->docs('r', '/guides/never-heard-of-it')->assertNotFound();
    }

    public function test_reference_groups_functions_by_category(): void
    {
        $this->docs('r', '/reference')
            ->assertOk()
            ->assertSee('data_read')
            ->assertSee('scaffold');
    }

    public function test_function_page_shows_usage_and_arguments(): void
    {
        $this->docs('r', '/reference/data_read')
            ->assertOk()
            ->assertSee('data_read()')
            ->assertSee('Usage')
            ->assertSee('Arguments');
    }

    public function test_unknown_functions_404(): void
    {
        $this->docs('r', '/reference/not_a_function')->assertNotFound();
    }

    public function test_search_finds_functions(): void
    {
        $this->docs('r', '/search?q=publish')
            ->assertOk()
            ->assertSee('publish_notebook');
    }
}
