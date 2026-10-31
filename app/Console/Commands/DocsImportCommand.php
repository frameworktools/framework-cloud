<?php

namespace App\Console\Commands;

use App\Support\Docs\Importer;
use App\Support\Docs\InvalidSite;
use App\Support\Docs\LegacyR;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Import a docs site for r.framework.pub, py.framework.pub, or
 * cli.framework.pub.
 *
 *   php artisan fw:docs-import py ../framework-py/docs-site.tar.gz
 *   php artisan fw:docs-import py ../framework-py/docs/site
 *   php artisan fw:docs-import cli --release
 *   php artisan fw:docs-import r --legacy
 */
class DocsImportCommand extends Command
{
    protected $signature = 'fw:docs-import
        {product : r, py, or cli}
        {source? : A docs-site.tar.gz file or a docs-site folder}
        {--release : Download docs-site.tar.gz from the product\'s GitHub releases (see docs_repositories in config/framework.php)}
        {--legacy : (r only) Import the guides in resources/docs/guides and the reference in docs.db}';

    protected $description = 'Import a docs site from an archive, a folder, or a GitHub release';

    public function handle(): int
    {
        $product = (string) $this->argument('product');
        $source = $this->argument('source');
        $chosen = array_filter([$source !== null, $this->option('release'), $this->option('legacy')]);

        if (count($chosen) !== 1) {
            $this->error('Give a source, --release, or --legacy (one of them).');

            return self::FAILURE;
        }

        $importer = new Importer;
        $legacyDir = null;

        try {
            if ($this->option('release')) {
                $this->line('Looking for '.Importer::ASSET.' in the releases of '.config("framework.docs_repositories.{$product}.repository").'…');
                $result = $importer->importRelease($product);
            } elseif ($this->option('legacy')) {
                if ($product !== 'r') {
                    $this->error('--legacy is for the r product only.');

                    return self::FAILURE;
                }
                $legacyDir = storage_path('framework/cache/docs-legacy-export-'.Str::random(8));
                LegacyR::export($legacyDir);
                $result = $importer->import($product, $legacyDir);
            } else {
                $result = $importer->import($product, (string) $source);
            }
        } catch (InvalidSite $e) {
            $this->error('The docs site was not imported:');
            foreach ($e->problems as $problem) {
                $this->line("  - {$problem}");
            }

            return self::FAILURE;
        } finally {
            if ($legacyDir !== null) {
                File::deleteDirectory($legacyDir);
            }
        }

        foreach ($importer->warnings as $warning) {
            $this->warn("Warning: {$warning}");
        }

        if (isset($result['tag'])) {
            $this->line("Release {$result['tag']}".($result['prerelease'] ? ' (a pre-release)' : '').'.');
        }

        $this->info("Imported {$product}: {$result['guides']} guides, {$result['entries']} reference entries, and {$result['images']} images (stored as {$result['release']}).");

        return self::SUCCESS;
    }
}
