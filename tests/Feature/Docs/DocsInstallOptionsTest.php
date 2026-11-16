<?php

namespace Tests\Feature\Docs;

class DocsInstallOptionsTest extends DocsTestCase
{
    public function test_a_site_can_offer_labeled_install_options(): void
    {
        $dir = $this->fixture('py');
        $yml = file_get_contents("{$dir}/site.yml");
        $options = "install:\n  - title: Install latest\n    note: Recommended.\n    code: uv add git+https://github.com/frameworktools/framework-py\n  - title: Install from PyPI\n    code: uv add framework-py\n";
        file_put_contents("{$dir}/site.yml", preg_replace('/^install:.*\n/m', $options, $yml));
        $this->import('py', $dir);

        // Each option has its own heading, note, colored command, and Copy button
        $this->docs('py')
            ->assertOk()
            ->assertSeeInOrder(['<h3 id="install-latest">Install latest</h3>', 'Recommended.', '<code id="docs-install"><span class="line">', '<h3 id="install-from-pypi">Install from PyPI</h3>', '<code id="docs-install-2"><span class="line">'], false)
            ->assertSee('data-copy="docs-install-2"', false)
            ->assertSee('href="#install-from-pypi"', false);
    }

    public function test_an_option_without_code_is_refused(): void
    {
        $dir = $this->fixture('py');
        $yml = file_get_contents("{$dir}/site.yml");
        file_put_contents("{$dir}/site.yml", preg_replace('/^install:.*\n/m', "install:\n  - title: Install latest\n", $yml));

        $this->expectException(\App\Support\Docs\InvalidSite::class);
        $this->import('py', $dir);
    }
}
