<?php

namespace Tests\Feature\Docs;

use App\Support\Docs\Importer;
use App\Support\Docs\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Phar;
use PharData;
use Tests\TestCase;

/**
 * Each test imports into its own temporary docs folder. The fixtures are
 * small docs-site folders in tests/fixtures/docs.
 */
abstract class DocsTestCase extends TestCase
{
    protected string $docsPath;

    protected string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = sys_get_temp_dir().'/fw-docs-test-'.Str::random(10);
        $this->docsPath = "{$this->scratch}/store";
        File::ensureDirectoryExists($this->docsPath);
        config(['framework.docs_path' => $this->docsPath]);
        Store::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scratch);
        Store::flush();

        parent::tearDown();
    }

    protected function docs(string $product, string $path = '/'): TestResponse
    {
        return $this->get('http://'.config("framework.docs_hosts.{$product}").$path);
    }

    /** A writable copy of a fixture's docs-site folder */
    protected function fixture(string $name): string
    {
        $copy = "{$this->scratch}/fixtures/".Str::random(6).'/docs-site';
        File::copyDirectory(base_path("tests/fixtures/docs/{$name}/docs-site"), $copy);

        return $copy;
    }

    /** docs-site.tar.gz with the folder as its single top folder, docs-site/ */
    protected function archive(string $docsSite): string
    {
        $parent = dirname($docsSite);
        $tar = "{$this->scratch}/archives/".Str::random(6).'/docs-site.tar';
        File::ensureDirectoryExists(dirname($tar));

        $phar = new PharData($tar);
        $phar->buildFromDirectory($parent);
        $phar->compress(Phar::GZ);
        unset($phar);
        @unlink($tar);

        return "{$tar}.gz";
    }

    protected function import(string $product, ?string $source = null): Importer
    {
        $importer = new Importer;
        $importer->import($product, $source ?? $this->fixture($product));
        Store::flush();

        return $importer;
    }
}
