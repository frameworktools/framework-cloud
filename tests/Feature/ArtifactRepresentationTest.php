<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Support\ArtifactPreview;
use App\Support\ArtifactPublisher;
use App\Support\ArtifactRepresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtifactRepresentationTest extends TestCase
{
    use RefreshDatabase;

    private function representation(): array
    {
        return ['schema' => 'framework.artifact/v1', 'kind' => 'table',
            'source' => ['language' => 'r', 'class' => ['tbl_df', 'tbl', 'data.frame']],
            'tables' => [['title' => 'Effects', 'columns' => [
                ['name' => 'term', 'type' => 'string'], ['name' => 'estimate', 'type' => 'number'],
                ['name' => 'included', 'type' => 'boolean'], ['name' => 'date', 'type' => 'date'],
                ['name' => 'time', 'type' => 'datetime'],
            ], 'rows' => [['<script>term</script>', 1.25, true, '2026-11-02', '2026-11-02T12:03:04.123Z'], ['second', null, false, null, null]], 'row_count' => 2]],
            'metrics' => [['name' => 'Observations', 'value' => 100], ['name' => 'Converged', 'value' => true]]];
    }

    private function publish(array|string $representation, array $options = []): Document
    {
        $project = Project::factory()->create();
        $version = app(ArtifactPublisher::class)->publish($project, $options + ['file' => UploadedFile::fake()->createWithContent('output.json', is_string($representation) ? $representation : json_encode($representation))]);

        return $version->document;
    }

    public function test_typed_values_source_metadata_and_model_formula_are_preserved(): void
    {
        $input = $this->representation();
        $input['kind'] = 'model';
        $input['formula'] = 'outcome ~ exposure + age';
        $input['source'] = ['language' => 'python', 'class' => ['statsmodels.regression.linear_model.RegressionResultsWrapper']];
        $parsed = ArtifactRepresentation::parse(json_encode($input));
        $this->assertSame('model', $parsed['kind']);
        $this->assertSame($input['source'], $parsed['source']);
        $this->assertSame($input['tables'][0]['rows'], $parsed['tables'][0]['rows']);
        $this->assertSame($input['metrics'], $parsed['metrics']);
        $this->assertSame($input['formula'], $parsed['formula']);
        $this->assertSame(5, $parsed['tables'][0]['column_count']);
        $this->assertFalse($parsed['truncated']);
    }

    public function test_schema_infers_kind_and_preview_uses_each_immutable_version(): void
    {
        Storage::fake('local');
        $document = $this->publish($this->representation(), ['password' => 'secret-pass']);
        $this->assertSame('table', $document->kind);
        $first = $document->latestVersion();
        $input = $this->representation();
        $input['kind'] = 'model';
        $input['tables'][0]['rows'][0][1] = 9.5;
        app(ArtifactPublisher::class)->publish($document->project, ['slug' => $document->slug, 'file' => UploadedFile::fake()->createWithContent('model.json', json_encode($input))]);
        $preview = ArtifactPreview::forVersion($first);
        $this->assertSame('representation', $preview['type']);
        $this->assertSame(1.25, $preview['tables'][0]['rows'][0][1]);
        $this->assertSame(9.5, ArtifactPreview::forVersion($document->fresh()->latestVersion())['tables'][0]['rows'][0][1]);
        $this->get($first->url())->assertSee('Password-protected artifact')->assertDontSee('Effects');
        $this->get($first->rawUrl())->assertForbidden();
    }

    public function test_preview_bounds_keep_original_dimensions_and_mark_truncation(): void
    {
        $input = $this->representation();
        $table = $input['tables'][0];
        $table['columns'] = array_fill(0, 51, ['name' => str_repeat('c', 250), 'type' => 'number']);
        $table['rows'] = array_fill(0, 1001, array_fill(0, 51, 2.1));
        $table['row_count'] = 1001;
        $input['tables'] = array_fill(0, 11, $table);
        $input['metrics'] = array_fill(0, 51, ['name' => str_repeat('m', 250), 'value' => 2]);
        $input['source']['class'] = array_fill(0, 21, str_repeat('x', 250));
        $parsed = ArtifactRepresentation::parse(json_encode($input));
        $this->assertCount(10, $parsed['tables']);
        $this->assertCount(50, $parsed['tables'][0]['columns']);
        $this->assertCount(1000, $parsed['tables'][0]['rows']);
        $this->assertCount(50, $parsed['tables'][0]['rows'][0]);
        $this->assertSame(1001, $parsed['tables'][0]['row_count']);
        $this->assertSame(51, $parsed['tables'][0]['column_count']);
        $this->assertCount(50, $parsed['metrics']);
        $this->assertCount(20, $parsed['source']['class']);
        $this->assertSame(200, mb_strlen($parsed['source']['class'][0]));
        $this->assertTrue($parsed['truncated']);
        $this->assertTrue($parsed['tables'][0]['truncated']);
    }

    public function test_long_scalar_values_remain_text_and_mark_the_preview_truncated(): void
    {
        $input = $this->representation();
        $input['tables'][0]['rows'][0][0] = str_repeat('<', 10001);
        $input['metrics'][0]['value'] = null;
        $parsed = ArtifactRepresentation::parse(json_encode($input));
        $this->assertSame(10000, mb_strlen($parsed['tables'][0]['rows'][0][0]));
        $this->assertSame(str_repeat('<', 10000), $parsed['tables'][0]['rows'][0][0]);
        $this->assertNull($parsed['metrics'][0]['value']);
        $this->assertTrue($parsed['tables'][0]['truncated']);
        $this->assertTrue($parsed['truncated']);
    }

    public function test_unsafe_integer_columns_and_metrics_preserve_exact_values_as_text(): void
    {
        $input = $this->representation();
        $input['tables'][0]['rows'][0][1] = 9007199254740993;
        $input['tables'][0]['rows'][1][1] = 2.25;
        $input['metrics'][0]['value'] = -9007199254740993;
        $parsed = ArtifactRepresentation::parse(json_encode($input));
        $this->assertSame('string', $parsed['tables'][0]['columns'][1]['type']);
        $this->assertSame('9007199254740993', $parsed['tables'][0]['rows'][0][1]);
        $this->assertSame('2.25', $parsed['tables'][0]['rows'][1][1]);
        $this->assertSame('-9007199254740993', $parsed['metrics'][0]['value']);
        $this->assertNotEmpty($parsed['tables'][0]['precision_note']);
        $this->assertFalse($parsed['truncated']);
        $json = str_replace('9007199254740993', '9223372036854775808', json_encode($input));
        $this->assertNull(ArtifactRepresentation::parse($json));
    }

    public function test_malformed_schema_dimensions_types_and_nonfinite_numbers_are_rejected(): void
    {
        $base = $this->representation();
        $mutations = [
            fn (&$x) => $x['schema'] = 'framework.artifact/v2',
            fn (&$x) => $x['source']['language'] = 'php',
            fn (&$x) => $x['tables'][0]['row_count'] = 1,
            fn (&$x) => $x['tables'][0]['row_count'] = 999,
            fn (&$x) => $x['tables'][0]['rows'][0] = ['wrong width'],
            fn (&$x) => $x['tables'][0]['rows'][0][1] = '1.25',
            fn (&$x) => $x['tables'][0]['rows'][0][2] = 1,
            fn (&$x) => $x['tables'][0]['rows'][0][3] = '2026-02-30',
            fn (&$x) => $x['tables'][0]['rows'][0][4] = 'yesterday',
            fn (&$x) => $x['metrics'][0]['value'] = ['unsafe'],
            fn (&$x) => $x['source']['class'] = new \stdClass,
            fn (&$x) => $x['formula'] = ['not text'],
        ];
        foreach ($mutations as $change) {
            $input = $base;
            $change($input);
            $this->assertNull(ArtifactRepresentation::parse(json_encode($input)));
        }
        $this->assertNull(ArtifactRepresentation::parse(str_replace('1.25', '1e400', json_encode($base))));
        $this->assertNull(ArtifactRepresentation::parse('{invalid'));
    }

    public function test_unsupported_or_oversized_representations_fall_back_to_download(): void
    {
        Storage::fake('local');
        $document = $this->publish('{invalid', ['kind' => 'model']);
        $this->assertSame('download', ArtifactPreview::forVersion($document->latestVersion())['type']);
        $input = $this->representation();
        $input['schema'] = 'future-version';
        $document = $this->publish($input);
        $this->assertSame('download', ArtifactPreview::forVersion($document->latestVersion())['type']);
        $document = $this->publish(str_repeat(' ', ArtifactRepresentation::MAX_BYTES + 1), ['kind' => 'model']);
        $this->assertSame('download', ArtifactPreview::forVersion($document->latestVersion())['type']);
        $document = $this->publish(['ordinary' => 'JSON']);
        $this->assertSame('text', ArtifactPreview::forVersion($document->latestVersion())['type']);
    }
}
