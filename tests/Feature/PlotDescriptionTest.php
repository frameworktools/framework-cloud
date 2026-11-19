<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\PlotDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** A figure published with a phiz plot description embeds as a live chart, with the figure as its fallback. */
class PlotDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->project = Project::factory()->for($user)->create(['slug' => 'cars']);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(base_path("tests/fixtures/artifacts/{$name}"));
    }

    private function publish(?string $description, string $slug = 'fuel', string $figure = 'r-ggplot.svg', string $name = 'figure.svg'): DocumentVersion
    {
        $input = ['slug' => $slug, 'title' => 'Fuel economy', 'file' => UploadedFile::fake()->createWithContent($name, $this->fixture($figure))];
        if ($description !== null) {
            $input['plot_description'] = UploadedFile::fake()->createWithContent('figure.phiz.json', $description);
        }

        return app(ArtifactPublisher::class)->publish($this->project, $input);
    }

    /** The artifact page's own figure, as opposed to other images such as the share dialog's QR code. */
    private function figureTag(DocumentVersion $version): string
    {
        return '<img src="'.e($version->contentUrl()).'"';
    }

    private function embed(DocumentVersion $version): TestResponse
    {
        return $this->getJson('/api/embed/v1/artifacts/'.$version->document->key.'/versions/'.$version->number)->assertOk();
    }

    public function test_a_described_figure_embeds_as_vega_lite_with_its_image_as_the_fallback(): void
    {
        $version = $this->publish($this->fixture('r-ggplot.phiz.json'));
        $key = $version->document->key;
        $this->assertSame('image', $version->kind);
        $this->assertTrue(Storage::disk('local')->exists(dirname($version->disk_path).'/figure.phiz.json'));

        $embed = $this->embed($version)->assertJson([
            'kind' => 'vega-lite',
            'alt' => 'Fuel economy falls with weight across 32 cars.',
            'data' => null,
            'image' => ['url' => "http://localhost/api/embed/v1/artifacts/{$key}/versions/1/image", 'content_type' => 'image/svg+xml', 'width' => 672, 'height' => 480],
        ])->json();
        $spec = $embed['spec'];
        $this->assertStringContainsString('vega-lite', $spec['$schema']);
        $this->assertSame('container', $spec['width']);
        $this->assertCount(3, $spec['layer']);
        $this->assertCount(32, $spec['data']['values']);

        // The page's theme wins: no chrome colors, fonts, background, or phiz dark theme
        $this->assertArrayNotHasKey('usermeta', $spec);
        $this->assertArrayNotHasKey('background', $spec);
        $config = json_encode($spec['config']);
        foreach (['"background"', '"labelColor"', '"titleColor"', '"gridColor"', '"fill"', '"stroke"', '"color"', 'Font"'] as $chrome) {
            $this->assertStringNotContainsString($chrome, $config);
        }
        // Sizes, layout, and the figure's palette stay
        $this->assertSame(11.7, $spec['config']['axis']['labelFontSize']);
        $this->assertSame(['#F8766D', '#00BA38', '#619CFF'], $spec['config']['range']['category']);

        // The fallback image is still served
        $this->get("/api/embed/v1/artifacts/{$key}/versions/1/image")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        // oEmbed still offers the figure as a photo
        $this->getJson('/api/oembed?url='.urlencode($version->document->publicUrl()).'&format=json')->assertOk()->assertJsonPath('type', 'photo');
    }

    public function test_the_description_is_compiled_at_publish_and_kept(): void
    {
        $version = $this->publish($this->fixture('r-ggplot.phiz.json'));
        // Removing the stored description doesn't change what is served: the compiled spec was kept at publish
        Storage::disk('local')->delete(PlotDescription::path($version));

        $this->embed($version)->assertJsonPath('kind', 'vega-lite');
    }

    public function test_an_invalid_description_leaves_the_figure_an_image(): void
    {
        Log::spy();
        $invalid = [
            'json' => '{not json',
            'version' => json_encode(['phiz' => 2, 'layers' => [['geom' => 'point']]]),
            'geom' => json_encode(['phiz' => 1, 'data' => [['x' => 1]], 'aes' => ['x' => 'x'], 'layers' => [['geom' => 'teapot']]]),
            'raw' => json_encode(['phiz' => 1, 'data' => [['x' => 1]], 'layers' => [['geom' => 'point', 'raw' => ['mark' => 'image']]]]),
            'url' => json_encode(['phiz' => 1, 'data' => ['url' => 'https://example.com/cars.csv'], 'layers' => [['geom' => 'point']]]),
            'href' => json_encode(['phiz' => 1, 'data' => [['x' => 1]], 'aes' => ['x' => 'x'], 'layers' => [['geom' => 'point', 'href' => 'https://example.com']]]),
            'theme' => json_encode(['phiz' => 1, 'data' => [['x' => 1]], 'aes' => ['x' => 'x'], 'layers' => [['geom' => 'point']], 'theme' => ['config' => ['background' => 'red']]]),
        ];
        foreach ($invalid as $slug => $description) {
            $version = $this->publish($description, $slug);
            $this->embed($version)->assertJson(['kind' => 'image', 'spec' => null, 'alt' => 'Fuel economy'], $slug)
                ->assertJsonPath('image.content_type', 'image/svg+xml');
            // The artifact page shows the figure itself
            $this->get($version->url())->assertOk()->assertSee($this->figureTag($version), false)->assertDontSee('data-chart-frame', false);
        }
        Log::shouldHaveReceived('warning')->with('A plot description could not be compiled; the figure stays an image.', \Mockery::any())->times(count($invalid));
    }

    public function test_the_description_is_never_served_raw(): void
    {
        $description = $this->fixture('r-ggplot.phiz.json');
        $version = $this->publish($description);
        $key = $version->document->key;

        // The embed holds the compiled chart, not the description
        $embed = $this->embed($version)->json();
        $this->assertArrayNotHasKey('phiz', $embed['spec']);
        $this->assertArrayNotHasKey('aes', $embed['spec']);
        $this->assertStringNotContainsString('"inheritAes"', $this->embed($version)->getContent());

        // The fallback image is the SVG
        $image = $this->get("/api/embed/v1/artifacts/{$key}/versions/1/image")->assertOk()->streamedContent();
        $this->assertStringContainsString('<svg', $image);
        $this->assertStringNotContainsString('"phiz"', $image);
        // No route names the file
        $this->get("/d/{$key}/versions/1/figure.phiz.json")->assertNotFound();
        $this->get("/d/{$key}/figure.phiz.json")->assertNotFound();
    }

    public function test_a_spec_that_reaches_other_hosts_is_refused(): void
    {
        $spec = ['$schema' => 'https://vega.github.io/schema/vega-lite/v6.json', 'mark' => 'point', 'config' => ['background' => 'white'], 'usermeta' => ['phiz' => []]];
        foreach ([
            'data' => ['data' => ['url' => 'https://example.com/cars.csv']],
            'layer' => ['layer' => [['mark' => 'line', 'data' => ['url' => 'cars.json']]]],
            'datasets' => ['datasets' => ['cars' => ['url' => 'https://example.com/c.json']]],
            'href' => ['encoding' => ['href' => ['field' => 'link']]],
        ] as $case => $parts) {
            $this->assertNull(PlotDescription::forEmbed($spec + $parts), $case);
        }
        $this->assertSame(['$schema' => $spec['$schema'], 'mark' => 'point', 'data' => ['values' => [['url' => 'a']]]],
            PlotDescription::forEmbed($spec + ['data' => ['values' => [['url' => 'a']]]]));
    }

    public function test_the_artifact_page_draws_the_live_chart(): void
    {
        $version = $this->publish($this->fixture('r-ggplot.phiz.json'));

        $page = $this->get($version->url())->assertOk()->assertSee('data-chart-frame', false)->assertDontSee($this->figureTag($version), false);
        preg_match('/data-src="([^"]+)"/', $page->getContent(), $match);
        $chart = $this->get(html_entity_decode($match[1]))->assertOk()->assertSee('data-phiz=', false);
        $this->assertStringStartsWith('sandbox allow-scripts;', $chart->headers->get('Content-Security-Policy'));
        // The page keeps phiz's theme and its dark variant
        $this->assertStringContainsString('Fuel economy falls with weight', $chart->getContent());
        $this->assertStringContainsString('usermeta', $chart->getContent());
    }

    public function test_figures_without_a_description_are_unchanged(): void
    {
        $version = $this->publish(null);
        $this->embed($version)->assertJson(['kind' => 'image', 'spec' => null]);
        $this->get($version->url())->assertOk()->assertSee($this->figureTag($version), false)->assertDontSee('data-chart-frame', false);
    }

    public function test_a_description_needs_an_svg_or_png_figure(): void
    {
        $this->withToken($this->project->issuePullToken())->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('report.html', '<html>Report</html>'),
            'plot_description' => UploadedFile::fake()->createWithContent('figure.phiz.json', $this->fixture('r-ggplot.phiz.json')),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('plot_description');
        $this->assertSame(0, Document::count());

        $this->app['auth']->forgetGuards();
        $this->withToken($this->project->issuePullToken())->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('figure.svg', $this->fixture('r-ggplot.svg')),
            'plot_description' => UploadedFile::fake()->createWithContent('figure.phiz.json', $this->fixture('r-ggplot.phiz.json')),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->embed(Document::firstOrFail()->latestVersion())->assertJsonPath('kind', 'vega-lite');
    }

    public function test_merging_and_deleting_carry_the_description(): void
    {
        $source = $this->publish($this->fixture('r-ggplot.phiz.json'), 'source');
        $target = $this->publish(null, 'target');
        app(ArtifactPublisher::class)->merge($target->document, $source->document);
        $merged = $target->document->fresh()->latestVersion();
        $this->assertTrue(Storage::disk('local')->exists(PlotDescription::path($merged)));
        $this->embed($merged->load('document.project.user'))->assertJsonPath('kind', 'vega-lite');

        $path = PlotDescription::path($merged);
        $merged->forceDelete();
        $this->assertFalse(Storage::disk('local')->exists($path));
    }
}
