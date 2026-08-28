<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The docs site is read-only against committed artifacts (resources/docs
 * guides + database/docs.db), so no RefreshDatabase — the real content is
 * the fixture.
 */
class DocsSiteTest extends TestCase
{
    private function docs(string $path)
    {
        return $this->get('http://'.config('framework.docs_host').$path);
    }

    public function test_home_lists_guides_and_categories(): void
    {
        $this->docs('/')
            ->assertOk()
            ->assertSee('Structured data science')
            ->assertSee('Conventions')
            ->assertSee('Function reference');
    }

    public function test_guides_render_markdown(): void
    {
        $this->docs('/guides/conventions')
            ->assertOk()
            ->assertSee('convention over configuration')
            ->assertSee('scaffold()');
    }

    public function test_unknown_guide_404s(): void
    {
        $this->docs('/guides/never-heard-of-it')->assertNotFound();
    }

    public function test_reference_groups_functions_by_category(): void
    {
        $this->docs('/reference')
            ->assertOk()
            ->assertSee('data_read')
            ->assertSee('scaffold');
    }

    public function test_function_page_shows_usage_and_arguments(): void
    {
        $this->docs('/reference/data_read')
            ->assertOk()
            ->assertSee('data_read()')
            ->assertSee('Usage');
    }

    public function test_unknown_functions_404(): void
    {
        // (Alias resolution exists in Docs::fn(), but this docs.db build
        // ships an empty aliases table — revisit when docs_export() emits them.)
        $this->docs('/reference/not_a_function')->assertNotFound();
    }

    public function test_search_finds_functions(): void
    {
        $this->docs('/search?q=publish')
            ->assertOk()
            ->assertSee('publish_notebook');
    }

    public function test_docs_host_does_not_leak_app_routes(): void
    {
        // The apex still serves the app landing page, not docs
        $this->get('http://framework.test/')
            ->assertOk()
            ->assertSee('cloud companion');
    }
}
