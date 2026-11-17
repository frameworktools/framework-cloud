<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Report;
use App\Models\User;
use App\Support\Docs\CloudGuide;
use App\Support\Docs\Sites;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * robots.txt and sitemap.xml. The app's own host lists its pages, the Cloud
 * guide, and public work with no password; each docs host lists its guides
 * and reference entries. Unlisted and private work never appears.
 */
class SitemapController extends Controller
{
    /** The protocol's limit for one sitemap file */
    private const LIMIT = 50000;

    public function robots(Request $request): Response
    {
        $body = "User-agent: *\nDisallow:\n\nSitemap: {$request->root()}/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function index(Request $request): Response
    {
        $xml = Cache::remember('sitemap:'.$request->root(), now()->addHour(), fn () => self::xml(self::cloudUrls()));

        return self::respond($xml);
    }

    public function docs(Request $request, string $product): Response
    {
        $xml = Cache::remember("sitemap:{$product}:".$request->root(), now()->addHour(), fn () => self::xml(self::docsUrls($product)));

        return self::respond($xml);
    }

    /** @return list<array{loc: string, lastmod?: string|null}> */
    private static function cloudUrls(): array
    {
        $urls = [];
        foreach (['home', 'self-hosting', 'terms', 'privacy'] as $name) {
            $urls[] = ['loc' => route($name)];
        }
        foreach (array_keys(collect(CloudGuide::PAGES)->collapse()->all()) as $page) {
            $urls[] = ['loc' => url(CloudGuide::path($page))];
        }

        User::whereNotNull('handle')->where('profile_public', true)->orderBy('id')
            ->each(function (User $user) use (&$urls) {
                $urls[] = ['loc' => route('profile', $user->handle), 'lastmod' => $user->updated_at];
            });

        Blueprint::with('latestVersion')->orderBy('id')->get()
            ->filter(fn (Blueprint $b) => $b->latestVersion && ($b->visibility === 'public' || $b->isSystem()))
            ->each(function (Blueprint $blueprint) use (&$urls) {
                $urls[] = ['loc' => $blueprint->publicUrl(), 'lastmod' => $blueprint->updated_at];
            });

        Document::where('visibility', 'public')->whereNull('password_hash')->whereNull('merged_into_id')
            ->where(fn ($q) => $q->whereNull('driver')->orWhere('driver', 'cloud'))
            ->whereHas('project')->with('project.user')->orderBy('id')
            ->each(function (Document $document) use (&$urls) {
                $urls[] = ['loc' => $document->shareUrl(), 'lastmod' => $document->updated_at];
            });

        Collection::where('visibility', 'public')->whereNull('password_hash')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('project')->orderBy('id')
            ->each(function (Collection $collection) use (&$urls) {
                $urls[] = ['loc' => $collection->shareUrl(), 'lastmod' => $collection->updated_at];
            });

        Report::where('visibility', 'public')->whereNull('password_hash')
            ->whereHas('project')->whereHas('versions')->orderBy('id')
            ->each(function (Report $report) use (&$urls) {
                $urls[] = ['loc' => $report->shareUrl(), 'lastmod' => $report->updated_at];
            });

        return array_slice($urls, 0, self::LIMIT);
    }

    /** @return list<array{loc: string, lastmod?: string|null}> */
    private static function docsUrls(string $product): array
    {
        $site = Sites::site($product) ?? abort(404);

        $urls = [['loc' => url('/')]];
        foreach (array_keys($site['guides']) as $id) {
            $urls[] = ['loc' => url(Sites::guideUrl($site, (string) $id))];
        }
        if ($site['categories'] !== []) {
            $urls[] = ['loc' => url('/reference')];
        }
        foreach (array_keys($site['entries']) as $id) {
            $urls[] = ['loc' => url('/reference/'.$id)];
        }

        return array_slice($urls, 0, self::LIMIT);
    }

    /** @param  list<array{loc: string, lastmod?: mixed}>  $urls */
    private static function xml(array $urls): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $out .= '  <url><loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
            if (! empty($url['lastmod'])) {
                $out .= '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>';
            }
            $out .= "</url>\n";
        }

        return $out.'</urlset>'."\n";
    }

    private static function respond(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
