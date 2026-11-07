<?php

namespace Tests\Feature;

use App\Support\Docs\Store;
use Tests\Feature\Docs\DocsTestCase;

/**
 * Until the R package's docs-site.tar.gz is imported, r.framework.pub is
 * built from resources/docs/guides and database/docs.db. The real content
 * is the fixture.
 */
class DocsSiteTest extends DocsTestCase
{
    public function test_a_guide_titled_like_its_section_introduces_the_whole_section(): void
    {
        $site = ['guides' => ['project-types' => ['title' => 'Project types'], 'standard-project' => ['title' => 'Standard project']]];
        $section = ['title' => 'Project types'];

        $this->assertSame('All project types', \App\Support\Docs\Sites::navTitle($site, $section, 'project-types'));
        $this->assertSame('Standard project', \App\Support\Docs\Sites::navTitle($site, $section, 'standard-project'));
    }

    public function test_home_lists_guides_and_categories(): void
    {
        $this->docs('r')
            ->assertOk()
            ->assertSee('Framework for R')
            ->assertSeeText('remotes::install_github("frameworktools/framework")')
            ->assertSee('Conventions')
            ->assertSee('Reference');
    }

    public function test_the_install_box_is_colored_in_each_products_language(): void
    {
        // Colored tokens go inside the copied element, so Copy still takes plain text
        $this->docs('r')
            ->assertSee('<code id="docs-install"><span class="line"><span class="token', false)
            ->assertSee('style="color: #a5d6ff;">frameworktools/framework</span>', false);

        $this->import('stata');
        $this->docs('stata')
            ->assertSee('<code id="docs-install"><span class="line"><span class="token" style="color: #ff7b72;">net</span>', false)
            ->assertSeeText('net install framework, from("https://example.org/framework-stata") replace');

        $this->import('py');
        $this->docs('py')
            ->assertSee('<code id="docs-install"><span class="line"><span class="token', false)
            ->assertSeeText('uv add framework-py');

        $this->import('cli');
        $this->docs('cli')
            ->assertSee('<code id="docs-install"><span class="line"><span class="token" style="color:', false)
            ->assertSeeText('brew install frameworktools/tap/fw');
    }

    public function test_sites_imported_before_install_coloring_color_it_when_shown(): void
    {
        $this->import('py');
        $site = Store::release('py').'/site.json';
        $data = json_decode(file_get_contents($site), true);
        unset($data['install_html']);
        file_put_contents($site, json_encode($data));
        Store::flush();

        $this->docs('py')->assertSee('<code id="docs-install"><span class="line"><span class="token', false);
    }

    public function test_reference_examples_and_usage_are_colored(): void
    {
        $this->docs('r', '/reference/ai_generate_context')
            ->assertSee('<pre class="mt-4 overflow-x-auto', false)
            ->assertSee('<code><span class="line"><span class="token" style="color: #ff7b72;">if</span>', false);

        // Usage lines too, in the product's language
        $this->import('stata');
        $this->docs('stata', '/reference/fw_use')
            ->assertSee('<code><span class="line"><span class="token', false)
            ->assertSeeText('fw_use name [, clear]');
    }

    public function test_untagged_code_takes_the_products_language_and_plain_text_stays_plain(): void
    {
        $dir = $this->fixture('py');
        file_put_contents("{$dir}/guides/installation.md", file_get_contents("{$dir}/guides/installation.md").<<<'MD'

            ## More

            ```
            import framework as fw
            df = fw.data_read("inputs.raw.survey")
            ```

            ```
            notebooks/
              1-cleaning.qmd
              2-analysis.qmd
            ```

                with fw.capture_output() as captured:
                    model.summary()
            MD);
        $this->import('py', $dir);

        $this->docs('py', '/guides/installation')
            ->assertSee('<pre class="phiki language-python"', false)
            ->assertSee("<pre><code>notebooks/\n", false)
            ->assertSee('<span class="token" style="color: #ff7b72;">with</span>', false);
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
