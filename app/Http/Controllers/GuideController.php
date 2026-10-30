<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/**
 * The Framework Cloud guide at /docs, rendered from docs/guide/*.md so the
 * same pages read well on GitHub and on every server.
 */
class GuideController extends Controller
{
    /** Sidebar order: section => [slug => title] */
    public const PAGES = [
        'Using Framework Cloud' => [
            'index' => 'Overview',
            'getting-started' => 'Getting started',
            'signing-in' => 'Signing in',
            'projects-and-blueprints' => 'Projects and blueprints',
            'github' => 'GitHub integration',
            'publishing' => 'Publishing notebooks',
        ],
        'Running your own server' => [
            'self-hosting' => 'Self-hosting overview',
            'install-docker' => 'Install with Docker',
            'running-at-home' => 'Run it on your own computer',
            'install-manual' => 'Install without Docker',
            'server-setup' => 'Server setup',
            'configuration' => 'Configuration reference',
            'maintenance' => 'Maintenance',
        ],
    ];

    public function show(string $page = 'index'): View
    {
        $title = collect(self::PAGES)->collapse()->get($page);
        $path = base_path("docs/guide/{$page}.md");

        abort_unless($title !== null && is_file($path), 404);

        $html = Str::markdown(file_get_contents($path), [
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
        $html = preg_replace_callback(
            '/href="(?!https?:|#|\/)([a-z0-9-]+)\.md(#[^"]*)?"/',
            fn ($m) => 'href="'.url($m[1] === 'index' ? '/docs' : '/docs/'.$m[1]).($m[2] ?? '').'"',
            $html,
        );

        return view('guide', [
            'title' => $title,
            'page' => $page,
            'pages' => self::PAGES,
            'html' => $html,
        ]);
    }
}
