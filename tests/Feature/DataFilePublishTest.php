<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactRepresentation;
use App\Support\DataFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataFilePublishTest extends TestCase
{
    use RefreshDatabase;

    private function publish(string $filename, string $content): array
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($owner)->create();
        $this->actingAs($owner)->post("/projects/{$project->id}/artifacts", [
            'file' => UploadedFile::fake()->createWithContent($filename, $content), 'visibility' => 'unlisted',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return [$owner, Document::firstOrFail()];
    }

    public function test_a_csv_becomes_a_typed_table_with_column_summaries(): void
    {
        $csv = "\xEF\xBB\xBFparticipant_id,site,visit_date,completed,score,,score\n"
            ."007,North,2026-01-04,TRUE,12.5,a,1\n"
            ."012,South,2026-02-11,FALSE,NA,b,2\n"
            ."031,North,,TRUE,9.25,c,3\n"
            ."044,,2026-03-01,NA,15,d,4\n";
        [, $document] = $this->publish('fuel.csv', $csv);
        $version = $document->versions()->firstOrFail();
        $this->assertSame('table', $document->kind);
        Storage::disk('local')->assertExists($version->representationPath());

        $table = ArtifactRepresentation::parse(Storage::disk('local')->get($version->representationPath()))['tables'][0];
        $columns = collect($table['columns'])->keyBy('name');
        $this->assertSame(['participant_id', 'site', 'visit_date', 'completed', 'score', 'column_6', 'score_2'], $columns->keys()->all());
        $this->assertSame(['string', 'string', 'date', 'boolean', 'number'], $columns->take(5)->pluck('type')->values()->all());
        $this->assertSame('007', $table['rows'][0][0]);
        $this->assertSame(1, $columns['score']['summary']['missing']);
        $this->assertEqualsWithDelta(12.25, $columns['score']['summary']['mean'], 1e-9);
        $this->assertEqualsWithDelta(12.5, $columns['score']['summary']['median'], 1e-9);
        $this->assertSame(['2026-01-04', '2026-03-01'], [$columns['visit_date']['summary']['min'], $columns['visit_date']['summary']['max']]);
        $this->assertSame(['value' => true, 'count' => 2], $columns['completed']['summary']['top'][0]);
        $this->assertSame(4, $table['row_count']);

        $this->get($document->shareUrl())->assertOk()
            ->assertSee('Download fuel.csv')->assertDontSee('Download JSON')
            ->assertSee('participant_id')->assertSee('CSV')->assertDontSee('Python');

        $this->delete("/published/{$document->id}")->assertRedirect();
        Storage::disk('local')->assertMissing($version->representationPath());
        Storage::disk('local')->assertMissing($version->disk_path);
    }

    public function test_a_long_csv_reports_its_true_row_count_and_profiles_every_row(): void
    {
        $lines = ['id,value'];
        for ($i = 1; $i <= 2500; $i++) {
            $lines[] = "{$i},".($i % 10);
        }
        [, $document] = $this->publish('long.tsv', str_replace(',', "\t", implode("\n", $lines)));
        $path = $document->versions()->firstOrFail()->representationPath();
        $table = ArtifactRepresentation::parse(Storage::disk('local')->get($path))['tables'][0];
        $this->assertSame(2500, $table['row_count']);
        $this->assertCount(1000, $table['rows']);
        $this->assertTrue($table['truncated']);
        $this->assertSame(2500, $table['columns'][0]['summary']['count']);
        $this->assertSame(10, $table['columns'][1]['summary']['distinct']);
        $this->get($document->shareUrl())->assertOk()->assertSee('Showing the first 1,000 of 2,500 rows', false)->assertSee('TSV');
    }

    public function test_other_data_formats_are_shared_for_download(): void
    {
        [, $document] = $this->publish('survey.parquet', "PAR1\x00\x01binary");
        $this->assertSame('data', $document->kind);
        $this->get($document->shareUrl())->assertOk()
            ->assertSee('Download this data file to open it in R, Python, Stata, or a spreadsheet.')
            ->assertSee('Download survey.parquet');
    }

    public function test_csvs_that_are_too_wide_or_not_tables_fall_back_to_the_plain_preview(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode(',', range(1, DataFile::MAX_COLUMNS + 1))."\n");
        $this->assertNull(DataFile::representation($path, ',', 'wide.csv'));
        file_put_contents($path, "id\n".PHP_INT_MAX."0\n1\n");
        $parsed = ArtifactRepresentation::parse(DataFile::representation($path, ',', 'ids.csv'));
        $this->assertSame('string', $parsed['tables'][0]['columns'][0]['type']);
        unlink($path);

        [, $document] = $this->publish('legacy.csv', "a,b\n1,2\n");
        $version = $document->versions()->firstOrFail();
        Storage::disk('local')->delete($version->representationPath());
        $this->get($document->shareUrl())->assertOk()->assertSee('Download legacy.csv');
    }

    public function test_merging_copies_the_table_with_its_versions(): void
    {
        [$owner, $source] = $this->publish('first.csv', "x\n1\n2\n");
        $target = Document::create(['project_id' => $source->project_id, 'title' => 'Target', 'slug' => 'target', 'key' => Document::newKey(),
            'content_type' => 'text/csv', 'disk_path' => 'artifacts/t/content', 'size_bytes' => 4, 'visibility' => 'unlisted', 'kind' => 'table']);
        Storage::disk('local')->put('artifacts/t/content', "x\n9\n");
        $this->actingAs($owner)->post("/published/{$target->id}/merge", ['source_id' => $source->id])->assertSessionHasNoErrors();
        $copied = DocumentVersion::where('document_id', $target->id)->orderByDesc('number')->firstOrFail();
        $this->assertNotSame($source->versions()->first()->representationPath(), $copied->representationPath());
        Storage::disk('local')->assertExists($copied->representationPath());
    }
}
