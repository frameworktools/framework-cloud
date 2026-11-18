<?php

namespace App\Support\Snippets;

use App\Models\Document;
use App\Support\ArtifactEmbed;
use InvalidArgumentException;
use JsonException;

/**
 * The code_snippet artifact: a chunk's code and the text it printed, as a
 * notebook showed them. Clients send framework.artifact/v1 JSON with blocks
 * of code, output, message, warning, and error. This class validates it and
 * builds the normalized form the reader and embeds draw: each block with its
 * plain `text` and its styled `lines`. See docs/plans/2026-11-18-code-snippets.md.
 */
class ArtifactSnippet
{
    public const KIND = 'code_snippet';

    public const MAX_BYTES = 2_097_152;

    public const MAX_BLOCKS = 200;

    public const MAX_CODE_LINES = 400;

    public const MAX_TEXT_LINES = 500;

    public const MAX_TEXT_CHARS = 100_000;

    /** Output blocks longer than this fold when the snippet leaves it to Cloud */
    public const FOLD_LINES = 12;

    public const TYPES = ['code', 'output', 'message', 'warning', 'error'];

    public const LANGUAGES = ['r' => 'R', 'python' => 'Python', 'stata' => 'Stata'];

    private const MAX_LABEL = 300;

    /** Whether JSON says it is a code snippet, valid or not. */
    public static function claims(string $json): bool
    {
        return preg_match('/"kind"\s*:\s*"code_snippet"/', $json) === 1;
    }

    /**
     * The normalized snippet, or an exception whose message says what is wrong.
     *
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('A code snippet can be at most 2 MB.');
        }
        try {
            $input = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The code snippet is not valid JSON.');
        }
        if (! is_array($input) || array_is_list($input)) {
            throw new InvalidArgumentException('The code snippet is not a JSON object.');
        }

        return self::normalize($input);
    }

    /** @throws InvalidArgumentException */
    public static function normalize(array $input): array
    {
        if (($input['schema'] ?? null) !== 'framework.artifact/v1' || ($input['kind'] ?? null) !== self::KIND) {
            throw new InvalidArgumentException('A code snippet needs schema framework.artifact/v1 and kind code_snippet.');
        }
        $source = is_array($input['source'] ?? null) ? $input['source'] : [];
        $language = $source['language'] ?? null;
        if (! is_string($language) || ! array_key_exists($language, self::LANGUAGES)) {
            throw new InvalidArgumentException('A code snippet needs source.language: r, python, or stata.');
        }
        $snippet = is_array($input['snippet'] ?? null) ? $input['snippet'] : [];
        $width = $snippet['width'] ?? null;
        $width = is_int($width) && $width >= 20 && $width <= 1000 ? $width : null;
        $blocks = $snippet['blocks'] ?? null;
        if (! is_array($blocks) || ! array_is_list($blocks) || $blocks === []) {
            throw new InvalidArgumentException('A code snippet needs snippet.blocks, a list of blocks.');
        }
        if (count($blocks) > self::MAX_BLOCKS) {
            throw new InvalidArgumentException('A code snippet can have at most '.self::MAX_BLOCKS.' blocks.');
        }
        $display = is_array($input['display'] ?? null) ? $input['display'] : [];
        $defaultFold = $display['folded'] ?? 'auto';
        if (! in_array($defaultFold, ['auto', true, false], true)) {
            $defaultFold = 'auto';
        }

        $normalized = [];
        $codeLines = 0;
        $truncated = false;
        foreach ($blocks as $index => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            if (! in_array($type, self::TYPES, true)) {
                $shown = is_string($type) ? '"'.mb_substr($type, 0, 40).'"' : 'missing';
                throw new InvalidArgumentException('Block '.($index + 1).' has type '.$shown.'. A code snippet holds code, output, message, warning, and error blocks.');
            }
            $smcl = $type !== 'code' && is_string($block['smcl'] ?? null) ? $block['smcl'] : null;
            $text = $block['text'] ?? null;
            if ($smcl === null && ! is_string($text)) {
                throw new InvalidArgumentException('Block '.($index + 1).' needs text.');
            }
            if ($type === 'code') {
                $lines = StyledText::trim(CodeHighlighter::lines(self::cleanCode($text), $language));
                $codeLines += count($lines);
                if ($codeLines > self::MAX_CODE_LINES) {
                    throw new InvalidArgumentException('A code snippet can have at most '.self::MAX_CODE_LINES.' lines of code.');
                }
                if ($lines !== []) {
                    $normalized[] = ['type' => 'code', 'text' => StyledText::text($lines), 'lines' => $lines];
                }

                continue;
            }
            // Text past the limits is cut here, whatever the client did
            $raw = $smcl ?? $text;
            if (strlen($raw) > self::MAX_TEXT_CHARS * 4) {
                $raw = substr($raw, 0, self::MAX_TEXT_CHARS * 4);
            }
            $lines = StyledText::trim($smcl !== null ? SmclText::lines($raw, $width) : AnsiText::lines($raw));
            [$lines, $more] = self::cut($lines);
            if ($lines === []) {
                continue;
            }
            if ($smcl !== null && $type === 'output' && SmclText::isError($raw)) {
                $type = 'error';
            }
            $folded = $block['folded'] ?? null;
            $folded = is_bool($folded) ? $folded : ($defaultFold === 'auto' ? count($lines) + $more > self::FOLD_LINES : $defaultFold);
            $entry = ['type' => $type, 'text' => StyledText::text($lines), 'lines' => $lines, 'folded' => $folded];
            if ($more > 0) {
                $entry['truncated'] = ['lines' => $more];
                $truncated = true;
            }
            $normalized[] = $entry;
        }
        if (! in_array('code', array_column($normalized, 'type'), true)) {
            throw new InvalidArgumentException('A code snippet needs at least one code block.');
        }

        return [
            'schema' => 'framework.artifact/v1',
            'kind' => self::KIND,
            'source' => self::source($source, $language),
            'snippet' => array_filter(['width' => $width, 'blocks' => $normalized], fn ($value) => $value !== null),
            'display' => ['links' => self::links($display['links'] ?? null)],
            'truncated' => $truncated,
        ];
    }

    /**
     * Links a reader of this artifact may follow: the notebook, and the model
     * when it is at least as open as the snippet. For an embed, the model must
     * be embeddable too, so a public snippet never names a private model.
     *
     * @return list<array{rel: string, label: string, url: string}>
     */
    public static function resolvedLinks(array $snippet, Document $document, bool $embed = false): array
    {
        $links = [];
        foreach ($snippet['display']['links'] ?? [] as $link) {
            if ($link['rel'] === 'notebook') {
                $links[] = ['rel' => 'notebook', 'label' => 'Full notebook', 'url' => $link['url']];
            } elseif (isset($link['url'])) {
                $links[] = ['rel' => 'model', 'label' => 'Model', 'url' => $link['url']];
            } elseif (($model = self::model($document, $link['artifact'], $embed)) !== null) {
                $links[] = ['rel' => 'model', 'label' => 'Model', 'url' => $model->shareUrl()];
            }
        }

        return $links;
    }

    private static function model(Document $snippet, string $slug, bool $embed): ?Document
    {
        $model = Document::where('project_id', $snippet->project_id)->where('slug', $slug)
            ->whereNull('merged_into_id')->with('project.user')->first();
        if ($model === null || ($model->driver ?? 'cloud') !== 'cloud') {
            return null;
        }
        if ($embed) {
            return ArtifactEmbed::embeddable($model) ? $model : null;
        }
        $openness = ['private' => 0, 'unlisted' => 1, 'public' => 2];
        $open = ($openness[$model->visibility] ?? 0) >= ($openness[$snippet->visibility] ?? 0);
        // A password on the model hides it from readers who only passed the snippet's
        $guarded = $model->isPasswordProtected() && $snippet->visibility !== 'private';

        return $open && ! $guarded ? $model : null;
    }

    /** Code with plain line endings and no escape codes. */
    private static function cleanCode(string $code): string
    {
        $code = str_replace(["\r\n", "\r"], "\n", $code);

        return StyledText::text(AnsiText::lines($code));
    }

    /**
     * The first lines within the limits, and how many lines were cut.
     *
     * @return array{0: list<list<array{0: string, 1: string}>>, 1: int}
     */
    private static function cut(array $lines): array
    {
        $kept = [];
        $chars = 0;
        foreach ($lines as $index => $line) {
            $chars += mb_strlen(StyledText::lineText($line)) + 1;
            if ($index >= self::MAX_TEXT_LINES || $chars > self::MAX_TEXT_CHARS) {
                return [$kept, count($lines) - $index];
            }
            $kept[] = $line;
        }

        return [$kept, 0];
    }

    private static function source(array $source, string $language): array
    {
        $label = fn (mixed $value) => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, self::MAX_LABEL) : null;
        $file = $label($source['file'] ?? null);
        if ($file !== null) {
            $file = str_replace('\\', '/', $file);
        }
        $lines = $source['lines'] ?? null;
        $lines = is_array($lines) && array_is_list($lines) && count($lines) === 2 && is_int($lines[0]) && is_int($lines[1])
            && $lines[0] >= 1 && $lines[1] >= $lines[0] ? $lines : null;
        $commit = is_string($source['commit'] ?? null) && preg_match('/^[0-9a-f]{7,64}$/', $source['commit']) ? $source['commit'] : null;
        $repository = is_string($source['repository'] ?? null)
            && preg_match('#^https://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#D', $source['repository'], $m)
            ? 'https://github.com/'.$m[1].'/'.$m[2] : null;
        $permalink = null;
        if ($repository && $commit && strlen($commit) === 40 && $file && $lines
            && ! str_starts_with($file, '/') && ! preg_match('#(^|/)\.\.(/|$)#', $file)) {
            $path = implode('/', array_map('rawurlencode', explode('/', $file)));
            $permalink = $repository.'/blob/'.$commit.'/'.$path.'#L'.$lines[0].($lines[1] > $lines[0] ? '-L'.$lines[1] : '');
        }

        return array_filter([
            'language' => $language,
            'file' => $file,
            'label' => $label($source['label'] ?? null),
            'lines' => $lines,
            'commit' => $commit,
            'repository' => $repository,
            'permalink' => $permalink,
        ], fn ($value) => $value !== null);
    }

    /** @return list<array<string, string>> */
    private static function links(mixed $links): array
    {
        if (! is_array($links) || ! array_is_list($links)) {
            return [];
        }
        $out = [];
        foreach (array_slice($links, 0, 10) as $link) {
            if (! is_array($link) || ! in_array($link['rel'] ?? null, ['notebook', 'model'], true)) {
                continue;
            }
            $url = $link['url'] ?? null;
            if (is_string($url) && strlen($url) <= 2000 && preg_match('#^https://[^\s/$.?\#][^\s]*$#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $out[] = ['rel' => $link['rel'], 'url' => $url];
            } elseif ($link['rel'] === 'model' && is_string($link['artifact'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,120}$/', $link['artifact'])) {
                $out[] = ['rel' => 'model', 'artifact' => $link['artifact']];
            }
        }

        return $out;
    }

    /** Whether a request or pasted link asks for code without output. */
    public static function wantsCodeOnly(?string $query): bool
    {
        parse_str((string) $query, $params);

        return ($params['output'] ?? null) === 'none';
    }

    /**
     * A one-sentence description: "R code from analysis.qmd, with its output."
     * Output hidden at first goes unmentioned.
     */
    public static function alt(array $snippet): string
    {
        $language = self::LANGUAGES[$snippet['source']['language']] ?? 'Code';
        $from = isset($snippet['source']['file']) ? ' from '.basename($snippet['source']['file']) : '';
        $output = ($snippet['display']['output'] ?? true) !== false
            && count(array_filter($snippet['snippet']['blocks'], fn ($block) => $block['type'] !== 'code')) > 0;

        return $language.' code'.$from.($output ? ', with its output.' : '.');
    }
}
