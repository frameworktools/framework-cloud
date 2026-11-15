<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/**
 * The Framework Cloud guide at /docs, rendered from docs/guide/*.md so the
 * same pages read well on GitHub and on every server.
 */
class CloudGuide
{
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

    /** A page's HTML, or null when it is not in the guide */
    public static function html(string $page): ?string
    {
        $file = base_path("docs/guide/{$page}.md");
        if (self::title($page) === null || ! is_file($file)) {
            return null;
        }

        $html = Str::markdown(file_get_contents($file), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'none',
            ],
        ], [new HeadingPermalinkExtension]);

        // Links between guide pages point at .md files so they work on
        // GitHub; on the site they point at /docs
        return preg_replace_callback(
            '/href="(?!https?:|#|\/)([a-z0-9-]+)\.md(#[^"]*)?"/',
            fn ($m) => 'href="'.url(self::path($m[1])).($m[2] ?? '').'"',
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

        return Cache::rememberForever('docs-search:'.Search::VERSION.":cloud:{$stamp}", function () {
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
