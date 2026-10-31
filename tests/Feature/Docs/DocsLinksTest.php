<?php

namespace Tests\Feature\Docs;

use App\Support\Docs\Links;
use App\Support\Docs\Markdown;
use App\Support\Docs\Products;
use Tests\TestCase;

class DocsLinksTest extends TestCase
{
    private function links(array $others = []): Links
    {
        return new Links(
            product: 'py',
            guides: ['setup' => ['install', 'configure']],
            entries: ['data_read' => ['usage'], 'project-bootstrap' => []],
            names: ['data_read' => 'data_read', 'data_load' => 'data_read', 'project bootstrap' => 'project-bootstrap'],
            images: ['catalog.png', 'gui/setup.png'],
            others: $others,
        );
    }

    public function test_link_forms_become_urls(): void
    {
        $links = $this->links();

        $this->assertSame('/guides/setup', $links->resolve('guide:setup'));
        $this->assertSame('/guides/setup#configure', $links->resolve('guide:setup#configure'));
        $this->assertSame('/reference/data_read', $links->resolve('ref:data_read'));
        $this->assertSame('/reference/data_read#usage', $links->resolve('ref:data_read#usage'));
        $this->assertSame('/guides/setup', $links->resolve('py:guide:setup'));
        $this->assertSame('fwdocs://r/guides/setup', $links->resolve('r:guide:setup'));
        $this->assertSame('fwdocs://cli/reference/project-bootstrap', $links->resolve('cli:ref:project-bootstrap'));
        $this->assertSame('#install', $links->resolve('#install'));
        $this->assertSame('https://pola.rs', $links->resolve('https://pola.rs'));
        $this->assertSame('/images/catalog.png', $links->resolve('images/catalog.png'));
        $this->assertSame('/images/gui/setup.png', $links->resolve('./images/gui/setup.png'));
        $this->assertSame([], $links->warnings);
    }

    public function test_unresolved_targets_are_recorded(): void
    {
        $links = $this->links();
        $links->context = 'guides/a.md';

        $links->resolve('guide:nope');
        $links->resolve('ref:nope');
        $links->resolve('guide:setup#nope');
        $links->resolve('images/nope.png');
        $links->resolve('setup.md');

        $this->assertSame([
            'guides/a.md: guide:nope does not match a guide.',
            'guides/a.md: ref:nope does not match a reference entry.',
            'guides/a.md: guide:setup#nope names a heading the page does not have.',
            'guides/a.md: images/nope.png is not in images/.',
            'guides/a.md: setup.md is not a link form docs sites use (guide:, ref:, images/, or a full URL).',
        ], $links->warnings);
    }

    public function test_other_products_are_checked_when_imported(): void
    {
        $links = $this->links(['cli' => ['guides' => ['install' => []], 'entries' => ['project-bootstrap' => []]]]);

        $this->assertSame('fwdocs://cli/guides/install', $links->resolve('cli:guide:install'));
        $links->resolve('cli:guide:missing');
        // r is not imported here, so its links pass unchecked
        $links->resolve('r:guide:anything');

        $this->assertSame(['cli:guide:missing does not match a guide.'], $links->warnings);
    }

    public function test_see_also_takes_names_aliases_and_link_targets(): void
    {
        $links = $this->links();

        $this->assertSame(['title' => 'data_read', 'url' => '/reference/data_read'], $links->seeAlso('data_read()'));
        $this->assertSame(['title' => 'data_load', 'url' => '/reference/data_read'], $links->seeAlso('data_load'));
        $this->assertSame(['title' => 'project bootstrap', 'url' => '/reference/project-bootstrap'], $links->seeAlso('project bootstrap'));
        $this->assertSame(['title' => 'data_read (R)', 'url' => 'fwdocs://r/reference/data_read'], $links->seeAlso('r:ref:data_read'));
        $this->assertSame([], $links->warnings);
    }

    public function test_markdown_links_are_rewritten_and_alerts_become_callouts(): void
    {
        $html = Markdown::render(implode("\n", [
            '## Install it',
            '',
            'Read [setup](guide:setup) and [data_read()](ref:data_read).',
            '',
            '> [!TIP]',
            '> Use `uv`.',
            '',
            '> [!CAUTION] This deletes files.',
            '',
            '> A plain quote.',
        ]), $this->links())['html'];

        $this->assertStringContainsString('<h2 id="install-it">Install it</h2>', $html);
        $this->assertStringContainsString('<a href="/guides/setup">setup</a>', $html);
        $this->assertStringContainsString('<a href="/reference/data_read">data_read()</a>', $html);
        $this->assertStringContainsString('<blockquote class="callout callout-tip">', $html);
        $this->assertStringContainsString('<p class="callout-title">Tip</p>', $html);
        $this->assertStringContainsString('<p class="callout-title">Caution</p>', $html);
        $this->assertStringContainsString('This deletes files.', $html);
        $this->assertStringContainsString("<blockquote>\n<p>A plain quote.</p>", $html);
        $this->assertStringNotContainsString('[!TIP]', $html);
    }

    public function test_raw_html_and_unsafe_links_are_dropped(): void
    {
        $html = Markdown::render("<script>alert(1)</script>\n\n[x](javascript:alert(1))")['html'];

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_placeholders_become_the_other_hosts(): void
    {
        $this->get('http://'.config('framework.docs_hosts.py').'/');

        $this->assertSame(
            '<a href="http://cli.framework.test/reference/project-bootstrap">',
            Products::absolutize('<a href="fwdocs://cli/reference/project-bootstrap">'),
        );
    }
}
