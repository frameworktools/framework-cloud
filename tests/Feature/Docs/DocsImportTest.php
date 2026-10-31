<?php

namespace Tests\Feature\Docs;

use App\Support\Docs\Importer;
use App\Support\Docs\InvalidSite;
use App\Support\Docs\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class DocsImportTest extends DocsTestCase
{
    public function test_a_valid_archive_imports(): void
    {
        $archive = $this->archive($this->fixture('py'));

        $this->artisan('fw:docs-import', ['product' => 'py', 'source' => $archive])
            ->expectsOutputToContain('Imported py: 3 guides, 4 reference entries, and 1 images')
            ->assertSuccessful();

        Store::flush();
        $release = Store::release('py');
        $this->assertNotNull($release);
        $this->assertFileExists("{$release}/site.json");
        $this->assertFileExists("{$release}/search.json");
        $this->assertFileExists("{$release}/images/catalog.png");
        $this->assertSame('0.1.0.dev1', Store::site('py')['version']);

        $this->docs('py', '/guides/installation')->assertOk();
    }

    public function test_a_folder_imports_directly(): void
    {
        $this->artisan('fw:docs-import', ['product' => 'cli', 'source' => $this->fixture('cli')])->assertSuccessful();

        Store::flush();
        $this->assertSame('fw', Store::site('cli')['title']);
    }

    public function test_reference_json_is_checked_against_the_schema(): void
    {
        $dir = $this->fixture('py');
        $reference = json_decode(File::get("{$dir}/reference.json"), true);
        $reference['entries'][0]['keywords'] = 'not in the schema';
        $reference['entries'][1]['id'] = 'data save';
        File::put("{$dir}/reference.json", json_encode($reference));

        $this->artisan('fw:docs-import', ['product' => 'py', 'source' => $this->archive($dir)])
            ->expectsOutputToContain('The docs site was not imported')
            ->expectsOutputToContain('reference.json at /entries/0')
            ->expectsOutputToContain('reference.json at /entries/1/id')
            ->assertFailed();

        Store::flush();
        $this->assertNull(Store::release('py'));
    }

    public function test_site_yml_and_front_matter_are_checked(): void
    {
        $dir = $this->fixture('py');
        File::put("{$dir}/site.yml", "product: cli\ntitle: Framework for Python\nsections:\n  - id: getting_started\n    title: Getting Started\n  - id: reference\n    title: Reference\n    reference: true\n");
        File::put("{$dir}/guides/broken.md", "---\nid: broken\ntitle: Broken\nsection: nowhere\nposition: first\n---\nText.\n");

        try {
            (new Importer)->import('py', $dir);
            $this->fail('The import should have failed.');
        } catch (InvalidSite $e) {
            $problems = implode("\n", $e->problems);
            $this->assertStringContainsString('site.yml is for product "cli", not "py".', $problems);
            $this->assertStringContainsString('guides/broken.md needs a section from site.yml', $problems);
            $this->assertStringContainsString('guides/broken.md needs a whole-number position.', $problems);
            // data-management.md is in a section this site.yml no longer has
            $this->assertStringContainsString('guides/data-management.md needs a section', $problems);
        }

        $dir = $this->fixture('py');
        File::put("{$dir}/site.yml", "product: py\ntitle: Framework for Python\nsections: []\nextra: true\n");
        $this->expectException(InvalidSite::class);
        (new Importer)->import('py', $dir);
    }

    public function test_an_archive_needs_a_single_docs_site_folder(): void
    {
        $dir = $this->fixture('py');
        File::moveDirectory($dir, dirname($dir).'/site');

        $this->artisan('fw:docs-import', ['product' => 'py', 'source' => $this->archive(dirname($dir).'/site')])
            ->expectsOutputToContain('single top folder, docs-site/')
            ->assertFailed();
    }

    public function test_unresolved_links_are_reported_as_warnings(): void
    {
        $dir = $this->fixture('py');
        File::append("{$dir}/guides/installation.md", implode("\n", [
            '',
            'See [nothing](guide:no-such-guide), [no entry](ref:no_such_entry),',
            '[no heading](guide:data-management#no-such-heading), [a file](other.md),',
            'and ![missing](images/missing.png).',
            '',
        ]));
        $reference = json_decode(File::get("{$dir}/reference.json"), true);
        $reference['entries'][1]['see_also'] = ['no_such_function'];
        $reference['entries'][2]['replaced_by'] = 'gone_too';
        File::put("{$dir}/reference.json", json_encode($reference));

        $this->artisan('fw:docs-import', ['product' => 'py', 'source' => $this->archive($dir)])
            ->expectsOutputToContain('guides/installation.md: guide:no-such-guide does not match a guide.')
            ->expectsOutputToContain('guides/installation.md: ref:no_such_entry does not match a reference entry.')
            ->expectsOutputToContain('guide:data-management#no-such-heading names a heading the page does not have.')
            ->expectsOutputToContain('other.md is not a link form docs sites use')
            ->expectsOutputToContain('images/missing.png is not in images/.')
            ->expectsOutputToContain('reference.json: data_save: see also "no_such_function" does not match an entry.')
            ->expectsOutputToContain('reference.json: data_load: replaced_by "gone_too" does not match an entry.')
            ->assertSuccessful();
    }

    public function test_links_to_another_imported_product_are_checked(): void
    {
        $this->import('cli');

        $dir = $this->fixture('py');
        File::append("{$dir}/guides/quick-start.md", "\nRun [bootstrap](cli:ref:project-bootstrap) or [this](cli:ref:project-nothing).\n");

        $importer = $this->import('py', $dir);

        $this->assertContains('guides/quick-start.md: cli:ref:project-nothing does not match a reference entry.', $importer->warnings);
        $this->assertEmpty(array_filter($importer->warnings, fn ($w) => str_contains($w, 'cli:ref:project-bootstrap')));
    }

    public function test_an_import_swaps_in_the_new_release_and_keeps_the_previous_one(): void
    {
        $this->import('py');
        $first = Store::release('py');

        $dir = $this->fixture('py');
        File::put("{$dir}/guides/quick-start.md", str_replace('title: Quick Start', 'title: First Steps', File::get("{$dir}/guides/quick-start.md")));
        $this->import('py', $dir);
        $second = Store::release('py');

        $this->assertNotSame($first, $second);
        $this->assertDirectoryExists($first);
        $this->docs('py', '/guides/quick-start')->assertSee('First Steps');

        // A failed import leaves the live site alone and no partial release behind
        $broken = $this->fixture('py');
        File::put("{$broken}/reference.json", '{ not json');
        try {
            $this->import('py', $broken);
        } catch (InvalidSite) {
        }
        $this->assertSame($second, Store::release('py'));
        $this->docs('py', '/guides/quick-start')->assertSee('First Steps');

        // A third import drops the oldest release
        $this->import('py');
        $this->assertDirectoryDoesNotExist($first);
        $this->assertDirectoryExists($second);
        $this->assertCount(2, File::directories("{$this->docsPath}/py/releases"));
        $this->assertSame([], glob("{$this->docsPath}/py/releases/.staging-*"));
        $this->assertSame([], glob("{$this->docsPath}/py/current.*"));
    }

    public function test_release_falls_back_to_the_newest_pre_release_with_the_asset(): void
    {
        $archive = File::get($this->archive($this->fixture('py')));

        Http::fake([
            'https://api.github.com/repos/frameworktools/framework-py/releases*' => Http::response([
                ['tag_name' => 'v0.1.0.dev2', 'prerelease' => true, 'draft' => true, 'assets' => [['name' => 'docs-site.tar.gz', 'browser_download_url' => 'https://github.com/frameworktools/framework-py/releases/download/v0.1.0.dev2/docs-site.tar.gz']]],
                ['tag_name' => 'v0.1.0.dev1', 'prerelease' => true, 'assets' => [['name' => 'docs-site.tar.gz', 'browser_download_url' => 'https://github.com/frameworktools/framework-py/releases/download/v0.1.0.dev1/docs-site.tar.gz']]],
                ['tag_name' => 'v0.1.0.dev0', 'prerelease' => true, 'assets' => []],
            ]),
            'https://github.com/frameworktools/framework-py/releases/download/v0.1.0.dev1/docs-site.tar.gz' => Http::response($archive),
        ]);

        $this->artisan('fw:docs-import', ['product' => 'py', '--release' => true])
            ->expectsOutputToContain('Release v0.1.0.dev1 (a pre-release).')
            ->expectsOutputToContain('Imported py')
            ->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/download/v0.1.0.dev1/'));
        Store::flush();
        $this->assertNotNull(Store::release('py'));
    }

    public function test_release_never_takes_a_pre_release_when_told_not_to(): void
    {
        config(['framework.docs_repositories.py.prerelease' => 'never']);
        Http::fake([
            'https://api.github.com/*' => Http::response([
                ['tag_name' => 'v0.1.0.dev1', 'prerelease' => true, 'assets' => [['name' => 'docs-site.tar.gz', 'browser_download_url' => 'https://example.test/a']]],
            ]),
        ]);

        $this->artisan('fw:docs-import', ['product' => 'py', '--release' => true])
            ->expectsOutputToContain('No release of frameworktools/framework-py has docs-site.tar.gz outside pre-releases.')
            ->assertFailed();
    }

    public function test_release_choice_prefers_stable_releases(): void
    {
        $asset = [['name' => 'docs-site.tar.gz', 'browser_download_url' => 'https://example.test/x']];
        $releases = [
            ['tag_name' => 'v2.1.0-rc1', 'prerelease' => true, 'assets' => $asset],
            ['tag_name' => 'v2.0.1', 'prerelease' => false, 'assets' => []],
            ['tag_name' => 'v2.0.0', 'prerelease' => false, 'assets' => $asset],
        ];

        $this->assertSame('v2.0.0', Importer::pickRelease($releases, 'fallback')['tag_name']);
        $this->assertSame('v2.0.0', Importer::pickRelease($releases, 'never')['tag_name']);
        $this->assertSame('v2.1.0-rc1', Importer::pickRelease($releases, 'always')['tag_name']);
        $this->assertNull(Importer::pickRelease([$releases[1]]));
    }

    public function test_the_current_r_guides_can_be_imported(): void
    {
        $this->artisan('fw:docs-import', ['product' => 'r', '--legacy' => true])
            ->expectsOutputToContain('Imported r: 26 guides, 102 reference entries')
            ->assertSuccessful();

        Store::flush();
        $this->assertNotNull(Store::release('r'));
        $this->docs('r', '/guides/data-management')->assertOk()->assertSee('href="/guides/', false);
        $this->docs('r', '/images/setup-gui.png')->assertOk();
    }

    public function test_the_command_needs_exactly_one_source(): void
    {
        $this->artisan('fw:docs-import', ['product' => 'py'])->assertFailed();
        $this->artisan('fw:docs-import', ['product' => 'py', 'source' => 'x', '--release' => true])->assertFailed();
        $this->artisan('fw:docs-import', ['product' => 'py', '--legacy' => true])->assertFailed();
        $this->artisan('fw:docs-import', ['product' => 'go', 'source' => $this->fixture('py')])
            ->expectsOutputToContain('Unknown product "go"')
            ->assertFailed();
    }
}
