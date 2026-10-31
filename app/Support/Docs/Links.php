<?php

namespace App\Support\Docs;

/**
 * Turns the docs-site link forms into URLs and records any target it
 * cannot find:
 *
 *   guide:ID[#anchor]   ref:ID[#anchor]        a page in this product
 *   r:guide:ID  py:ref:ID  cli:ref:ID           a page in another product
 *   #anchor   https://…   mailto:…              left as they are
 *   images/name.png                             served at /images/name.png
 *
 * Links to another product become fwdocs://{product}/path, which
 * Products::absolutize() turns into that product's host when a page
 * renders. They are checked only when that product has been imported.
 */
class Links
{
    /** @var list<string> */
    public array $warnings = [];

    /** Where links are being read, such as "guides/setup.md", for warnings */
    public string $context = '';

    /**
     * @param  array<string, list<string>>  $guides  guide id => its heading anchors
     * @param  array<string, list<string>>  $entries  entry id => its anchors
     * @param  array<string, string>  $names  entry name or alias => entry id
     * @param  list<string>  $images  paths under images/
     * @param  array<string, array{guides: array<string, list<string>>, entries: array<string, list<string>>}>  $others  other imported products
     */
    public function __construct(
        public readonly string $product,
        public readonly array $guides = [],
        public readonly array $entries = [],
        public readonly array $names = [],
        public readonly array $images = [],
        public readonly array $others = [],
        public readonly bool $quiet = false,
    ) {}

    public function resolve(string $target): string
    {
        $target = trim($target);

        if ($target === '' || str_starts_with($target, '#') || preg_match('#^(https?:|mailto:)#i', $target)) {
            return $target;
        }

        if (preg_match('#^(?:(r|py|cli):)?(guide|ref):([^\#]+)(\#.*)?$#', $target, $m)) {
            return $this->page($m[1] ?: $this->product, $m[2], $m[3], $m[4] ?? '', $target);
        }

        if (preg_match('#^(?:\./)?(images/.+)$#', $target, $m)) {
            return $this->image($m[1], $target);
        }

        $this->warn("{$target} is not a link form docs sites use (guide:, ref:, images/, or a full URL).");

        return $target;
    }

    /** The id of the entry a name, alias, or id refers to */
    public function entryId(string $name): ?string
    {
        $name = preg_replace('/\(\)$/', '', trim($name));

        if (isset($this->entries[$name])) {
            return $name;
        }

        return $this->names[$name] ?? null;
    }

    /**
     * A see_also string: an entry name in this product, or a link target.
     *
     * @return array{title: string, url: string}
     */
    public function seeAlso(string $item): array
    {
        if (preg_match('#^(?:(r|py|cli):)?(guide|ref):([^\#]+)#', $item, $m)) {
            $other = $m[1] !== '' && $m[1] !== $this->product ? ' ('.Products::LABELS[$m[1]].')' : '';

            return ['title' => $m[3].$other, 'url' => $this->resolve($item)];
        }

        if (preg_match('#^https://#', $item)) {
            return ['title' => $item, 'url' => $item];
        }

        $title = preg_replace('/\(\)$/', '', trim($item));
        $id = $this->entryId($item);
        if ($id === null) {
            $this->warn("see also \"{$item}\" does not match an entry.");

            return ['title' => $title, 'url' => '/reference/'.rawurlencode($title)];
        }

        return ['title' => $title, 'url' => '/reference/'.$id];
    }

    private function page(string $product, string $kind, string $id, string $anchor, string $target): string
    {
        $path = ($kind === 'guide' ? '/guides/' : '/reference/').$id.$anchor;
        $local = $product === $this->product;

        if (! $local && ! isset($this->others[$product])) {
            // Not imported here yet, so there is nothing to check against
            return Products::PLACEHOLDER.$product.$path;
        }

        $pages = $local
            ? ($kind === 'guide' ? $this->guides : $this->entries)
            : $this->others[$product][$kind === 'guide' ? 'guides' : 'entries'];

        if ($kind === 'ref' && $local && ! isset($pages[$id]) && ($byName = $this->entryId($id)) !== null) {
            $id = $byName;
            $path = '/reference/'.$id.$anchor;
        }

        if (! isset($pages[$id])) {
            $this->warn("{$target} does not match ".($kind === 'guide' ? 'a guide' : 'a reference entry').'.');
        } elseif ($anchor !== '' && ! in_array(substr($anchor, 1), $pages[$id], true)) {
            $this->warn("{$target} names a heading the page does not have.");
        }

        return $local ? $path : Products::PLACEHOLDER.$product.$path;
    }

    private function image(string $path, string $target): string
    {
        $file = substr($path, strlen('images/'));

        if (! in_array($file, $this->images, true)) {
            $this->warn("{$target} is not in images/.");
        }

        return '/'.$path;
    }

    private function warn(string $message): void
    {
        if (! $this->quiet) {
            $this->warnings[] = ($this->context !== '' ? $this->context.': ' : '').$message;
        }
    }
}
