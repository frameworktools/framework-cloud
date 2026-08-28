<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Read-side for the R package docs site (r.framework.pub): markdown guides
 * from resources/docs/guides (ported from the old Statamic site) and the
 * function reference from docs.db (the R package's docs_export() output,
 * mounted read-only as the "docs" connection).
 */
class Docs
{
    public const SECTIONS = [
        'getting_started' => 'Getting Started',
        'project_types' => 'Project Types',
        'features' => 'Features',
    ];

    /** @return Collection<int, array{slug:string,title:string,section:string,position:int,description:string}> */
    public static function guides(): Collection
    {
        return Cache::remember('docs.guides', 300, function () {
            return collect(File::files(resource_path('docs/guides')))
                ->filter(fn ($f) => $f->getExtension() === 'md')
                ->map(function ($file) {
                    $meta = static::frontMatter($file->getContents());

                    return [
                        'slug' => $meta['id'] ?? $file->getFilenameWithoutExtension(),
                        'title' => $meta['title'] ?? Str::headline($file->getFilenameWithoutExtension()),
                        'section' => $meta['section'] ?? 'features',
                        'position' => (int) ($meta['position'] ?? 999),
                        'description' => $meta['description'] ?? '',
                    ];
                })
                ->sortBy([['section', 'asc'], ['position', 'asc']])
                ->values();
        });
    }

    /** @return array{meta: array, html: string}|null */
    public static function guide(string $slug): ?array
    {
        $file = collect(File::files(resource_path('docs/guides')))
            ->first(function ($f) use ($slug) {
                if ($f->getExtension() !== 'md') {
                    return false;
                }
                $meta = static::frontMatter($f->getContents());

                return ($meta['id'] ?? $f->getFilenameWithoutExtension()) === $slug;
            });

        if (! $file) {
            return null;
        }

        $content = $file->getContents();
        $body = preg_replace('/\A---\n.*?\n---\n/s', '', $content);

        return [
            'meta' => static::frontMatter($content),
            'html' => Str::markdown($body),
        ];
    }

    public static function categories(): Collection
    {
        return DB::connection('docs')->table('categories')->orderBy('position')->get();
    }

    public static function functionsByCategory(): Collection
    {
        return DB::connection('docs')->table('functions')
            ->where('is_exported', 1)
            ->orderBy('name')
            ->get()
            ->groupBy('category_id');
    }

    public static function fn(string $name): ?object
    {
        $fn = DB::connection('docs')->table('functions')->where('name', $name)->first();

        if (! $fn) {
            // Alias resolution (data_load -> data_read etc.)
            $alias = DB::connection('docs')->table('aliases')->where('alias', $name)->first();
            if ($alias) {
                $fn = DB::connection('docs')->table('functions')->where('id', $alias->function_id)->first();
            }
        }

        if (! $fn) {
            return null;
        }

        $fn->parameters = DB::connection('docs')->table('parameters')
            ->where('function_id', $fn->id)->orderBy('position')->get();
        $fn->examples = DB::connection('docs')->table('examples')
            ->where('function_id', $fn->id)->orderBy('position')->get();
        $fn->seealso = DB::connection('docs')->table('seealso')
            ->where('function_id', $fn->id)->get();
        $fn->category = $fn->category_id
            ? DB::connection('docs')->table('categories')->find($fn->category_id)
            : null;

        return $fn;
    }

    public static function search(string $query): Collection
    {
        if (blank($query)) {
            return collect();
        }

        // FTS5 with a sanitized prefix query; fall back to LIKE on syntax errors
        $term = '"'.str_replace('"', '', trim($query)).'"*';

        try {
            $functions = DB::connection('docs')->select(
                'SELECT f.name, f.title, f.description FROM functions_fts
                 JOIN functions f ON f.id = functions_fts.rowid
                 WHERE functions_fts MATCH ? AND f.is_exported = 1
                 ORDER BY rank LIMIT 20',
                [$term],
            );
        } catch (\Throwable) {
            $functions = DB::connection('docs')->table('functions')
                ->where('is_exported', 1)
                ->where(fn ($q) => $q->where('name', 'like', "%{$query}%")
                    ->orWhere('title', 'like', "%{$query}%"))
                ->limit(20)->get(['name', 'title', 'description'])->all();
        }

        $guides = static::guides()->filter(fn ($g) => Str::contains(
            Str::lower($g['title'].' '.$g['description']),
            Str::lower($query),
        ))->values();

        return collect(['functions' => collect($functions), 'guides' => $guides]);
    }

    protected static function frontMatter(string $content): array
    {
        if (! preg_match('/\A---\n(.*?)\n---\n/s', $content, $m)) {
            return [];
        }

        try {
            return Yaml::parse($m[1]) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
