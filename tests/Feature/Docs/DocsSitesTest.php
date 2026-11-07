<?php

namespace Tests\Feature\Docs;

/**
 * The docs hosts and what their pages show once a site is imported.
 */
class DocsSitesTest extends DocsTestCase
{
    public function test_each_product_answers_on_its_own_host(): void
    {
        $this->import('py');
        $this->import('cli');

        $this->docs('r')->assertOk()->assertSee('Framework for R');
        $this->docs('py')->assertOk()->assertSee('Framework for Python')->assertSeeText('uv add framework-py');
        $this->docs('cli')->assertOk()->assertSee('The Framework command line tool');

        // Each host has its own pages
        $this->docs('py', '/guides/install')->assertNotFound();
        $this->docs('cli', '/guides/install')->assertOk()->assertSee('Install fw');
    }

    public function test_the_home_page_reads_like_a_guide(): void
    {
        $this->import('py');

        $this->docs('py')
            ->assertOk()
            // The sidebar, the install command, and "Contents" as on every other page
            ->assertSee('aria-label="Sidebar"', false)
            ->assertSee('<h2 id="install">Install</h2>', false)
            ->assertSeeText('uv add framework-py')
            ->assertSee('<h2 id="getting-started">', false)
            ->assertSeeInOrder(['Installation', 'Install framework-py with uv or pip'])
            ->assertSee('Contents')
            ->assertDontSee('bg-blue-950', false);
    }

    public function test_stata_joins_the_switcher_once_its_docs_are_imported(): void
    {
        config(['framework.stata_package' => false]);
        $this->docs('r')->assertDontSee('href="http://stata.framework.test/"', false);
        $this->docs('stata')->assertOk()->assertSee('Documentation unavailable');

        $this->import('stata');

        $this->docs('r')->assertSee('href="http://stata.framework.test/"', false);
        $this->docs('stata')
            ->assertOk()
            ->assertSee('Framework for Stata')
            ->assertSeeText('net install framework');
        $this->docs('stata', '/guides/installation')
            ->assertOk()
            ->assertSee('href="http://r.framework.test/guides/installation"', false);
        $this->docs('stata', '/reference/fw_use')->assertOk()->assertSee('fw_use name');
    }

    public function test_the_apex_still_serves_the_app(): void
    {
        $this->get('http://framework.test/')
            ->assertOk()
            ->assertSee('Structured data science workflows');
    }

    public function test_fw_docs_host_is_still_the_r_host(): void
    {
        // phpunit.xml pins FW_DOCS_HOST_R; set and clear it everywhere env() reads
        $env = function (string $name, ?string $value): void {
            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv("{$name}={$value}");
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        };
        $pinned = getenv('FW_DOCS_HOST_R') ?: null;

        try {
            $env('FW_DOCS_HOST_R', null);
            $config = require config_path('framework.php');
            $this->assertSame('r.framework.test', $config['docs_hosts']['r']);

            $env('FW_DOCS_HOST', 'docs.example.edu');
            $config = require config_path('framework.php');
            $this->assertSame('docs.example.edu', $config['docs_hosts']['r']);

            $env('FW_DOCS_HOST_R', 'r.example.edu');
            $config = require config_path('framework.php');
            $this->assertSame('r.example.edu', $config['docs_hosts']['r']);
        } finally {
            $env('FW_DOCS_HOST', null);
            $env('FW_DOCS_HOST_R', $pinned);
        }
    }

    public function test_products_not_imported_yet_say_so(): void
    {
        $this->docs('py')->assertOk()->assertSee('Documentation unavailable');
        $this->docs('cli')->assertOk()->assertSee('Documentation unavailable');
        $this->docs('cli', '/guides/install')->assertNotFound();
        $this->docs('cli', '/reference')->assertNotFound();
    }

    public function test_the_top_bar_has_section_dropdowns_and_the_product_switcher(): void
    {
        $this->import('py');

        $this->docs('py')
            ->assertSee('Getting Started')
            // A guide item with its description under it
            ->assertSeeInOrder(['Installation', 'Install framework-py with uv or pip'])
            // The reference dropdown lists categories with descriptions
            ->assertSeeInOrder(['Data', 'Reading, writing, and managing data files'])
            ->assertSee('href="http://r.framework.test/"', false)
            ->assertSee('href="http://cli.framework.test/"', false)
            ->assertSee('⌘K');
    }

    public function test_guides_render_with_callouts_a_table_of_contents_and_neighbours(): void
    {
        $this->import('py');

        $this->docs('py', '/guides/installation')
            ->assertOk()
            ->assertSee('<h2 id="requirements">Requirements</h2>', false)
            // Fenced code is colored at import, on the docs' own background
            ->assertSee('<pre class="phiki language-', false)
            ->assertDontSee('style="background-color:', false)
            ->assertSee('class="callout callout-note"', false)
            ->assertSee('class="callout callout-warning"', false)
            ->assertDontSee('[!NOTE]')
            ->assertSee('On this page')
            ->assertSee('href="#with-uv"', false)
            // Next follows the section's positions: Installation, then Quick Start
            ->assertSeeInOrder(['Next', 'Quick Start']);

        // The last guide of Getting Started leads into Features
        $this->docs('py', '/guides/quick-start')
            ->assertSeeInOrder(['Previous', 'Installation', 'Next', 'Data Management']);
    }

    public function test_links_to_another_product_use_its_host(): void
    {
        $this->import('py');

        $this->docs('py', '/guides/installation')
            ->assertSee('href="http://r.framework.test/guides/setup"', false)
            ->assertDontSee('fwdocs://');
    }

    public function test_images_are_served_under_images(): void
    {
        $this->import('py');

        $this->docs('py', '/images/catalog.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->docs('py', '/images/missing.png')->assertNotFound();
        $this->docs('py', '/images/../site.json')->assertNotFound();
    }

    public function test_reference_entry_shows_usage_parameters_sections_examples_and_see_also(): void
    {
        $this->import('py');

        $this->docs('py', '/reference/data_read')
            ->assertOk()
            ->assertSee('data_read()')
            ->assertSee('Read a data file from the catalog')
            ->assertSeeText("data_read(name, *, delimiter='auto')")
            // Usage and examples are colored as Python
            ->assertSee('<span class="token" style="color:', false)
            ->assertSeeInOrder(['Arguments', 'Name', 'Type', 'Default', 'Description', 'delimiter', 'str', "'auto'"])
            ->assertSee('Formats')
            ->assertSee('Delimiters are detected.')
            ->assertSee('Not run')
            ->assertSee('href="/reference/data_save"', false)
            ->assertSee('href="http://r.framework.test/reference/data_read"', false)
            ->assertSee('data_read (R)')
            ->assertSee('Added in 0.1.0.');
    }

    public function test_command_options_show_their_environment_variable(): void
    {
        $this->import('cli');

        $this->docs('cli', '/reference/project-bootstrap')
            ->assertOk()
            ->assertSee('Options')
            ->assertSee('FW_PATH');
    }

    public function test_deprecated_entries_say_what_to_use_instead(): void
    {
        $this->import('py');

        $this->docs('py', '/reference/data_load')
            ->assertOk()
            ->assertSee('Deprecated')
            ->assertSeeInOrder(['Use', 'href="/reference/data_read"', 'data_read', 'instead.'], false);

        $this->docs('py', '/reference')->assertSeeInOrder(['data_load()', 'Deprecated']);
        $this->docs('py', '/reference/data_read')->assertDontSee('>Deprecated<', false);
    }

    public function test_names_and_aliases_redirect_to_the_entry(): void
    {
        $this->import('py');
        $this->import('cli');

        $this->docs('py', '/reference/Audit')->assertRedirect('/reference/AuditResult');
        $this->docs('cli', '/reference/project bootstrap')->assertRedirect('/reference/project-bootstrap');
        $this->docs('py', '/reference/nothing_here')->assertNotFound();
    }

    public function test_reference_index_lists_common_entries_first(): void
    {
        $this->import('py');

        $this->docs('py', '/reference')
            ->assertOk()
            ->assertSeeInOrder(['Data', 'Common', 'data_read()', 'More', 'data_load()', 'data_save()', 'Results', 'AuditResult']);
    }

    public function test_search_covers_guides_headings_names_summaries_and_parameters(): void
    {
        $this->import('py');

        $this->docs('py', '/search?q=data_read')->assertOk()->assertSeeInOrder(['Reference', 'data_read']);
        // An h3 heading
        $this->docs('py', '/search?q=with uv')->assertSee('/guides/installation#with-uv', false);
        // A guide description
        $this->docs('py', '/search?q=declarative')->assertSee('Data Management');
        // A parameter name
        $this->docs('py', '/search?q=delimiter')->assertSee('/reference/data_read', false);
        // A summary
        $this->docs('py', '/search?q=audit')->assertSee('AuditResult');
        $this->docs('py', '/search?q=zzzz')->assertSee('Nothing matches');

        $index = $this->docs('py', '/search.json')->assertOk()->json();
        $this->assertContains('guide', array_column($index, 'type'));
        $this->assertContains('heading', array_column($index, 'type'));
        $this->assertContains('entry', array_column($index, 'type'));
        $entry = collect($index)->firstWhere('url', '/reference/data_read');
        $this->assertStringContainsString('delimiter', $entry['keywords']);
    }

    public function test_dark_mode_uses_the_app_theme_script(): void
    {
        $this->import('py');

        $this->docs('py')
            ->assertSee('window.fwTheme', false)
            ->assertSee('data-docs-theme', false);
    }
}
