<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Copies the spec schemas from the framework R package at a commit and
 * records each file's source and SHA-256 in resources/schema/schema.lock.json.
 * Copying is a manual step. SchemaLockTest fails when a copy drifts from
 * its lock entry.
 *
 *   php artisan fw:sync-schemas ../framework c74051f2f75a5b20f41a2281bb548ff57310cf58
 */
class SyncSchemasCommand extends Command
{
    /** Copied file (in resources/schema) => source path in the R repository */
    public const FILES = [
        'layer.v1.json' => 'inst/schema/layer.v1.json',
        'layers.v1.json' => 'inst/schema/layers.v1.json',
    ];

    public const LOCK = 'schema/schema.lock.json';

    protected $signature = 'fw:sync-schemas {repo : Path to a framework R package checkout} {ref : The full commit SHA to copy from}';

    protected $description = 'Copy the spec schemas from the framework R package and update their lock file';

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

        $files = [];
        foreach (self::FILES as $name => $source) {
            $show = $git('show', "{$commit}:{$source}");
            if ($show->failed()) {
                $this->error("{$source} is not in {$commit}.");

                return self::FAILURE;
            }

            File::put(resource_path("schema/{$name}"), $show->output());
            $files[$name] = ['source' => $source, 'sha256' => hash('sha256', $show->output())];
        }

        File::put(resource_path(self::LOCK), json_encode(['commit' => $commit, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info("Copied the schemas from {$commit}.");

        return self::SUCCESS;
    }
}
