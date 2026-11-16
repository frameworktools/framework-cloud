<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The Framework Cloud guide at /docs, rendered from docs/guide/*.md so the
 * same pages read well on GitHub and on every server. Pages render with the
 * docs sites' Markdown (Markdown::render) and show in their layout, as a site
 * shaped like a compiled one (site()) with the product "cloud".
 */
class CloudGuide
{
    public const PRODUCT = 'cloud';

    public const TITLE = 'Framework Cloud';

    public const REPOSITORY = 'https://github.com/frameworktools/framework-cloud';

    /** Bump when rendered pages change shape, so cached pages are rebuilt */
    private const VERSION = 2;

    /** Sidebar order: section => [slug => title] */
    public const PAGES = [
        'Framework Cloud' => [
            'index' => 'Overview',
            'getting-started' => 'Getting started',
            'signing-in' => 'Signing in',
            'projects-and-blueprints' => 'Projects and blueprints',
            'github' => 'GitHub integration',
            'skill-sync' => 'Skill sync',
            'publishing' => 'Publishing artifacts',
            'ai-apps' => 'AI apps',
            'stata' => 'Framework for Stata',
        ],
        'Self-hosting' => [
            'self-hosting' => 'Self-hosting overview',
            'install-docker' => 'Docker installation',
            'running-at-home' => 'Local installation',
            'install-manual' => 'Manual installation',
            'server-setup' => 'Server setup',
            'configuration' => 'Configuration reference',
            'maintenance' => 'Maintenance',
        ],
    ];

    public static function title(string $page): ?string
    {
        return collect(self::PAGES)->collapse()->get($page);
    }

    public static function path(string $page): string
    {
        return $page === 'index' ? '/docs' : '/docs/'.$page;
    }

    /**
     * The guide as the docs layout reads a compiled site: sections of guides,
     * no reference, and each guide's own URL under /docs.
     *
     * @return array<string, mixed>
     */
    public static function site(): array
    {
        $sections = [];
        $guides = [];
        foreach (self::PAGES as $section => $pages) {
            $sections[] = ['id' => Str::slug($section), 'title' => $section, 'reference' => false, 'guides' => array_keys($pages)];
            foreach ($pages as $page => $title) {
                $guides[$page] = ['id' => $page, 'title' => $title, 'description' => '', 'url' => self::path($page)];
            }
        }

        return [
            'title' => self::TITLE,
            'tagline' => 'Documentation for Framework Cloud and self-hosted servers.',
            'repository' => self::REPOSITORY,
            'home' => '/docs',
            'search_index' => '/docs/search.json',
            'sections' => $sections,
            'guides' => $guides,
            'categories' => [],
            'entries' => [],
            'names' => [],
        ];
    }

    /**
     * A page as the docs guide view reads one, or null when it is not in the
     * guide. The title is the sidebar's name (the browser tab's), and the
     * heading is the Markdown's own h1.
     *
     * @return array<string, mixed>|null
     */
    public static function page(string $page): ?array
    {
        $rendered = self::rendered($page);
        if ($rendered === null) {
            return null;
        }

        $order = collect(self::PAGES)->collapse();
        $ids = $order->keys()->all();
        $i = array_search($page, $ids, true);
        $pointer = fn (?string $id) => $id === null ? null : ['id' => $id, 'title' => $order[$id]];
        $section = collect(self::PAGES)->search(fn ($pages) => isset($pages[$page]));

        return [
            'id' => $page,
            'title' => $page === 'index' ? 'Documentation' : $order[$page],
            'heading' => $rendered['heading'] ?? $order[$page],
            'description' => '',
            'section' => Str::slug($section),
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
            'prev' => $pointer($ids[$i - 1] ?? null),
            'next' => $pointer($ids[$i + 1] ?? null),
            'edit_url' => self::REPOSITORY."/blob/main/docs/guide/{$page}.md",
        ];
    }

    /** A page's HTML, or null when it is not in the guide */
    public static function html(string $page): ?string
    {
        return self::rendered($page)['html'] ?? null;
    }

    /**
     * The page's h1 text, its HTML without the h1, and its table of contents.
     * Cached until the file changes, since coloring code takes a moment.
     *
     * @return array{heading: string|null, html: string, toc: list<array{level: int, id: string, text: string}>}|null
     */
    private static function rendered(string $page): ?array
    {
        $file = base_path("docs/guide/{$page}.md");
        if (self::title($page) === null || ! is_file($file)) {
            return null;
        }

        $key = 'cloud-guide:'.self::VERSION.":{$page}:".md5_file($file);

        return Cache::rememberForever($key, function () use ($file) {
            $rendered = Markdown::render(file_get_contents($file));
            $html = $rendered['html'];

            // The layout shows the title, so the page's own h1 comes out
            $heading = null;
            $html = preg_replace_callback('#^\s*<h1\b[^>]*>(.*?)</h1>\s*#s', function ($m) use (&$heading) {
                $heading = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5);

                return '';
            }, $html, 1);

            // Links between guide pages point at .md files so they work on
            // GitHub; on the site they point at /docs
            $html = preg_replace_callback(
                '/href="(?!https?:|#|\/)([a-z0-9-]+)\.md(#[^"]*)?"/',
                fn ($m) => 'href="'.self::path($m[1]).($m[2] ?? '').'"',
                $html,
            );

            $html = self::languageTabs($html);
            $html = str_replace(['<table>', '</table>'], ['<div class="overflow-x-auto"><table>', '</table></div>'], $html);

            return ['heading' => $heading, 'html' => $html, 'toc' => $rendered['toc']];
        });
    }

    /**
     * An R code block followed directly by a Python one becomes a pair of
     * tabs. On GitHub the Markdown shows both blocks, R first.
     */
    public static function languageTabs(string $html): string
    {
        $block = fn (string $language) => '(<pre class="(?:phiki )?language-'.$language.'"[^>]*>.*?</pre>)';

        return preg_replace_callback(
            '#'.$block('r').'\s*'.$block('python').'#s',
            fn ($m) => '<div class="code-tabs" data-code-tabs>'
                .'<div class="code-tabs-bar" role="tablist">'
                .'<button type="button" role="tab" data-lang="r" aria-selected="true">R</button>'
                .'<button type="button" role="tab" data-lang="python" aria-selected="false">Python</button>'
                .'</div>'
                .'<div data-lang-panel="r">'.$m[1].'</div>'
                .'<div data-lang-panel="python" hidden>'.$m[2].'</div>'
                .'</div>',
            $html,
        );
    }

    /**
     * The search items for every page, with paths under /docs. Rebuilt when
     * a guide file changes.
     *
     * @return list<array<string, string>>
     */
    public static function search(): array
    {
        $files = glob(base_path('docs/guide/*.md')) ?: [];
        $stamp = md5(implode('|', array_map(fn ($f) => $f.':'.filemtime($f), $files)));

        return Cache::rememberForever('docs-search:'.Search::VERSION.':cloud:'.self::VERSION.":{$stamp}", function () {
            $items = [];
            foreach (self::PAGES as $section => $pages) {
                foreach ($pages as $page => $title) {
                    $html = self::html($page);
                    if ($html !== null) {
                        array_push($items, ...Search::page($title, self::path($page), '', $section, $html));
                    }
                }
            }

            return $items;
        });
    }
}
