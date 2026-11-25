<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Support\Dags\ArtifactDag;
use App\Support\Dags\DagLayout;
use App\Support\Snippets\ArtifactSnippet;
use App\Support\Trees\ArtifactTree;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Throwable;

/**
 * The picture a link preview shows for a shared artifact: a 1200 × 630 PNG
 * with the title, the kind, project, and author, and the artifact itself where
 * it can be drawn (a DAG's diagram, a tree's rows, a snippet's code, a table's
 * first rows, a figure). Anything else gets the title card alone. Built once
 * per version and kept on the local disk; VERSION redraws every image.
 */
class ShareImage
{
    public const VERSION = 3;

    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const PAD = 64;

    /** The body's box ends above the footer; it starts under the header, which grows with the title. */
    private const BODY_BOTTOM = 548;

    /** Figures larger than this are not read for a preview. */
    private const MAX_FIGURE_BYTES = 20_971_520;

    // Installed on the server (infra: deploy-framework-pub.yml --tags fonts; the self-host image copies them in)
    private const SANS = "'IBM Plex Sans', 'DejaVu Sans', sans-serif";

    private const SERIF = "'IBM Plex Serif', 'DejaVu Serif', serif";

    private const MONO = "'JetBrains Mono', 'DejaVu Sans Mono', monospace";

    /** Kinds whose title card reads like a paper, in the serif. */
    private const PAPERS = ['html', 'notebook', 'report', 'book', 'pdf', 'text'];

    private const ROLE_COLORS = [
        'exposure' => ['#ecfdf5', '#059669', '#064e3b'],
        'outcome' => ['#f0f9ff', '#0284c7', '#0c4a6e'],
        'adjusted' => ['#f1f5f9', '#334155', '#0f172a'],
    ];

    /** Whether link previews may show this artifact: anyone with the link can open it, without a password. */
    public static function available(Document $document): bool
    {
        // Drawing needs Imagick; without it, previews show the site's image
        return extension_loaded('imagick') && ($document->driver ?? 'cloud') === 'cloud' && in_array($document->visibility, ['public', 'unlisted'], true)
            && ! $document->isPasswordProtected() && $document->merged_into_id === null;
    }

    /** The image's URL for a version; it changes with the version, so previews pick up a new one. */
    public static function url(DocumentVersion $version): string
    {
        return route('documents.share-image', ['key' => $version->document->key, 'number' => $version->number, 'v' => self::VERSION]);
    }

    /** One line saying what is shown, for og:image:alt. */
    public static function alt(DocumentVersion $version): string
    {
        $document = $version->document;

        return ($document->title ?: $document->slug).', '.self::kindLabel($version).' on '.ArtifactEmbed::providerName();
    }

    /** The PNG, built and kept on first use. */
    public static function png(DocumentVersion $version): string
    {
        $path = 'share-images/'.$version->id.'-'.self::VERSION.'.png';
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }
        $png = self::build($version);
        $disk->put($path, $png);

        return $png;
    }

    private static function build(DocumentVersion $version): string
    {
        try {
            return self::render($version, true);
        } catch (Throwable $e) {
            report($e);

            // A drawing that fails still leaves the title card
            return self::render($version, false);
        }
    }

    private static function render(DocumentVersion $version, bool $withBody): string
    {
        $preview = ArtifactPreview::forVersion($version);
        $figure = $withBody ? self::figure($version) : null;
        $body = $withBody && $figure === null ? self::body($preview) : null;
        [$svg, $bodyTop] = self::frame($version, $body !== null || $figure !== null ? $body ?? [] : null);

        $image = new Imagick;
        $image->setBackgroundColor(new ImagickPixel('white'));
        $image->readImageBlob($svg);
        $image->setImageFormat('png');
        if ($figure !== null) {
            self::place($image, $figure, $bodyTop);
            $figure->clear();
        }
        $image->stripImage();
        $png = $image->getImageBlob();
        $image->clear();

        return $png;
    }

    /**
     * The card: header, body (an SVG fragment scaled into its box), and footer,
     * and where the body starts. An empty $body leaves room for a figure.
     *
     * @return array{0: string, 1: int}
     */
    private static function frame(DocumentVersion $version, ?array $body): array
    {
        $document = $version->document;
        $title = $document->title ?: $document->slug;
        $titleSize = $body === null ? 60 : 46;
        $paper = $body === null && in_array($version->kind ?? $document->kind, self::PAPERS, true);
        $family = $paper ? self::SERIF : self::SANS;
        // Plex SemiBold averages about 0.58 em a character
        $lines = self::wrap($title, $body === null ? 3 : 2, (int) floor((self::WIDTH - 2 * self::PAD) / ($titleSize * 0.58)));
        $out = '';
        $y = $body === null ? 190 : 108;
        foreach ($lines as $line) {
            $out .= self::text(self::PAD, $y, $line, $titleSize, '#0f172a', $family, '600');
            $y += (int) round($titleSize * 1.18);
        }
        // The kind line sits under the last title line, and the body under it
        $metaY = $y - (int) round($titleSize * 1.18) + ($body === null ? 64 : 48);
        $out .= self::text(self::PAD, $metaY, self::meta($version), 24, '#64748b', self::SANS);
        $bodyTop = $metaY + 36;
        if ($body !== null && $body !== []) {
            [$fragment, $width, $height] = $body;
            $boxWidth = self::WIDTH - 2 * self::PAD;
            $boxHeight = self::BODY_BOTTOM - $bodyTop;
            $scale = min($boxWidth / max($width, 1), $boxHeight / max($height, 1), 2.0);
            $x = self::PAD + ($boxWidth - $width * $scale) / 2;
            $top = $bodyTop + ($boxHeight - $height * $scale) / 2;
            $out .= sprintf('<g transform="translate(%.1f %.1f) scale(%.4f)">%s</g>', $x, $top, $scale, $fragment);
        }
        $out .= sprintf('<rect x="0" y="%d" width="%d" height="1" fill="#e2e8f0"/>', self::HEIGHT - 58, self::WIDTH);
        $out .= self::text(self::PAD, self::HEIGHT - 22, ArtifactEmbed::providerName(), 22, '#0369a1', self::SANS, '600');

        return [sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d"><rect width="%1$d" height="%2$d" fill="#ffffff"/>%3$s</svg>', self::WIDTH, self::HEIGHT, $out), $bodyTop];
    }

    /** The kind alone: "DAG". */
    private static function meta(DocumentVersion $version): string
    {
        return self::kindLabel($version);
    }

    public static function kindLabel(DocumentVersion $version): string
    {
        $kind = $version->kind ?? $version->document->kind ?? 'file';

        return [
            'dag' => 'DAG', 'tree' => 'Project structure', 'code_snippet' => 'Code', 'table' => 'Table', 'data' => 'Data',
            'model' => 'Model', 'test' => 'Hypothesis test', 'image' => 'Figure', 'chart' => 'Chart', 'html' => 'Document',
            'notebook' => 'Notebook', 'report' => 'Report', 'book' => 'Book', 'pdf' => 'PDF', 'text' => 'Text',
        ][$kind] ?? 'Artifact';
    }

    /** @return array{0: string, 1: float, 2: float}|null An SVG fragment and its size. */
    private static function body(array $preview): ?array
    {
        return match ($preview['type']) {
            ArtifactDag::KIND => self::dag($preview),
            ArtifactTree::KIND => self::tree($preview),
            ArtifactSnippet::KIND => self::snippet($preview),
            'representation' => self::table($preview['tables'][0] ?? null),
            'table' => self::plainTable($preview['rows'] ?? []),
            default => null,
        };
    }

    private static function dag(array $data): array
    {
        $graph = $data['dag'];
        $out = '';
        foreach ($graph['edges'] as $edge) {
            $dash = $edge['type'] === '<->' ? ' stroke-dasharray="5 4"' : '';
            $out .= '<path d="'.self::e($edge['path']).'" fill="none" stroke="#64748b" stroke-width="1.5"'.$dash.'/>';
            foreach ($edge['heads'] as $head) {
                $out .= '<path d="'.self::e($head).'" fill="#64748b"/>';
            }
        }
        foreach ($graph['nodes'] as $node) {
            $role = collect(array_keys(self::ROLE_COLORS))->first(fn ($role) => in_array($role, $node['roles'], true));
            [$fill, $stroke, $ink] = self::ROLE_COLORS[$role] ?? ['#ffffff', '#94a3b8', '#1e293b'];
            $dash = in_array('latent', $node['roles'], true) ? ' stroke-dasharray="4 3"' : '';
            $out .= sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="6" fill="%s" stroke="%s" stroke-width="1.25"%s/>', $node['x'], $node['y'], $node['width'], $node['height'], $fill, $stroke, $dash);
            $label = DagLayout::shownLabel($node['label'] ?? $node['name']);
            $out .= self::text($node['x'] + $node['width'] / 2, $node['y'] + $node['height'] / 2 + 5, $label, 14, $ink, self::MONO, 'normal', 'middle');
        }

        return [$out, (float) $graph['width'], (float) $graph['height']];
    }

    /** The tree's first rows as it opens, with guides and notes. */
    private static function tree(array $data): array
    {
        $rows = [[$data['tree']['root'].'/', null, true]];
        $walk = function (array $children, string $prefix) use (&$walk, &$rows): void {
            $last = count($children) - 1;
            foreach ($children as $index => $node) {
                $end = $index === $last;
                $badge = isset($node['hidden']) ? ArtifactTree::fileCount($node['hidden']) : null;
                $note = $node['note'] ?? (isset($node['ignored']) ? 'ignored · '.$node['ignored']['rule'].($badge ? ' · '.$badge : '') : (isset($node['private']) ? 'private'.($badge ? ' · '.$badge : '') : $badge));
                $rows[] = [$prefix.($end ? '└── ' : '├── ').$node['name'].($node['type'] === 'dir' ? '/' : ''), $note, $node['type'] === 'dir' && ! isset($node['hidden'])];
                if (($node['open'] ?? false) && ! empty($node['children'])) {
                    $walk($node['children'], $prefix.($end ? '    ' : '│   '));
                }
            }
        };
        $walk($data['tree']['children'], '');

        return self::monoLines(array_slice($rows, 0, 12), count($rows) > 12);
    }

    private static function snippet(array $data): ?array
    {
        $code = collect($data['snippet']['blocks'] ?? [])->firstWhere('type', 'code');
        if ($code === null) {
            return null;
        }
        $lines = array_slice(explode("\n", rtrim($code['text'])), 0, 12);

        return self::monoLines(array_map(fn ($line) => [$line, null, false], $lines), substr_count(rtrim($code['text']), "\n") >= 12);
    }

    /** @param list<array{0: string, 1: ?string, 2: bool}> $rows Text, a note, and whether it's bold. */
    private static function monoLines(array $rows, bool $more): array
    {
        $size = 18;
        $height = 26;
        $nameWidth = min(46, max(array_map(fn ($row) => mb_strlen($row[0]), $rows)) + 3);
        $out = '';
        foreach ($rows as $index => [$text, $note, $bold]) {
            $y = ($index + 1) * $height;
            $out .= self::text(0, $y, self::clip($text, 46), $size, '#1e293b', self::MONO, $bold ? '600' : 'normal', 'start', true);
            if ($note !== null) {
                $out .= self::text($nameWidth * $size * 0.6, $y, '# '.self::clip($note, 36), $size, '#64748b', self::MONO, 'normal', 'start', true);
            }
        }
        if ($more) {
            $out .= self::text(0, (count($rows) + 1) * $height, '…', $size, '#94a3b8', self::MONO);
        }
        $width = $nameWidth * $size * 0.6 + 40 * $size * 0.6;

        return [$out, max($width, 1000.0), (count($rows) + ($more ? 1 : 0)) * $height + 8];
    }

    /** A table's first rows and columns, header first. */
    private static function table(?array $table): ?array
    {
        if ($table === null || empty($table['columns'])) {
            return null;
        }
        $columns = array_slice($table['columns'], 0, 6);
        $rows = array_map(fn ($row) => array_map(fn ($value) => ArtifactFormatting::value($value, is_float($value)), array_slice($row, 0, 6)), array_slice($table['rows'] ?? [], 0, 7));

        return self::grid(array_map(fn ($column) => $column['label'] ?? $column['name'], $columns), $rows);
    }

    private static function plainTable(array $rows): ?array
    {
        $header = array_shift($rows);
        if (! is_array($header)) {
            return null;
        }

        return self::grid(array_slice($header, 0, 6), array_map(fn ($row) => array_slice($row, 0, 6), array_slice($rows, 0, 7)));
    }

    private static function grid(array $header, array $rows): array
    {
        $size = 18;
        $rowHeight = 40;
        $widths = [];
        foreach ([$header, ...$rows] as $row) {
            foreach (array_values($row) as $index => $cell) {
                $widths[$index] = min(18, max($widths[$index] ?? 4, mb_strlen((string) $cell)));
            }
        }
        $x = [];
        $at = 0.0;
        foreach ($widths as $index => $chars) {
            $x[$index] = $at;
            $at += $chars * $size * 0.6 + 36;
        }
        $out = '';
        foreach (array_values($header) as $index => $cell) {
            $out .= self::text($x[$index], 26, self::clip((string) $cell, 18), $size, '#0f172a', self::SANS, '600');
        }
        $out .= sprintf('<rect x="0" y="40" width="%.1f" height="1.5" fill="#cbd5e1"/>', $at - 36);
        foreach ($rows as $r => $row) {
            $y = 40 + ($r + 1) * $rowHeight - 12;
            foreach (array_values($row) as $index => $cell) {
                $out .= self::text($x[$index] ?? 0, $y, self::clip((string) ($cell ?? ''), 18), $size, '#334155', self::MONO);
            }
            $out .= sprintf('<rect x="0" y="%d" width="%.1f" height="1" fill="#e2e8f0"/>', 40 + ($r + 1) * $rowHeight, $at - 36);
        }

        return [$out, max($at - 36, 600.0), 40 + count($rows) * $rowHeight + 4];
    }

    /** A raster figure to place in the body: the figure itself, or a chart's fallback image. */
    private static function figure(DocumentVersion $version): ?Imagick
    {
        if (! ArtifactEmbed::hasImage($version)) {
            return null;
        }
        // SVG figures can name other files; only raster figures are read
        if (! in_array(ArtifactEmbed::contentType($version), ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
            || (int) $version->size_bytes > self::MAX_FIGURE_BYTES) {
            return null;
        }
        $bytes = Storage::disk($version->disk())->get($version->entryPath());
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }
        $figure = new Imagick;
        $figure->readImageBlob($bytes);
        $figure = $figure->coalesceImages()->getImage();

        return $figure;
    }

    private static function place(Imagick $card, Imagick $figure, int $bodyTop): void
    {
        $boxWidth = self::WIDTH - 2 * self::PAD;
        $boxHeight = self::BODY_BOTTOM - $bodyTop;
        $figure->setImageBackgroundColor(new ImagickPixel('white'));
        $figure = $figure->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $scale = min($boxWidth / $figure->getImageWidth(), $boxHeight / $figure->getImageHeight(), 2.0);
        $width = max(1, (int) round($figure->getImageWidth() * $scale));
        $height = max(1, (int) round($figure->getImageHeight() * $scale));
        $figure->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        $card->compositeImage($figure, Imagick::COMPOSITE_OVER, self::PAD + intdiv($boxWidth - $width, 2), $bodyTop + intdiv($boxHeight - $height, 2));
    }

    /** Up to $max lines of about $chars characters each, the last cut with an ellipsis. */
    private static function wrap(string $text, int $max, int $chars): array
    {
        $lines = [''];
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $line = &$lines[count($lines) - 1];
            if ($line === '' || mb_strlen($line.' '.$word) <= $chars) {
                $line = $line === '' ? $word : $line.' '.$word;
            } else {
                unset($line);
                $lines[] = $word;
            }
            unset($line);
        }
        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = self::clip($lines[$max - 1].' …', $chars);
        }

        return array_map(fn ($line) => self::clip($line, $chars), $lines);
    }

    private static function clip(string $text, int $chars): string
    {
        return mb_strlen($text) > $chars ? rtrim(mb_substr($text, 0, $chars - 1)).'…' : $text;
    }

    private static function text(float $x, float $y, string $text, int $size, string $fill, string $family, string $weight = 'normal', string $anchor = 'start', bool $preserve = false): string
    {
        // An explicit upright style: without it the matcher can pick a family's italic face
        return sprintf('<text x="%.1f" y="%.1f" font-family="%s" font-size="%d" font-weight="%s" font-style="normal" fill="%s" text-anchor="%s"%s>%s</text>',
            $x, $y, self::e($family), $size, $weight, $fill, $anchor, $preserve ? ' xml:space="preserve"' : '', self::e($text));
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
