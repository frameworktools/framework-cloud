<?php

namespace App\Support\Docs;

use Illuminate\Support\Str;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a docs-site folder (site.yml, guides/*.md, images/, reference.json)
 * and builds what the docs pages read: rendered HTML, the navigation,
 * previous and next links, and the search index. Throws InvalidSite when
 * the folder cannot be used, and lists links it could not resolve in
 * $warnings.
 */
class Compiler
{
    public const FORMAT = 1;

    public const SITE_SCHEMA_ID = 'https://framework.pub/schema/docs-site.v1.json';

    public const REFERENCE_SCHEMA_ID = 'https://framework.pub/schema/docs-reference.v1.json';

    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];

    private const ID = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    private array $problems = [];

    /**
     * @param  array<string, array{guides: array<string, list<string>>, entries: array<string, list<string>>}>  $others  link targets of the other imported products
     */
    public function __construct(private readonly array $others = []) {}

    /**
     * @return array{site: array<string, mixed>, search: list<array<string, string>>, images: list<string>}
     */
    public function compile(string $dir, string $product): array
    {
        $dir = rtrim($dir, '/');
        $this->warnings = [];
        $this->problems = [];

        $site = $this->siteYml($dir, $product);
        $reference = $this->reference($dir, $product, $site);
        $guides = $this->guides($dir, $site);
        $images = $this->images($dir);

        if ($this->problems !== []) {
            throw new InvalidSite($this->problems);
        }

        // First pass: each guide's headings, so links to #anchors can be checked
        $anchors = [];
        foreach ($guides as $id => $guide) {
            $anchors[$id] = array_column(Markdown::render($guide['body'])['toc'], 'id');
        }

        $entries = collect($reference['entries'] ?? [])->keyBy('id');
        $names = [];
        foreach ($entries as $entry) {
            foreach ([$entry['name'], ...($entry['aliases'] ?? [])] as $name) {
                $names[$name] ??= $entry['id'];
            }
        }

        $links = new Links(
            product: $product,
            guides: $anchors,
            entries: $entries->map(fn ($e) => $this->entryAnchors($e))->all(),
            names: $names,
            images: $images,
            others: array_diff_key($this->others, [$product => true]),
        );

        $compiledGuides = [];
        foreach ($guides as $id => $guide) {
            $links->context = "guides/{$guide['file']}";
            $rendered = Markdown::render($guide['body'], $links);
            $compiledGuides[$id] = [
                'id' => $id,
                'title' => $guide['title'],
                'description' => $guide['description'],
                'section' => $guide['section'],
                'position' => $guide['position'],
                'html' => $rendered['html'],
                'toc' => $rendered['toc'],
            ];
        }

        $compiledEntries = [];
        foreach ($entries as $id => $entry) {
            $links->context = "reference.json: {$id}";
            $compiledEntries[$id] = $this->entry($entry, $links);
        }

        $sections = [];
        $order = [];
        foreach ($site['sections'] as $section) {
            $items = collect($compiledGuides)
                ->where('section', $section['id'])
                ->sortBy([['position', 'asc'], ['title', 'asc']])
                ->keys()->all();
            $order = [...$order, ...$items];
            $sections[] = [
                'id' => $section['id'],
                'title' => $section['title'],
                'reference' => (bool) ($section['reference'] ?? false),
                'guides' => $items,
            ];
        }

        foreach ($order as $i => $id) {
            $compiledGuides[$id]['prev'] = isset($order[$i - 1]) ? $this->pointer($compiledGuides[$order[$i - 1]]) : null;
            $compiledGuides[$id]['next'] = isset($order[$i + 1]) ? $this->pointer($compiledGuides[$order[$i + 1]]) : null;
        }

        $categories = [];
        foreach (collect($reference['categories'] ?? [])->sortBy('position') as $category) {
            $members = $entries->where('category', $category['id'])->sortBy(fn ($e) => Str::lower($e['name']));
            $common = [];
            foreach ($category['common'] ?? [] as $name) {
                $commonId = $links->entryId($name);
                if ($commonId === null || ($entries[$commonId]['category'] ?? null) !== $category['id']) {
                    $this->warnings[] = "reference.json: category {$category['id']} lists \"{$name}\" as common, but it has no such entry.";

                    continue;
                }
                $common[] = $commonId;
            }
            $categories[] = [
                'id' => $category['id'],
                'title' => $category['title'],
                'description' => $category['description'] ?? '',
                'common' => array_values(array_unique($common)),
                'entries' => $members->keys()->values()->all(),
            ];
        }

        $this->warnings = array_values(array_unique([...$this->warnings, ...$links->warnings]));

        $compiled = [
            'format' => self::FORMAT,
            'product' => $product,
            'title' => $site['title'],
            'tagline' => $site['tagline'] ?? '',
            'repository' => $site['repository'] ?? null,
            'install' => $site['install'] ?? null,
            'version' => $reference['version'] ?? null,
            'imported_at' => now()->toIso8601String(),
            'sections' => $sections,
            'guides' => $compiledGuides,
            'categories' => $categories,
            'entries' => $compiledEntries,
            'names' => $names,
        ];

        return ['site' => $compiled, 'search' => Search::build($compiled), 'images' => $images];
    }

    /** @return array<string, mixed> */
    private function siteYml(string $dir, string $product): array
    {
        $file = "{$dir}/site.yml";
        if (! is_file($file)) {
            $this->problems[] = 'site.yml is missing.';

            return ['title' => '', 'sections' => []];
        }

        try {
            $site = Yaml::parseFile($file);
        } catch (ParseException $e) {
            $this->problems[] = 'site.yml: '.$e->getMessage();

            return ['title' => '', 'sections' => []];
        }

        if (! is_array($site) || array_is_list($site)) {
            $this->problems[] = 'site.yml must be a YAML map.';

            return ['title' => '', 'sections' => []];
        }

        $problems = self::schemaProblems(json_decode(json_encode($site)), self::SITE_SCHEMA_ID, 'docs-site.v1.json', 'site.yml');
        if ($problems !== []) {
            array_push($this->problems, ...$problems);

            return ['title' => '', 'sections' => []];
        }

        if ($site['product'] !== $product) {
            $this->problems[] = "site.yml is for product \"{$site['product']}\", not \"{$product}\".";
        }

        $ids = array_column($site['sections'], 'id');
        foreach (array_unique(array_diff_assoc($ids, array_unique($ids))) as $id) {
            $this->problems[] = "site.yml lists section \"{$id}\" more than once.";
        }

        if (count(array_filter($site['sections'], fn ($s) => $s['reference'] ?? false)) > 1) {
            $this->problems[] = 'site.yml has more than one reference section.';
        }

        return $site;
    }

    /** @return array<string, mixed>|null */
    private function reference(string $dir, string $product, array $site): ?array
    {
        $file = "{$dir}/reference.json";
        $wanted = collect($site['sections'])->contains(fn ($s) => $s['reference'] ?? false);

        if (! is_file($file)) {
            if ($wanted) {
                $this->problems[] = 'reference.json is missing, but site.yml has a reference section.';
            }

            return null;
        }

        $decoded = json_decode((string) file_get_contents($file));
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->problems[] = 'reference.json is not valid JSON: '.json_last_error_msg().'.';

            return null;
        }

        $problems = self::schemaProblems($decoded, self::REFERENCE_SCHEMA_ID, 'docs-reference.v1.json', 'reference.json');
        if ($problems !== []) {
            array_push($this->problems, ...$problems);

            return null;
        }

        $reference = json_decode((string) file_get_contents($file), true);

        if ($reference['product'] !== $product) {
            $this->problems[] = "reference.json is for product \"{$reference['product']}\", not \"{$product}\".";
        }

        if (! $wanted) {
            $this->warnings[] = 'reference.json is there, but site.yml has no section with reference: true, so no page links to it.';
        }

        $categoryIds = array_column($reference['categories'], 'id');
        foreach (array_unique(array_diff_assoc($categoryIds, array_unique($categoryIds))) as $id) {
            $this->problems[] = "reference.json lists category \"{$id}\" more than once.";
        }

        $entryIds = array_column($reference['entries'], 'id');
        foreach (array_unique(array_diff_assoc($entryIds, array_unique($entryIds))) as $id) {
            $this->problems[] = "reference.json lists entry \"{$id}\" more than once.";
        }

        foreach ($reference['entries'] as $entry) {
            if (! in_array($entry['category'], $categoryIds, true)) {
                $this->problems[] = "reference.json: entry {$entry['id']} is in category \"{$entry['category']}\", which is not in categories.";
            }
        }

        return $reference;
    }

    /** @return array<string, array{file: string, title: string, description: string, section: string, position: int, body: string}> */
    private function guides(string $dir, array $site): array
    {
        $sections = collect($site['sections'])->reject(fn ($s) => $s['reference'] ?? false)->pluck('id')->all();
        $guides = [];

        if (! is_dir("{$dir}/guides")) {
            $this->warnings[] = 'There is no guides folder.';

            return [];
        }

        $files = Finder::create()->files()->in("{$dir}/guides")->name('*.md')->depth(0)->sortByName();
        foreach ($files as $file) {
            $name = $file->getFilename();
            $content = str_replace("\r\n", "\n", $file->getContents());

            if (! preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $content, $m)) {
                $this->problems[] = "guides/{$name} has no front matter.";

                continue;
            }

            try {
                $meta = Yaml::parse($m[1]);
            } catch (ParseException $e) {
                $this->problems[] = "guides/{$name}: front matter: ".$e->getMessage();

                continue;
            }

            $body = $m[2];
            $id = $meta['id'] ?? null;
            $where = "guides/{$name}";

            if (! is_string($id) || ! preg_match(self::ID, $id)) {
                $this->problems[] = "{$where} needs an id of letters, digits, hyphens, and underscores.";

                continue;
            }
            if (isset($guides[$id])) {
                $this->problems[] = "{$where} has id \"{$id}\", which guides/{$guides[$id]['file']} also has.";

                continue;
            }
            if (! is_string($meta['title'] ?? null) || trim($meta['title']) === '') {
                $this->problems[] = "{$where} needs a title.";
            }
            if (! in_array($meta['section'] ?? null, $sections, true)) {
                $this->problems[] = "{$where} needs a section from site.yml (".implode(', ', $sections).').';
            }
            if (! is_int($meta['position'] ?? null)) {
                $this->problems[] = "{$where} needs a whole-number position.";
            }
            if (! is_string($meta['description'] ?? null) || trim($meta['description']) === '') {
                $this->warnings[] = "{$where} has no description.";
            }
            if ($name !== "{$id}.md") {
                $this->warnings[] = "{$where} has id \"{$id}\"; its file is usually named {$id}.md.";
            }
            if (preg_match('/^# /m', preg_replace('/^(```|~~~).*?^\1/ms', '', $body))) {
                $this->warnings[] = "{$where} has an h1 heading. The page title comes from the front matter.";
            }

            $guides[$id] = [
                'file' => $name,
                'title' => (string) ($meta['title'] ?? $id),
                'description' => (string) ($meta['description'] ?? ''),
                'section' => (string) ($meta['section'] ?? ''),
                'position' => (int) ($meta['position'] ?? 0),
                'body' => $body,
            ];
        }

        return $guides;
    }

    /** @return list<string> paths under images/ */
    private function images(string $dir): array
    {
        if (! is_dir("{$dir}/images")) {
            return [];
        }

        $images = [];
        foreach (Finder::create()->in("{$dir}/images")->ignoreDotFiles(true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if ($file->isLink()) {
                $this->warnings[] = "images/{$relative} is a link and was left out.";
            } elseif ($file->isFile()) {
                if (in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true)) {
                    $images[] = $relative;
                } else {
                    $this->warnings[] = "images/{$relative} is not a png, jpg, svg, webp, or gif file and was left out.";
                }
            }
        }
        sort($images);

        return $images;
    }

    /** @return list<string> */
    private function entryAnchors(array $entry): array
    {
        $anchors = ['usage', 'parameters', 'returns', 'details', 'examples', 'see-also'];
        foreach ($entry['sections'] ?? [] as $section) {
            $anchors[] = Str::slug($section['title']);
            foreach ($section['subsections'] ?? [] as $sub) {
                $anchors[] = Str::slug($sub['title']);
            }
        }

        return $anchors;
    }

    /** @return array<string, mixed> */
    private function entry(array $entry, Links $links): array
    {
        $replacedBy = null;
        if (isset($entry['replaced_by'])) {
            $target = $links->entryId($entry['replaced_by']);
            if ($target === null) {
                $links->warnings[] = "{$links->context}: replaced_by \"{$entry['replaced_by']}\" does not match an entry.";
            }
            $replacedBy = [
                'name' => $entry['replaced_by'],
                'url' => $target !== null ? '/reference/'.$target : null,
            ];
        }

        return [
            'id' => $entry['id'],
            'name' => $entry['name'],
            'kind' => $entry['kind'],
            'category' => $entry['category'],
            'title' => $entry['title'] ?? null,
            'summary' => Markdown::plain($entry['summary']),
            'summary_html' => Markdown::inline($entry['summary'], $links),
            'usage' => $entry['usage'] ?? null,
            'description' => Markdown::html($entry['description'] ?? null, $links),
            'details' => Markdown::html($entry['details'] ?? null, $links),
            'returns' => Markdown::html($entry['returns'] ?? null, $links),
            'note' => Markdown::html($entry['note'] ?? null, $links),
            'parameters' => array_map(fn ($p) => [
                'name' => $p['name'],
                'type' => $p['type'] ?? null,
                'default' => $p['default'] ?? null,
                'required' => $p['required'] ?? false,
                'env' => $p['env'] ?? null,
                'description' => Markdown::html($p['description'], $links) ?? '',
            ], $entry['parameters'] ?? []),
            'sections' => array_map(fn ($s) => [
                'id' => Str::slug($s['title']),
                'title' => $s['title'],
                'html' => Markdown::html($s['content'], $links) ?? '',
                'subsections' => array_map(fn ($sub) => [
                    'id' => Str::slug($sub['title']),
                    'title' => $sub['title'],
                    'html' => Markdown::html($sub['content'], $links) ?? '',
                ], $s['subsections'] ?? []),
            ], $entry['sections'] ?? []),
            'examples' => array_map(fn ($e) => [
                'title' => $e['title'] ?? null,
                'code' => $e['code'],
                'runnable' => $e['runnable'] ?? true,
            ], $entry['examples'] ?? []),
            'see_also' => array_map(
                fn ($s) => is_string($s) ? $links->seeAlso($s) : ['title' => $s['title'], 'url' => $s['url']],
                $entry['see_also'] ?? [],
            ),
            'aliases' => $entry['aliases'] ?? [],
            'lifecycle' => $entry['lifecycle'] ?? 'stable',
            'replaced_by' => $replacedBy,
            'since' => $entry['since'] ?? null,
        ];
    }

    /** @return array{id: string, title: string} */
    private function pointer(array $guide): array
    {
        return ['id' => $guide['id'], 'title' => $guide['title']];
    }

    /** @return list<string> */
    private static function schemaProblems(mixed $data, string $id, string $file, string $label): array
    {
        $validator = new Validator;
        $validator->setMaxErrors(20);
        $validator->resolver()->registerFile($id, resource_path("schema/{$file}"));

        $result = $validator->validate($data, $id);
        if ($result->isValid()) {
            return [];
        }

        $problems = [];
        foreach ((new ErrorFormatter)->format($result->error(), true) as $path => $messages) {
            foreach ((array) $messages as $message) {
                $problems[] = "{$label} ".($path === '/' ? '' : "at {$path}").": {$message}";
            }
        }

        return array_map(fn ($p) => str_replace(' : ', ': ', $p), $problems);
    }
}
