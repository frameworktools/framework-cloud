<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\ArtifactPublisher;
use App\Support\ArtifactRepresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Objects published from R and Python, using real exporter output in tests/fixtures/artifacts.
 * The contract is docs/notes/2026-11-02-artifact-objects-contract.md.
 */
class ArtifactObjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/artifacts/'.$name));
    }

    private function publish(string $fixture, string $filename)
    {
        return app(ArtifactPublisher::class)->publish(Project::factory()->create(), [
            'file' => UploadedFile::fake()->createWithContent($filename, $this->fixture($fixture)),
        ]);
    }

    public function test_r_and_python_tests_parse_without_their_data(): void
    {
        foreach (['r-ttest-welch.json', 'python-ttest-welch.json'] as $fixture) {
            $test = ArtifactRepresentation::parse($this->fixture($fixture));
            $this->assertSame('test', $test['kind'], $fixture);
            $this->assertStringContainsString('Welch', $test['test']['method']);
            $this->assertSame('two.sided', $test['test']['alternative']);
            $this->assertArrayHasKey('p_value', $test['test']);
            $this->assertSame(0.95, $test['test']['conf_int']['level']);
        }
    }

    public function test_a_test_needs_a_method_and_drops_malformed_parts(): void
    {
        $document = ['schema' => 'framework.artifact/v1', 'kind' => 'test', 'source' => ['language' => 'r', 'class' => ['htest']],
            'tables' => [], 'metrics' => [], 'test' => ['statistic' => ['name' => 't', 'value' => 2.1]]];
        $this->assertNull(ArtifactRepresentation::parse(json_encode($document)));

        $document['test'] += ['method' => 'Welch Two Sample t-test', 'alternative' => 'sideways', 'p_value' => 3,
            'conf_int' => ['level' => 0.95, 'low' => 2, 'high' => 1], 'estimates' => [['name' => 'mean', 'value' => 'high']]];
        $test = ArtifactRepresentation::parse(json_encode($document))['test'];
        $this->assertSame(['method' => 'Welch Two Sample t-test', 'statistic' => ['name' => 't', 'value' => 2.1]], $test);
    }

    public function test_models_carry_their_description_and_table_roles(): void
    {
        $glm = ArtifactRepresentation::parse($this->fixture('r-glm-logit.json'));
        $this->assertSame(['type' => 'glm', 'family' => 'binomial', 'link' => 'logit', 'response' => 'am', 'exponentiate' => 'Odds ratio'], $glm['model']);
        $this->assertSame('coefficients', $glm['tables'][0]['role']);

        $lmer = ArtifactRepresentation::parse($this->fixture('r-lmer.json'));
        $this->assertSame(['coefficients', 'random_effects'], array_column($lmer['tables'], 'role'));

        $xgboost = ArtifactRepresentation::parse($this->fixture('python-xgboost.json'));
        $this->assertSame('importance', $xgboost['tables'][0]['role']);
        $this->assertSame('tree_ensemble', $xgboost['model']['type']);
    }

    public function test_column_summaries_are_kept_and_bad_ones_dropped(): void
    {
        $table = ArtifactRepresentation::parse($this->fixture('r-tibble.json'))['tables'][0];
        $mass = collect($table['columns'])->firstWhere('name', 'body_mass_g');
        $this->assertSame('Body mass (g)', $mass['label']);
        $this->assertSame(count($mass['summary']['histogram']['edges']) - 1, count($mass['summary']['histogram']['counts']));
        $this->assertArrayHasKey('top', collect($table['columns'])->firstWhere('name', 'species')['summary']);

        $document = json_decode($this->fixture('r-tibble.json'), true);
        $document['tables'][0]['columns'][0]['summary'] = ['missing' => -1, 'histogram' => ['edges' => [3, 1], 'counts' => [2]], 'top' => 'many'];
        $document['tables'][0]['role'] = 'mystery';
        $parsed = ArtifactRepresentation::parse(json_encode($document))['tables'][0];
        $this->assertArrayNotHasKey('summary', $parsed['columns'][0]);
        $this->assertNull($parsed['role']);
    }

    public function test_a_logistic_model_shows_odds_ratios_with_a_switch_to_log_odds(): void
    {
        $version = $this->publish('r-glm-logit.json', 'model.json');

        $this->get($version->url())->assertOk()
            ->assertSee('R &middot; <span class="font-mono">glm()</span>', false)
            ->assertSee('Generalized linear model, binomial family, logit link')
            ->assertSee('Coefficient forest plot')
            ->assertSee('data-scale="ratio"', false)->assertSee('Log odds')
            ->assertSee('Odds ratio (95% CI)')
            ->assertSee('data-coefficient-specs', false);
    }

    public function test_mixed_models_show_random_effects_and_group_counts(): void
    {
        $version = $this->publish('r-lmer.json', 'model.json');

        $this->get($version->url())->assertOk()
            ->assertSee('lmer()')->assertSee('Mixed-effects model')
            ->assertSee('Random effects')->assertSee('Std. deviation')
            ->assertSee('Groups: Subject')
            ->assertSeeInOrder(['Correlations', 'Subject:', '(Intercept) and Days'])
            ->assertDontSee('>NA<', false);
    }

    public function test_boosted_models_show_feature_importance(): void
    {
        $version = $this->publish('python-xgboost.json', 'model.json');

        $this->get($version->url())->assertOk()
            ->assertSee('Python &middot; <span class="font-mono">XGBClassifier</span>', false)
            ->assertSee('Feature importance')->assertSee("each feature's gain", false)->assertDontSee('Coefficient forest plot')
            ->assertSee('Trees')->assertSee('Learning rate');
    }

    public function test_hypothesis_tests_publish_as_tests_and_show_their_result(): void
    {
        $version = $this->publish('r-ttest-welch.json', 'test.json');

        $this->assertSame('test', $version->kind);
        $this->get($version->url())->assertOk()
            ->assertSee('Welch Two Sample t-test')->assertSee('Two-sided alternative hypothesis')
            ->assertSee('95% confidence interval')->assertSee('The dashed line marks the null value', false)
            ->assertSee('Published test result.');
    }

    public function test_tables_show_a_profile_of_their_columns(): void
    {
        $version = $this->publish('r-tibble.json', 'table.json');

        $this->get($version->url())->assertOk()
            ->assertSee('Summaries over all 60 rows')
            ->assertSee('Histogram of body_mass_g')->assertSee('Body mass (g)')
            ->assertSee('Chinstrap')->assertSee('Earliest');
    }

    public function test_svg_figures_show_as_images_on_a_white_card(): void
    {
        $version = $this->publish('r-ggplot.svg', 'figure.svg');

        $this->assertSame('image', $version->kind);
        $this->get($version->url())->assertOk()
            ->assertSee('<img src="'.e($version->contentUrl()).'"', false)
            ->assertDontSee('<iframe', false)
            ->assertSee('Download figure.svg');
    }

    public function test_vega_lite_charts_draw_in_a_sandbox(): void
    {
        $version = $this->publish('python-altair.vl.json', 'figure.vl.json');
        $this->assertSame('chart', $version->kind);

        $page = $this->get($version->url())->assertOk()->assertSee('data-chart-frame', false)->assertDontSee('data-phiz=', false);
        preg_match('/data-src="([^"]+)"/', $page->getContent(), $match);
        $chartUrl = html_entity_decode($match[1]);

        $chart = $this->get($chartUrl)->assertOk()->assertSee('data-phiz=', false)->assertSee('vega-embed', false);
        $policy = $chart->headers->get('Content-Security-Policy');
        $this->assertStringStartsWith('sandbox allow-scripts;', $policy);
        $this->assertStringContainsString("default-src 'none'", $policy);
        $this->assertStringNotContainsString('connect-src', $policy);

        $this->get(preg_replace('#/chart/(\d+)/[^/?]+#', '/chart/$1/0.bad', $chartUrl))->assertForbidden();
    }

    public function test_a_json_file_that_is_not_vega_lite_is_not_drawn(): void
    {
        $project = Project::factory()->create();
        $version = app(ArtifactPublisher::class)->publish($project, [
            'file' => UploadedFile::fake()->createWithContent('figure.vl.json', '{"$schema": "https://example.org/other.json"}'),
        ]);

        $this->get($version->url())->assertOk()->assertSee('This chart cannot be shown.')->assertDontSee('data-chart-frame', false);
    }
}
