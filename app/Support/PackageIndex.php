<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds R and Python packages for the package lists, as the Framework GUI
 * did: CRAN and Bioconductor by name from their package indexes, cached for
 * a day, and PyPI by exact name, since PyPI has no search API.
 */
class PackageIndex
{
    private const INDEXES = [
        'cran' => 'https://cloud.r-project.org/src/contrib/PACKAGES.gz',
        'bioc' => 'https://bioconductor.org/packages/release/bioc/src/contrib/PACKAGES.gz',
    ];

    /**
     * Up to 20 packages whose names contain the query, names that start with it first.
     *
     * @return list<array{name: string, version: string, title: ?string, source: string}>
     */
    public static function search(string $source, string $query, ?string $language = null): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        if ($source === 'github') {
            return self::github($query, $language);
        }

        if ($source === 'pypi') {
            return self::pypi($query);
        }

        $needle = mb_strtolower($query);
        $matches = [];
        foreach (self::index($source) as $name => $version) {
            $at = strpos(strtolower($name), $needle);
            if ($at !== false) {
                $matches[] = ['name' => $name, 'version' => $version, 'title' => null, 'source' => $source, 'rank' => [$at === 0 ? 0 : 1, strlen($name)]];
            }
        }
        usort($matches, fn ($a, $b) => $a['rank'] <=> $b['rank']);

        return array_map(fn ($m) => array_diff_key($m, ['rank' => true]), array_slice($matches, 0, 20));
    }

    private static function github(string $query, ?string $language): array
    {
        return Cache::remember('package-index:github:'.hash('sha256', $query.':'.$language), now()->addMinutes(5), function () use ($query, $language) {
            try {
                $client = Http::acceptJson()->withHeaders(['User-Agent' => 'Framework-Cloud'])->timeout(10);
                if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $query)) {
                    $response = $client->get('https://api.github.com/repos/'.$query);
                    $items = $response->successful() ? [$response->json()] : [];
                } else {
                    $response = $client->get('https://api.github.com/search/repositories', [
                        'q' => $query.($language ? ' language:'.($language === 'r' ? 'R' : 'Python') : ''),
                        'per_page' => 10,
                    ]);
                    $items = $response->successful() ? $response->json('items', []) : [];
                }

                return collect($items)->filter(fn ($item) => ! empty($item['full_name']))->map(fn ($item) => [
                    'name' => $item['full_name'], 'version' => '', 'title' => $item['description'] ?? null, 'source' => 'github',
                ])->all();
            } catch (Throwable) {
                return [];
            }
        }) ?: [];
    }

    /** @return array<string, string> package name => version */
    private static function index(string $source): array
    {
        if (! isset(self::INDEXES[$source])) {
            return [];
        }

        return Cache::remember("package-index:{$source}", now()->addDay(), function () use ($source) {
            try {
                $body = Http::timeout(20)->get(self::INDEXES[$source])->throw()->body();
                $text = gzdecode($body) ?: '';
            } catch (Throwable) {
                return [];
            }

            preg_match_all('/^Package:\s*(\S+)\s*\nVersion:\s*(\S+)/m', $text, $found, PREG_SET_ORDER);

            return collect($found)->mapWithKeys(fn ($m) => [$m[1] => $m[2]])->all();
        }) ?: [];
    }

    /** @return list<array{name: string, version: string, title: ?string, source: string}> */
    private static function pypi(string $name): array
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
            return [];
        }

        $info = Cache::remember('package-index:pypi:'.strtolower($name), now()->addDay(), function () use ($name) {
            try {
                $response = Http::timeout(10)->get("https://pypi.org/pypi/{$name}/json");

                return $response->successful() ? $response->json('info') : false;
            } catch (Throwable) {
                return false;
            }
        });

        return $info ? [['name' => $info['name'], 'version' => (string) $info['version'], 'title' => $info['summary'] ?: null, 'source' => 'pypi']] : [];
    }
}
