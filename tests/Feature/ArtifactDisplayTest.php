<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\ArtifactPublisher;
use App\Support\RatioTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
            ->assertSee('Coefficient plot')->assertSee('95% confidence interval (Wald t)')->assertSee('data-phiz=', false)->assertSee('vega-embed', false)->assertSee('Observations')
            ->assertSee('R²')->assertSee('Sample and degrees of freedom')->assertSee('Model fit')->assertSee('scope="rowgroup"', false)->assertSee('outcome ~ treatment')->assertSee('CI lower')->assertSee('Decimal places')->assertSee('data-table-precision type="number"', false)
            ->assertSee('data-table-search-open', false)->assertSee('Search terms')->assertSee('Download CSV')->assertDontSee('preview CSV')
            ->assertDontSee('data-table-sort=', false)->assertDontSee('aria-sort=', false)
            ->assertSee('Download JSON')->assertDontSee('Open original')->assertSee('<h1', false)
            ->assertSee('lm()')->assertDontSee('Source details')
            ->assertDontSee('</script><script>window.intruded=true</script>', false);
        $this->assertStringContainsString('\\u003C\/script\\u003E', $response->getContent());
        $this->get($version->url().'?embed=1')->assertOk()->assertSee('Coefficient plot')
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
        $this->get($version->url())->assertOk()->assertSee('are shortened or left out here')
            ->assertSee('Large integer columns are shown as text')->assertSee('9007199254740993')
            ->assertDontSee('9007199254740992');
    }

    public function test_an_artifact_whose_file_is_missing_still_opens(): void
    {
        Storage::fake('local');
        $version = app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent('model.json', json_encode($this->model())),
        ]);
        Storage::disk($version->disk())->delete($version->entryPath());
        Cache::flush();

        $this->get($version->url())->assertOk();
        $this->get($version->url().'?embed=1')->assertOk();
        $this->getJson('/api/embed/v1/artifacts/'.$version->document->key)->assertOk()->assertJsonPath('kind', 'page');
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
        foreach (['stata-regress' => ['Stata', 'regress', 'Ordinary least squares regression', 'Coefficient plot', 'R²'],
            'stata-logit' => ['Stata', 'logit', 'Odds ratio'],
            'stata-mixed' => ['Stata', 'mixed', 'Linear mixed model', 'Random effects', 'Residual'],
            'stata-stcox' => ['Stata', 'stcox', 'Cox proportional hazards model', 'Hazard ratio']] as $fixture => $expected) {
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

    public function test_ratio_models_switch_the_table_and_plot_between_the_ratio_and_the_model_scale(): void
    {
        Storage::fake('local');
        $json = file_get_contents(base_path('tests/fixtures/artifacts/stata-logit.json'));
        $version = app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent('stata-logit.json', $json),
        ]);

        $html = $this->get($version->url())->assertOk()->getContent();
        $page = new \DOMXPath(tap(new \DOMDocument, fn ($dom) => @$dom->loadHTML($html)));
        // Each view's table header holds the switch, in place of a search box
        foreach (['ratio' => ['true', 'false'], 'model' => ['false', 'true']] as $view => [$ratioPressed, $modelPressed]) {
            $buttons = $page->query("//*[@data-scale-view='{$view}']//section[@data-artifact-table]/div[1]//*[@data-scale-switch]//button");
            $this->assertSame(["ratio:Odds ratio:{$ratioPressed}", "model:Log odds:{$modelPressed}"], array_map(
                fn ($b) => $b->getAttribute('data-scale').':'.trim($b->textContent).':'.$b->getAttribute('aria-pressed'), iterator_to_array($buttons)));
        }
        $this->assertSame(2, $page->query('//*[@data-scale-view]//*[@data-table-search and @hidden]')->length);
        $this->assertSame(1, $page->query('//*[@data-scale-view="ratio" and not(@hidden)]//table')->length);
        $this->assertSame(1, $page->query('//*[@data-scale-view="model" and @hidden]//table')->length);
        $ratioHeaders = array_map(fn ($th) => trim($th->textContent), iterator_to_array($page->query('//*[@data-scale-view="ratio"]//thead//th')));
        $modelHeaders = array_map(fn ($th) => trim($th->textContent), iterator_to_array($page->query('//*[@data-scale-view="model"]//thead//th')));
        $this->assertSame(array_replace($modelHeaders, [1 => 'Odds ratio']), $ratioHeaders);
        // The plot no longer has its own scale buttons
        $this->assertSame(0, $page->query('//*[@data-coefficient-plot]//*[@data-scale]')->length);
    }

    public function test_the_ratio_table_keeps_the_columns_and_rescales_estimates_errors_and_intervals(): void
    {
        $columns = [['name' => 'term', 'type' => 'string'], ['name' => 'estimate', 'type' => 'number'], ['name' => 'std_error', 'type' => 'number'],
            ['name' => 'statistic', 'type' => 'number'], ['name' => 'p_value', 'type' => 'number'], ['name' => 'conf_low', 'type' => 'number'], ['name' => 'conf_high', 'type' => 'number']];
        $table = ['columns' => $columns, 'rows' => [['(Intercept)', 0.0, 0.5, 0.0, 1.0, -1.0, 1.0], ['x', log(2), 0.1, 6.93, 0.002, log(1.5), log(3)]]];
        $ratio = RatioTable::from($table, 'Odds ratio');
        $this->assertSame(['term', 'odds_ratio', 'std_error', 'statistic', 'p_value', 'conf_low', 'conf_high'], array_column($ratio['columns'], 'name'));
        $this->assertEqualsWithDelta(['x', 2.0, 0.2, 6.93, 0.002, 1.5, 3.0], $ratio['rows'][1], 1e-12);
        $this->assertEqualsWithDelta(['(Intercept)', 1.0, 0.5, 0.0, 1.0, exp(-1), exp(1)], $ratio['rows'][0], 1e-12);
        $this->assertNull(RatioTable::ratio('Coefficient'));
    }
}
