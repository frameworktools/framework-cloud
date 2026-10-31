<?php

namespace App\Support\Docs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * The R docs as they were before the R package shipped docs-site.tar.gz:
 * guides in resources/docs/guides (ported from the old Statamic site) and
 * the reference in docs.db (docs_export() output, the "docs" database
 * connection). export() writes them as a docs-site folder, so they go
 * through the same import as every other site. Until r has been imported,
 * the R site is built from them (see site()).
 */
class LegacyR
{
    public const GUIDES = 'docs/guides';

    public const IMAGES = 'docs/images';

    /** Write a docs-site folder (site.yml, guides/, images/, reference.json) to $dir */
    public static function export(string $dir): void
    {
        File::ensureDirectoryExists("{$dir}/guides");
        $hasReference = self::hasDatabase();

        $sections = [
            ['id' => 'getting_started', 'title' => 'Getting Started'],
            ['id' => 'project_types', 'title' => 'Project Types'],
            ['id' => 'features', 'title' => 'Features'],
        ];
        if ($hasReference) {
            $sections[] = ['id' => 'reference', 'title' => 'Reference', 'reference' => true];
        }

        File::put("{$dir}/site.yml", Yaml::dump([
            'product' => 'r',
            'title' => 'Framework for R',
            'tagline' => 'Structured data science projects in R',
            'repository' => 'https://github.com/frameworktools/framework',
            'install' => 'remotes::install_github("frameworktools/framework")',
            'sections' => $sections,
        ], 4, 2));

        foreach (File::files(resource_path(self::GUIDES)) as $file) {
            if ($file->getExtension() === 'md') {
                File::put("{$dir}/guides/".$file->getFilename(), self::guide($file->getContents()));
            }
        }

        if (is_dir(resource_path(self::IMAGES))) {
            File::copyDirectory(resource_path(self::IMAGES), "{$dir}/images");
        }

        if ($hasReference) {
            File::put("{$dir}/reference.json", json_encode(self::reference(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * The compiled R site, built from the legacy sources and cached on disk
     * until they change.
     *
     * @return array{site: array<string, mixed>, search: list<array<string, string>>}
     */
    public static function compiled(): array
    {
        static $memo = [];

        $fingerprint = self::fingerprint();
        if (isset($memo[$fingerprint])) {
            return $memo[$fingerprint];
        }

        $cache = storage_path("framework/cache/docs-legacy-r-{$fingerprint}.json");
        if (is_file($cache)) {
            return $memo[$fingerprint] = json_decode((string) file_get_contents($cache), true);
        }

        $tmp = storage_path('framework/cache/docs-legacy-r-'.Str::random(8));
        try {
            self::export($tmp);
            $compiled = (new Compiler)->compile($tmp, 'r');
        } finally {
            File::deleteDirectory($tmp);
        }

        $result = ['site' => $compiled['site'], 'search' => $compiled['search']];
        $partial = "{$cache}.".Str::random(6);
        File::ensureDirectoryExists(dirname($cache));
        File::put($partial, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($partial, $cache);

        // Older builds of the same sources
        foreach (glob(storage_path('framework/cache/docs-legacy-r-*.json')) ?: [] as $stale) {
            if ($stale !== $cache) {
                @unlink($stale);
            }
        }

        return $memo[$fingerprint] = $result;
    }

    public static function image(string $path): ?string
    {
        $file = resource_path(self::IMAGES.'/'.$path);

        return ! str_contains($path, '..') && is_file($file) ? $file : null;
    }

    /** Old links (/docs/{id}, /images/{file}) in the new forms, and no hand-made prev/next footer */
    public static function guide(string $markdown): string
    {
        $markdown = preg_replace('#\n-{3,}\s*\n+<div style="display: flex[^"]*">.*?</div>\s*\z#s', "\n", $markdown);
        $markdown = preg_replace('#\]\(/docs/([a-z0-9-]+)(\#[^)]*)?\)#', '](guide:$1$2)', $markdown);
        $markdown = preg_replace('#\]\(/reference/([A-Za-z0-9_.-]+)\)#', '](ref:$1)', $markdown);

        return preg_replace('#\]\(/images/#', '](images/', $markdown);
    }

    /** @return array<string, mixed> reference.json in the docs-reference.v1 shape */
    public static function reference(): array
    {
        $db = DB::connection('docs');
        $categories = $db->table('categories')->orderBy('position')->get();
        $categoryIds = $categories->mapWithKeys(fn ($c) => [$c->id => Str::slug($c->name)]);
        $functions = $db->table('functions')->where('is_exported', 1)->orderBy('name')->get();

        $byFunction = fn (string $table, string $order = 'position') => $db->table($table)->orderBy($order)->get()->groupBy('function_id');
        $parameters = $byFunction('parameters');
        $examples = $byFunction('examples');
        $seeAlso = $byFunction('seealso', 'id');
        $sections = $byFunction('sections');
        $subsections = $db->table('subsections')->orderBy('position')->get()->groupBy('section_id');
        $aliases = $byFunction('aliases', 'id');

        $text = fn (?string $s) => $s !== null && trim($s) !== '' ? trim($s) : null;

        return [
            'schema' => 'docs-reference.v1',
            'product' => 'r',
            'version' => 'docs.db',
            'categories' => $categories->map(fn ($c) => array_filter([
                'id' => $categoryIds[$c->id],
                'title' => $c->name,
                'description' => (string) $c->description,
                'position' => (int) $c->position,
                'common' => $functions->where('category_id', $c->id)->where('is_common', 1)->pluck('name')->values()->all(),
            ], fn ($v) => $v !== [] && $v !== null))->values()->all(),
            'entries' => $functions->map(fn ($f) => array_filter([
                'id' => $f->name,
                'name' => $f->name,
                'kind' => 'function',
                'category' => $categoryIds[$f->category_id] ?? 'workflow',
                'summary' => $text($f->title) ?? $f->name,
                'usage' => $text($f->usage),
                'description' => $text($f->description),
                'details' => $text($f->details),
                'returns' => $text($f->value),
                'note' => $text($f->note),
                'parameters' => $parameters->get($f->id, collect())->map(fn ($p) => [
                    'name' => $p->name,
                    'description' => (string) $p->description,
                ])->values()->all(),
                'sections' => $sections->get($f->id, collect())->map(fn ($s) => array_filter([
                    'title' => $s->title,
                    'content' => (string) $s->content,
                    'subsections' => $subsections->get($s->id, collect())->map(fn ($sub) => [
                        'title' => $sub->title,
                        'content' => (string) $sub->content,
                    ])->values()->all(),
                ], fn ($v) => $v !== []))->values()->all(),
                'examples' => $examples->get($f->id, collect())->map(fn ($e) => [
                    'code' => $e->code,
                    'runnable' => ! $e->is_dontrun,
                ])->values()->all(),
                'see_also' => $seeAlso->get($f->id, collect())->map(fn ($s) => $s->url && str_starts_with($s->url, 'https://')
                    ? ['title' => $s->reference, 'url' => $s->url]
                    : preg_replace('/\(\)$/', '', $s->reference))->unique(fn ($s) => json_encode($s))->values()->all(),
                'aliases' => $aliases->get($f->id, collect())->pluck('alias')->values()->all(),
            ], fn ($v) => $v !== [] && $v !== null))->values()->all(),
        ];
    }

    private static function hasDatabase(): bool
    {
        $path = (string) config('database.connections.docs.database');

        return $path !== '' && is_file($path);
    }

    private static function fingerprint(): string
    {
        $parts = [Compiler::FORMAT];
        foreach (File::files(__DIR__) as $file) {
            $parts[] = $file->getFilename().':'.$file->getMTime();
        }
        foreach (File::files(resource_path(self::GUIDES)) as $file) {
            $parts[] = $file->getFilename().':'.$file->getMTime().':'.$file->getSize();
        }
        if (self::hasDatabase()) {
            $parts[] = filemtime((string) config('database.connections.docs.database'));
        }

        return substr(md5(implode('|', $parts)), 0, 16);
    }
}
