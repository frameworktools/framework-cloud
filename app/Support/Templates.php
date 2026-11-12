<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * The notebook, script, and slide templates a blueprint or project gives
 * make_notebook(), make_script(), and make_revealjs(). Each is a file in the
 * project's stubs/ folder, which R and Python read before their own
 * templates: stubs/notebook-default.qmd (or .Rmd), stubs/script-default.R
 * (or .py), and stubs/notebook-revealjs.qmd. Templates keep their
 * {{ title }}-style placeholders for the packages to fill when each file is
 * made, so the files are written as they are.
 *
 * Jupyter notebooks and Stata have no templates here.
 */
class Templates
{
    public const KINDS = ['notebook', 'script', 'slides'];

    /** Every path a template can take, by kind, as split() finds them. */
    private const PATHS = [
        'notebook' => ['stubs/notebook-default.qmd', 'stubs/notebook-default.Rmd'],
        'script' => ['stubs/script-default.R', 'stubs/script-default.py'],
        'slides' => ['stubs/notebook-revealjs.qmd'],
    ];

    /** The largest template, as a layer's files allow. */
    private const MAX_BYTES = 65536;

    /** An editor with no templates: Framework's own for each kind. */
    public static function empty(): array
    {
        return array_fill_keys(self::KINDS, '');
    }

    /** @return array<string, list<mixed>> request rules for the editor's templates field */
    public static function rules(): array
    {
        return [
            'templates' => ['sometimes', 'nullable', 'array'],
            ...collect(self::KINDS)->mapWithKeys(fn (string $kind) => ["templates.{$kind}" => ['nullable', 'string', 'max:'.self::MAX_BYTES]])->all(),
        ];
    }

    /**
     * Where each template goes for a language and notebook format, or null
     * for a kind the project has no template for.
     *
     * @param  string|null  $format  quarto, rmarkdown, jupyter, or null for scripts
     * @return array<string, ?string>
     */
    public static function paths(string $language, ?string $format): array
    {
        $documents = in_array($format, ['quarto', 'rmarkdown'], true);

        return [
            'notebook' => match (true) {
                ! $documents || $language === 'stata' => null,
                $format === 'rmarkdown' && $language === 'r' => 'stubs/notebook-default.Rmd',
                default => 'stubs/notebook-default.qmd',
            },
            'script' => ['r' => 'stubs/script-default.R', 'python' => 'stubs/script-default.py'][$language] ?? null,
            'slides' => $documents && $language !== 'stata' ? 'stubs/notebook-revealjs.qmd' : null,
        ];
    }

    /**
     * The editor's templates as the layer's files. Empty templates use
     * Framework's own and add nothing.
     *
     * @param  array<string, mixed>  $input  the editor's templates field
     * @return array<string, array{content: string}>
     */
    public static function files(array $input, string $language, ?string $format): array
    {
        $files = [];
        foreach (self::paths($language, $format) as $kind => $path) {
            $content = $input[$kind] ?? null;
            if (! is_string($content) || trim($content) === '' || $path === null) {
                continue;
            }
            if (strlen($content) > self::MAX_BYTES) {
                throw ValidationException::withMessages(["templates.{$kind}" => 'A template can be at most 64 KB.']);
            }
            $files[$path] = ['content' => rtrim($content, "\n")."\n"];
        }

        return $files;
    }

    /**
     * A layer's templates as the editor holds them, and the layer without
     * them. A stub file with placeholders filled at creation stays in the layer.
     *
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     */
    public static function split(array $layer): array
    {
        $form = self::empty();
        foreach (self::PATHS as $kind => $paths) {
            foreach ($paths as $path) {
                $file = $layer['files'][$path] ?? null;
                if (! is_array($file) || ! is_string($file['content'] ?? null) || ($file['template'] ?? false)) {
                    continue;
                }
                if ($form[$kind] === '') {
                    $form[$kind] = $file['content'];
                }
                unset($layer['files'][$path]);
            }
        }
        if (array_key_exists('files', $layer) && $layer['files'] === []) {
            unset($layer['files']);
        }

        return [$form, $layer];
    }

    /**
     * Framework's own templates, by language and kind, for the editor to
     * start from. The notebook has one for each format R reads.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        $read = fn (string $path) => file_get_contents(resource_path("stubs/{$path}"));

        return [
            'r' => [
                'notebook' => ['quarto' => $read('r/notebook-default.qmd'), 'rmarkdown' => $read('r/notebook-default.Rmd')],
                'script' => $read('r/script-default.R'),
                'slides' => $read('r/notebook-revealjs.qmd'),
            ],
            'python' => [
                'notebook' => ['quarto' => $read('python/notebook-default.qmd')],
                'script' => $read('python/script-default.py'),
                'slides' => $read('python/notebook-revealjs.qmd'),
            ],
        ];
    }
}
