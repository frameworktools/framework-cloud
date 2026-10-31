<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Copies Framework's tool definitions (inst/features) and its skill and
 * package sets (inst/sets) from the framework R package at a commit, into
 * database/seeders/masters/r, and records each file's SHA-256 in lock.json.
 * The R package is canonical, so R, Python, fw, and the cloud offer the same
 * pieces. PiecesLockTest fails when a copy drifts from its lock entry.
 *
 *   php artisan fw:sync-pieces ../framework 8bb43cd5e5c2dd04c21f4a7cd33686871386cb21
 */
class SyncPiecesCommand extends Command
{
    /** Source folders in the R repository, copied under the same names */
    public const FOLDERS = ['inst/features' => 'features', 'inst/sets/skills' => 'sets/skills', 'inst/sets/packages' => 'sets/packages'];

    protected $signature = 'fw:sync-pieces {repo : Path to a framework R package checkout} {ref : The full commit SHA to copy from}';

    protected $description = 'Copy Framework\'s tools, skill sets, and package sets from the framework R package';

    public static function dir(): string
    {
        return database_path('seeders/masters/r');
    }

    public function handle(): int
    {
        $repo = $this->argument('repo');
        $git = fn (string ...$args) => Process::run(['git', '-C', $repo, ...$args]);

        $resolved = $git('rev-parse', '--verify', $this->argument('ref').'^{commit}');
        if ($resolved->failed()) {
            $this->error("{$repo} has no commit {$this->argument('ref')}.");

            return self::FAILURE;
        }
        $commit = trim($resolved->output());

        File::deleteDirectory(self::dir());
        $files = [];

        foreach (self::FOLDERS as $source => $target) {
            $list = $git('ls-tree', '--name-only', "{$commit}:{$source}");
            if ($list->failed()) {
                $this->error("{$source} is not in {$commit}.");

                return self::FAILURE;
            }

            foreach (array_filter(explode("\n", trim($list->output()))) as $name) {
                if (! str_ends_with($name, '.json')) {
                    continue;
                }
                $content = $git('show', "{$commit}:{$source}/{$name}")->throw()->output();
                File::ensureDirectoryExists(self::dir()."/{$target}");
                File::put(self::dir()."/{$target}/{$name}", $content);
                $files["{$target}/{$name}"] = ['source' => "{$source}/{$name}", 'sha256' => hash('sha256', $content)];
            }
        }

        ksort($files);
        File::put(self::dir().'/lock.json', json_encode(['commit' => $commit, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info('Copied '.count($files)." tools and sets from {$commit}.");

        return self::SUCCESS;
    }
}
