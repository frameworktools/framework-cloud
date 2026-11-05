<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BuildArtifactReader extends Command
{
    protected $signature = 'artifacts:build-reader {--output= : Destination HTML template}';

    protected $description = 'Compile the portable artifact reader through Blade';

    public function handle(): int
    {
        $temporary = tempnam(sys_get_temp_dir(), 'framework-reader-');
        try {
            $process = new Process([base_path('node_modules/.bin/esbuild'), resource_path('js/static-artifact-reader.js'), '--bundle', '--format=iife', '--minify', '--outfile='.$temporary]);
            $process->mustRun();
            $html = view('artifacts.static', ['readerJs' => File::get($temporary), 'readerCss' => File::get(resource_path('static-reader/reader.css'))])->render();
            $path = $this->option('output') ?: resource_path('static-reader/reader.html');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $html);
            $this->info('Portable reader compiled: '.$path);
        } finally {
            File::delete($temporary);
        }

        return self::SUCCESS;
    }
}
