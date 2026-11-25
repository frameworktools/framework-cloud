<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A table's download is always the whole table: the published file when the page holds part of it. */
class TableDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_large_data_file_downloads_the_published_file(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $rows = ['site,visits'];
        for ($i = 0; $i < 1500; $i++) {
            $rows[] = "site-{$i},{$i}";
        }
        $version = app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent('visits.tsv', str_replace(',', "\t", implode("\n", $rows))),
            'visibility' => 'public',
        ]);

        $this->get($version->document->publicUrl())->assertOk()
            ->assertSee('Showing the first 1,000 of 1,500 rows')
            ->assertSee('href="'.e($version->downloadUrl()).'" download', false)
            ->assertSee('Download TSV')
            ->assertDontSee('data-table-export', false)
            ->assertDontSee('preview', false);
    }

    public function test_a_whole_table_exports_from_the_page(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $version = app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent('visits.csv', "site,visits\nNorth,12\nSouth,9\n"),
            'visibility' => 'public',
        ]);

        $this->get($version->document->publicUrl())->assertOk()
            ->assertSee('data-table-export', false)->assertSee('Download CSV')->assertDontSee('Showing the first');
    }
}
