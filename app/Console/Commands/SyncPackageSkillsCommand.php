<?php

namespace App\Console\Commands;

use App\Support\PackageSkills;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Records which skills each scaffolding package ships at the commit Cloud
 * pins it to, in database/seeders/masters/package-skills.json. It reads the
 * git trees at the pinned commits, never a working tree, so uncommitted work
 * in a package checkout can't make Cloud offer a skill the pin lacks.
 *
 *   php artisan fw:sync-package-skills
 *
 * It records the pins config/services.php ships with, not an environment
 * override. Run it after changing a pin there. PackageSkillsTest fails when
 * the record and the pins disagree.
 */
class SyncPackageSkillsCommand extends Command
{
    /** Where each language's package keeps its shipped skills, one folder per skill */
    public const SOURCES = [
        'r' => ['package' => 'framework', 'repo' => '../framework', 'skills' => 'inst/templates/skills'],
        'python' => ['package' => 'framework-py', 'repo' => '../framework-py', 'skills' => 'src/framework/templates/skills'],
        'stata' => ['package' => 'fw', 'repo' => '../framework-cli', 'skills' => 'internal/stata/skills'],
    ];

    protected $signature = 'fw:sync-package-skills
        {--r= : Path to a framework R package checkout}
        {--python= : Path to a framework-py checkout}
        {--stata= : Path to a framework-cli checkout}';

    protected $description = 'Record the skills each pinned scaffolding package ships';

    public function handle(): int
    {
        $record = [];

        foreach (self::SOURCES as $language => $source) {
            $repo = $this->option($language) ?: base_path($source['repo']);
            $ref = PackageSkills::defaultRef($language);
            if (blank($ref)) {
                $this->error("config/services.php has no {$source['package']} pin.");

                return self::FAILURE;
            }

            $git = fn (string ...$args) => Process::run(['git', '-C', $repo, ...$args]);
            // fw is pinned by release, which its tags name
            $commit = $git('rev-parse', '--verify', ($language === 'stata' ? "v{$ref}" : $ref).'^{commit}');
            if ($commit->failed()) {
                $this->error("{$repo} has no commit {$ref}. Fetch it first.");

                return self::FAILURE;
            }
            $commit = trim($commit->output());

            $tree = $git('ls-tree', '-r', '--name-only', $commit, $source['skills'].'/');
            if ($tree->failed()) {
                $this->error("{$source['skills']} is not in {$source['package']} {$commit}.");

                return self::FAILURE;
            }
            $skills = collect(explode("\n", trim($tree->output())))
                ->map(fn (string $path) => substr($path, strlen($source['skills']) + 1))
                ->filter(fn (string $path) => preg_match('#^[^/]+/SKILL\.md$#', $path))
                ->map(fn (string $path) => dirname($path))
                ->sort()->values()->all();

            $record[$language] = [
                'package' => $source['package'],
                'ref' => $ref,
                'commit' => $commit,
                'version' => $this->version($git, $commit, $language),
                'skills' => $skills,
            ];
        }

        File::put(PackageSkills::path(), json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        foreach ($record as $language => $entry) {
            $this->info("{$entry['package']} {$entry['version']}: ".implode(', ', $entry['skills']));
        }

        return self::SUCCESS;
    }

    private function version(callable $git, string $commit, string $language): ?string
    {
        [$file, $pattern] = match ($language) {
            'r' => ['DESCRIPTION', '/^Version:\s*(\S+)/m'],
            'python' => ['pyproject.toml', '/^version\s*=\s*"([^"]+)"/m'],
            default => [null, null],
        };

        if ($file === null) {
            return PackageSkills::defaultRef('stata');
        }

        $show = $git('show', "{$commit}:{$file}");

        return $show->successful() && preg_match($pattern, $show->output(), $m) ? $m[1] : null;
    }
}
