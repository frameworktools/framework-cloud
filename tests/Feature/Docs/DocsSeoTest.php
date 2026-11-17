<?php

namespace Tests\Feature\Docs;

class DocsSeoTest extends DocsTestCase
{
    public function test_each_docs_host_has_its_own_robots_and_sitemap(): void
    {
        $this->import('py');
        $host = 'http://'.config('framework.docs_hosts.py');

        $this->docs('py', '/robots.txt')->assertOk()->assertSee("Sitemap: {$host}/sitemap.xml", false);

        $this->docs('py', '/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee("<loc>{$host}</loc>", false)
            ->assertSee("<loc>{$host}/reference/data_read</loc>", false)
            ->assertSee("<loc>{$host}/reference</loc>", false)
            ->assertDontSee('/search', false);
    }

    public function test_a_reference_entry_is_a_tech_article_with_breadcrumbs(): void
    {
        $this->import('py');

        $response = $this->docs('py', '/reference/data_read')->assertOk()
            ->assertSee('<link rel="canonical" href="http://'.config('framework.docs_hosts.py').'/reference/data_read">', false)
            ->assertSee('<meta property="og:type" content="article">', false);

        $graph = $this->graph($response->getContent());
        $this->assertSame('data_read()', collect($graph)->firstWhere('@type', 'TechArticle')['headline']);
        $this->assertSame(['Framework for Python', 'Reference', 'data_read()'],
            array_column(collect($graph)->firstWhere('@type', 'BreadcrumbList')['itemListElement'], 'name'));
    }

    public function test_search_results_are_not_indexed(): void
    {
        $this->import('py');

        $this->docs('py', '/search?q=data')->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('rel="canonical"', false);
    }

    public function test_an_unpublished_docs_site_has_no_sitemap(): void
    {
        $this->docs('cli', '/sitemap.xml')->assertNotFound();
    }

    /** @return list<array<string, mixed>> */
    private function graph(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        $json = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $json['@context']);

        return $json['@graph'];
    }
}
