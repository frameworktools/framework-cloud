<?php

namespace Tests\Feature;

use App\Support\ProjectTree;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The preview tree against the packages' create/ fixtures: for each resolved
 * spec, the files and folders R, Python, or fw actually write. The preview
 * collapses agent folders to one entry with their contents in a tooltip, and
 * leaves out .git and Framework's internal .framework folder.
 */
class PreviewConformanceTest extends TestCase
{
    public static function cases(): array
    {
        $cases = [];
        foreach (glob(__DIR__.'/../fixtures/conformance/create/*/*.json') as $file) {
            $cases[basename(dirname($file)).'/'.basename($file, '.json')] = [$file];
        }

        return $cases;
    }

    #[DataProvider('cases')]
    public function test_the_preview_matches_what_creation_writes(string $file): void
    {
        $case = json_decode(file_get_contents($file), true);
        $spec = $case['spec'];
        $language = $case['language'] ?? basename(dirname($file));

        $tree = ProjectTree::for($spec, $language, array_keys(array_filter((array) ($spec['tools'] ?? []))), (bool) ($spec['security']['sensitive'] ?? false));

        // Folders made later aren't created yet; a created path's parents are
        $preview = [];
        foreach ($tree as $entry) {
            if ($entry['later'] || $entry['path'] === '.git') {
                continue;
            }
            $parts = explode('/', $entry['path']);
            foreach (array_keys($parts) as $i) {
                $last = $i === count($parts) - 1;
                $preview[] = implode('/', array_slice($parts, 0, $i + 1)).($last && $entry['type'] !== 'dir' ? '' : '/');
            }
        }
        $collapse = fn (string $path) => preg_match('#^(\.agents|\.claude|\.github)/#', $path) ? explode('/', $path)[0].'/' : $path;
        // fw keeps empty folders in git with .gitkeep files, which the preview doesn't show
        $expected = collect($case['expected']['paths'])->reject(fn ($p) => str_starts_with($p, '.framework') || str_ends_with($p, '/.gitkeep'))->map($collapse)->unique()->sort()->values()->all();
        $preview = collect($preview)->map($collapse)->unique()->sort()->values()->all();

        $this->assertSame($expected, $preview, $case['description'] ?? basename($file));
    }
}
