<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtifactDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function model(): array
    {
        return ['schema' => 'framework.artifact/v1', 'kind' => 'model',
            'source' => ['language' => 'r', 'class' => ['lm']], 'formula' => 'outcome ~ treatment',
            'tables' => [['title' => 'Coefficients', 'columns' => [
                ['name' => 'term', 'type' => 'string'], ['name' => 'estimate', 'type' => 'number'],
                ['name' => 'conf_low', 'type' => 'number'], ['name' => 'conf_high', 'type' => 'number'],
            ], 'rows' => [['treatment', 1.25, -0.2, 2.7], ['</script><script>window.intruded=true</script>', -2, -3, -1]], 'row_count' => 2]],
            'metrics' => [['name' => 'nobs', 'value' => 100], ['name' => 'r.squared', 'value' => 0.75],
                ['name' => 'confidence_level', 'value' => 0.95], ['name' => 'interval_method', 'value' => 'wald-t']]];
    }

    public function test_models_have_coefficient_plots_typed_tables_and_readable_fit_statistics(): void
    {
        Storage::fake('local');
        $version = app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent('model.json', json_encode($this->model())),
        ]);
        $response = $this->get($version->url())->assertOk()
            ->assertSee('Coefficient forest plot')->assertSee('95% confidence interval (Wald t)')->assertSee('data-phiz=', false)->assertSee('vega-embed', false)->assertSee('Observations')
            ->assertSee('R²')->assertSee('Sample and degrees of freedom')->assertSee('Model fit')->assertSee('scope="rowgroup"', false)->assertSee('outcome ~ treatment')->assertSee('CI lower')->assertSee('Decimal places')->assertSee('data-table-precision type="number"', false)
            ->assertSee('data-table-search', false)->assertSee('Export preview CSV')
            ->assertDontSee('data-table-sort=', false)->assertDontSee('aria-sort=', false)
            ->assertSee('Download JSON')->assertDontSee('Open original')->assertSee('<h1', false)
            ->assertSee('lm()')->assertDontSee('Source details')
            ->assertDontSee('</script><script>window.intruded=true</script>', false);
        $this->assertStringContainsString('\\u003C\/script\\u003E', $response->getContent());
        $this->get($version->url().'?embed=1')->assertOk()->assertSee('Coefficient forest plot')
            ->assertDontSee('Published with Framework Cloud')->assertDontSee('Open original');
    }

    public function test_preview_reports_global_truncation_and_large_integer_normalization(): void
    {
        Storage::fake('local');
        $representation = $this->model();
        $representation['tables'] = array_fill(0, 11, $representation['tables'][0]);
        $representation['tables'][0]['rows'][0][1] = 9007199254740993;
        $version = app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent('model.json', json_encode($representation)),
        ]);
        $this->get($version->url())->assertOk()->assertSee('This is a limited preview.')
            ->assertSee('Large integer columns are shown as text')->assertSee('9007199254740993')
            ->assertDontSee('9007199254740992');
    }

    public function test_only_pages_with_a_chart_load_the_chart_scripts(): void
    {
        Storage::fake('local');
        $version = app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent('visits.csv', "site,visits\nNorth,12\nSouth,9\n"),
        ]);

        $this->get($version->url())->assertOk()->assertSee('North')
            ->assertDontSee('data-phiz=', false)->assertDontSee('vega-embed', false);
    }

    public function test_stata_models_show_their_language_command_and_random_effects(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        foreach (['stata-regress' => ['Stata', 'regress', 'Linear model', 'Coefficient forest plot', 'R²'],
            'stata-logit' => ['Stata', 'logit', 'Odds ratio'],
            'stata-mixed' => ['Stata', 'mixed', 'Mixed-effects model', 'Random effects', 'Residual'],
            'stata-stcox' => ['Stata', 'stcox', 'Survival model', 'Hazard ratio']] as $fixture => $expected) {
            $json = file_get_contents(base_path("tests/fixtures/artifacts/{$fixture}.json"));
            $version = app(ArtifactPublisher::class)->publish($project, [
                'file' => UploadedFile::fake()->createWithContent("{$fixture}.json", $json),
            ]);
            $response = $this->get($version->url())->assertOk()->assertDontSee('cannot be previewed');
            foreach ($expected as $text) {
                $response->assertSee($text);
            }
        }
    }
}
