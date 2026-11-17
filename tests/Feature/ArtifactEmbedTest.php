<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactEmbed;
use App\Support\ArtifactPublisher;
use App\Support\EmbedAlt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The embed API: framework.embed/v1 documents, figures, oEmbed, and discovery. */
class ArtifactEmbedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->user = User::factory()->create(['name' => 'Ada Example', 'email_verified_at' => now()]);
        $this->user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();
        $this->project = Project::factory()->for($this->user)->create(['name' => 'Admissions', 'slug' => 'admissions']);
    }

    private function publish(string $fixture, array $input = [], ?string $name = null): DocumentVersion
    {
        $path = base_path("tests/fixtures/artifacts/{$fixture}");
        $file = is_file($path) ? file_get_contents($path) : $fixture;
        $name ??= is_file($path) ? $fixture : 'page.html';

        return app(ArtifactPublisher::class)->publish($this->project, $input + [
            'file' => UploadedFile::fake()->createWithContent($name, $file),
        ]);
    }

    private function embed(DocumentVersion $version): TestResponse
    {
        return $this->getJson('/api/embed/v1/artifacts/'.$version->document->key.'/versions/'.$version->number);
    }

    private function assertPublic(TestResponse $response, int $maxAge): void
    {
        $response->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeaderMissing('Access-Control-Allow-Credentials')
            ->assertHeaderMissing('Set-Cookie');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age='.$maxAge, $response->headers->get('Cache-Control'));
    }

    public function test_a_model_embeds_its_normalized_representation(): void
    {
        $version = $this->publish('r-glm-logit.json', ['title' => 'Transmission model', 'description' => 'Manual transmission by weight and horsepower.']);
        $key = $version->document->key;

        $response = $this->embed($version)->assertOk();
        $this->assertPublic($response, 3600);
        $response->assertJson([
            'schema' => 'framework.embed/v1', 'key' => $key, 'version' => 1, 'latest_version' => 1,
            'kind' => 'model', 'title' => 'Transmission model', 'description' => 'Manual transmission by weight and horsepower.',
            'alt' => 'Logistic regression of am with 3 terms and odds ratios.',
            'url' => "http://localhost/d/{$key}/versions/1",
            'embed_url' => "http://localhost/api/embed/v1/artifacts/{$key}/versions/1",
            'author' => ['name' => 'Ada Example', 'url' => 'http://localhost/@ada'],
            'provider' => ['name' => 'Framework', 'url' => 'http://localhost'],
            'attribution' => ['text' => 'Framework', 'url' => "http://localhost/d/{$key}/versions/1"],
            'spec' => null, 'image' => null,
        ])->assertJsonPath('data.schema', 'framework.artifact/v1')
            ->assertJsonPath('data.kind', 'model')
            ->assertJsonPath('data.model.exponentiate', 'Odds ratio')
            ->assertJsonPath('data.tables.0.row_count', 3)
            ->assertJsonPath('data.tables.0.truncated', false)
            ->assertJsonMissingPath('data.type');
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $response->json('published_at'));
    }

    public function test_tables_and_tests_embed_with_text_descriptions(): void
    {
        $table = $this->embed($this->publish('r-tibble.json'))->assertOk()->assertJsonPath('kind', 'table')->assertJsonPath('data.kind', 'table');
        $this->assertStringStartsWith('Table with 60 rows and ', $table->json('alt'));
        $this->assertStringContainsString(': species, island, bill_length_mm', $table->json('alt'));

        $this->embed($this->publish('r-ttest-welch.json'))->assertOk()
            ->assertJsonPath('kind', 'test')
            ->assertJsonPath('data.test.method', 'Welch Two Sample t-test')
            ->assertJsonPath('alt', 'Welch Two Sample t-test: t = 1.92, p = 0.0606.');
    }

    public function test_tables_embed_their_first_rows_and_their_full_sizes(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = [$i, 'participant '.$i, $i * 1.5, $i % 2 === 0];
        }
        $artifact = ['schema' => 'framework.artifact/v1', 'kind' => 'table', 'source' => ['language' => 'r', 'class' => ['data.frame']], 'metrics' => [],
            'tables' => [['title' => 'Participants', 'role' => 'data', 'row_count' => 1000, 'rows' => $rows, 'columns' => [
                ['name' => 'id', 'type' => 'number', 'summary' => ['count' => 1000, 'missing' => 0, 'min' => 1, 'max' => 1000]],
                ['name' => 'name', 'type' => 'string'], ['name' => 'score', 'type' => 'number'], ['name' => 'even', 'type' => 'boolean'],
            ]]]];
        $response = $this->embed($this->publish(json_encode($artifact), [], 'participants.json'))->assertOk()
            ->assertJsonPath('kind', 'table')
            ->assertJsonCount(25, 'data.tables.0.rows')
            ->assertJsonPath('data.tables.0.rows.24', [25, 'participant 25', 37.5, false])
            ->assertJsonPath('data.tables.0.row_count', 1000)
            ->assertJsonPath('data.tables.0.column_count', 4)
            ->assertJsonPath('data.tables.0.columns.0.summary.max', 1000)
            ->assertJsonPath('data.tables.0.truncated', true)
            ->assertJsonPath('data.truncated', true);
        $this->assertStringStartsWith('Table with 1,000 rows and 4 columns', $response->json('alt'));
        $this->assertLessThan(20_000, strlen($response->getContent()));

        // A short table is whole
        $this->embed($this->publish('r-glm-logit.json'))->assertJsonCount(3, 'data.tables.0.rows')->assertJsonPath('data.truncated', false);
    }

    public function test_a_figure_embeds_as_an_image_served_without_scripts(): void
    {
        $version = $this->publish('r-ggplot.svg', ['title' => 'Fuel economy']);
        $key = $version->document->key;
        $this->embed($version)->assertOk()->assertJson([
            'kind' => 'image', 'alt' => 'Fuel economy', 'data' => null, 'spec' => null,
            'image' => ['url' => "http://localhost/api/embed/v1/artifacts/{$key}/versions/1/image", 'content_type' => 'image/svg+xml', 'width' => 672, 'height' => 480],
        ]);

        $image = $this->get("/api/embed/v1/artifacts/{$key}/versions/1/image")->assertOk();
        $this->assertSame('image/svg+xml', $image->headers->get('Content-Type'));
        $image->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        $this->assertPublic($image, 3600);
        $this->assertStringContainsString('<svg', $image->streamedContent());
    }

    public function test_only_image_artifacts_have_an_image(): void
    {
        $version = $this->publish('r-glm-logit.json');
        $this->get('/api/embed/v1/artifacts/'.$version->document->key.'/versions/1/image')->assertNotFound();
    }

    public function test_the_figure_redirects_to_the_content_worker_when_it_serves_files(): void
    {
        config(['framework.content_url' => 'https://content.example.test', 'framework.content_signing_key' => 'test-key', 'framework.content_worker_disk' => 'local']);
        $version = $this->publish('r-ggplot.svg');

        $this->travelTo('2026-11-16T12:59:59Z');
        $response = $this->get('/api/embed/v1/artifacts/'.$version->document->key.'/versions/1/image')->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertPublic($response, 300);

        // A signed embed-mode link: the Worker sends public, cross-origin image headers and no download name
        $this->assertTrue((bool) preg_match('#^https://content\.example\.test/c/([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)/(.+)$#', $location, $m));
        $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', $m[1], 'test-key', true)), '+/', '-_'), '='), $m[2]);
        $claims = json_decode(base64_decode(strtr($m[1], '-_', '+/')), true);
        $this->assertSame(['k' => $version->disk_path, 't' => 'image/svg+xml', 'c' => 'embed', 'e' => strtotime('2026-11-16T14:00:00Z')], $claims);
        $this->assertSame($version->contentFilename(), rawurldecode($m[3]));
        // Every redirect in the hour names the same link, and it outlives the redirect's cache
        $this->travelTo('2026-11-16T12:00:00Z');
        $this->assertSame($location, $this->get('/api/embed/v1/artifacts/'.$version->document->key.'/versions/1/image')->headers->get('Location'));
        $this->assertGreaterThan(300, $claims['e'] - strtotime('2026-11-16T12:59:59Z'));

        // The embed document keeps the stable app URL
        $this->embed($version)->assertJsonPath('image.url', 'http://localhost/api/embed/v1/artifacts/'.$version->document->key.'/versions/1/image');
    }

    public function test_a_vega_lite_chart_embeds_its_specification(): void
    {
        $this->embed($this->publish('python-altair.vl.json', ['title' => 'Tips by bill']))->assertOk()
            ->assertJsonPath('kind', 'vega-lite')
            ->assertJsonPath('data', null)
            ->assertJsonPath('spec.mark.type', 'point')
            ->assertJsonPath('alt', 'Tips by bill');
    }

    public function test_charts_that_load_from_other_hosts_embed_as_page_cards(): void
    {
        $chart = fn (array $parts) => json_encode($parts + ['$schema' => 'https://vega.github.io/schema/vega-lite/v5.json', 'mark' => 'point',
            'encoding' => ['x' => ['field' => 'a', 'type' => 'quantitative']]]);
        $inline = ['data' => ['values' => [['a' => 1, 'url' => 'https://example.com/a.png', 'href' => 'https://example.com']]]];
        $this->embed($this->publish($chart($inline), ['slug' => 'inline'], 'inline.vl.json'))->assertJsonPath('kind', 'vega-lite');
        $this->embed($this->publish($chart(['datasets' => ['points' => [['a' => 1]]], 'data' => ['name' => 'points']]), ['slug' => 'named'], 'named.vl.json'))
            ->assertJsonPath('kind', 'vega-lite');

        foreach ([
            'remote' => ['data' => ['url' => 'https://example.com/data.csv']],
            'layered' => ['data' => ['values' => [['a' => 1]]], 'layer' => [['mark' => 'line', 'data' => ['url' => 'data/cars.json']]]],
            'lookup' => ['data' => ['values' => [['a' => 1]]], 'transform' => [['lookup' => 'a', 'from' => ['data' => ['url' => 'https://example.com/b.json'], 'key' => 'a']]]],
            'dataset' => ['datasets' => ['points' => ['url' => 'https://example.com/c.json']], 'data' => ['name' => 'points']],
            'image' => $inline + ['mark' => 'image', 'encoding' => ['x' => ['field' => 'a', 'type' => 'quantitative'], 'url' => ['field' => 'url', 'type' => 'nominal']]],
            'link' => $inline + ['encoding' => ['x' => ['field' => 'a', 'type' => 'quantitative'], 'href' => ['field' => 'href']]],
        ] as $slug => $parts) {
            $this->embed($this->publish($chart($parts), ['slug' => $slug], "{$slug}.vl.json"))->assertOk()
                ->assertJson(['kind' => 'page', 'spec' => null, 'data' => null], $slug);
        }
    }

    public function test_html_embeds_as_a_page_card(): void
    {
        $this->embed($this->publish('<html><body>Report</body></html>', ['title' => 'Annual report']))->assertOk()
            ->assertJson(['kind' => 'page', 'title' => 'Annual report', 'alt' => 'Annual report', 'data' => null, 'spec' => null, 'image' => null]);
    }

    public function test_versions_are_pinned_and_the_newest_moves(): void
    {
        $this->publish('r-ttest-welch.json', ['slug' => 'ttest']);
        $second = $this->publish('r-ttest-welch.json', ['slug' => 'ttest']);
        $key = $second->document->key;

        $latest = $this->getJson("/api/embed/v1/artifacts/{$key}")->assertOk()
            ->assertJson(['version' => 2, 'latest_version' => 2, 'embed_url' => "http://localhost/api/embed/v1/artifacts/{$key}/versions/2"]);
        $this->assertPublic($latest, 300);
        $first = $this->getJson("/api/embed/v1/artifacts/{$key}/versions/1")->assertOk()
            ->assertJson(['version' => 1, 'latest_version' => 2, 'url' => "http://localhost/d/{$key}/versions/1"]);
        $this->assertPublic($first, 3600);
        $this->getJson("/api/embed/v1/artifacts/{$key}/versions/3")->assertNotFound();
    }

    public function test_resolve_accepts_every_artifact_link_form(): void
    {
        $version = $this->publish('r-glm-logit.json', ['slug' => 'logit', 'visibility' => 'public']);
        $key = $version->document->key;
        $this->publish('r-glm-logit.json', ['slug' => 'logit']);

        foreach ([
            "http://localhost/d/{$key}" => [2, 300],
            "http://localhost/d/{$key}/versions/1" => [1, 3600],
            'http://localhost/@ada/admissions/logit' => [2, 300],
            'http://localhost/@ada/admissions/logit/versions/1' => [1, 3600],
            'http://LOCALHOST/@ada/admissions/logit/?embed=1#top' => [2, 300],
        ] as $url => [$number, $maxAge]) {
            $response = $this->getJson('/api/embed/v1/resolve?url='.urlencode($url))->assertOk()->assertJsonPath('version', $number);
            $this->assertPublic($response, $maxAge);
        }
        // A public artifact links to its readable page
        $this->getJson('/api/embed/v1/resolve?url='.urlencode("http://localhost/d/{$key}/versions/1"))
            ->assertJsonPath('url', 'http://localhost/@ada/admissions/logit/versions/1');

        foreach (["https://elsewhere.example/d/{$key}", "http://localhost:8080/d/{$key}", "ftp://localhost/d/{$key}", "http://localhost/c/{$key}",
            "http://localhost/d/{$key}/versions/9", 'http://localhost/@ada/admissions/missing', 'not a url', ''] as $url) {
            $this->getJson('/api/embed/v1/resolve?url='.urlencode($url))->assertNotFound();
        }
        $this->getJson('/api/embed/v1/resolve')->assertNotFound();
    }

    public function test_unlisted_artifacts_embed_but_have_no_readable_link(): void
    {
        $version = $this->publish('r-glm-logit.json', ['slug' => 'logit', 'visibility' => 'unlisted']);
        $this->embed($version)->assertOk();
        $this->getJson('/api/embed/v1/resolve?url='.urlencode('http://localhost/d/'.$version->document->key))->assertOk();
        $this->getJson('/api/embed/v1/resolve?url='.urlencode('http://localhost/@ada/admissions/logit'))->assertNotFound();
    }

    public function test_artifacts_that_are_not_embeddable_all_read_as_missing(): void
    {
        $private = $this->publish('r-ggplot.svg', ['slug' => 'private', 'visibility' => 'private']);
        $password = $this->publish('r-ggplot.svg', ['slug' => 'locked', 'visibility' => 'public', 'password' => 'correct horse']);
        $deleted = $this->publish('r-ggplot.svg', ['slug' => 'deleted', 'visibility' => 'public']);
        $deletedKey = $deleted->document->key;
        $deleted->document->delete();
        $external = Document::create(['project_id' => $this->project->id, 'key' => 'externalkey123', 'slug' => 'external', 'title' => 'External',
            'visibility' => 'public', 'driver' => 'github_pages', 'external_url' => 'https://example.github.io/x/',
            'disk_path' => 'external', 'content_type' => 'text/html', 'size_bytes' => 0]);
        $keys = [$private->document->key, $password->document->key, $deletedKey, $external->key, 'nosuchkey12345'];

        $bodies = [];
        foreach ($keys as $key) {
            foreach (["/api/embed/v1/artifacts/{$key}", "/api/embed/v1/artifacts/{$key}/versions/1", "/api/embed/v1/artifacts/{$key}/versions/1/image",
                '/api/embed/v1/resolve?url='.urlencode("http://localhost/d/{$key}"),
                '/api/oembed?url='.urlencode("http://localhost/d/{$key}/versions/1")] as $path) {
                $response = $this->get($path)->assertNotFound()->assertHeader('Access-Control-Allow-Origin', '*')->assertHeaderMissing('Set-Cookie');
                $bodies[] = $response->getContent();
            }
        }
        // Signed in as the owner changes nothing: the API has no session
        $this->actingAs($this->user)->get('/api/embed/v1/artifacts/'.$private->document->key)->assertNotFound();
        $this->assertCount(1, array_unique($bodies));
        $this->assertSame(['message' => 'Not found.'], json_decode($bodies[0], true));
    }

    public function test_oembed_answers_photo_for_figures_and_link_for_the_rest(): void
    {
        $figure = $this->publish('r-ggplot.svg', ['slug' => 'figure', 'title' => 'Fuel economy', 'visibility' => 'public']);
        $key = $figure->document->key;

        $photo = $this->getJson('/api/oembed?format=json&url='.urlencode('http://localhost/@ada/admissions/figure/versions/1'))->assertOk();
        $this->assertPublic($photo, 3600);
        $photo->assertExactJson([
            'version' => '1.0', 'type' => 'photo', 'title' => 'Fuel economy',
            'url' => "http://localhost/api/embed/v1/artifacts/{$key}/versions/1/image", 'width' => 672, 'height' => 480,
            'author_name' => 'Ada Example', 'author_url' => 'http://localhost/@ada',
            'provider_name' => 'Framework', 'provider_url' => 'http://localhost', 'cache_age' => 3600,
            'framework_embed_url' => "http://localhost/api/embed/v1/artifacts/{$key}/versions/1",
        ]);
        $this->getJson('/api/oembed?url='.urlencode("http://localhost/d/{$key}").'&maxwidth=336&maxheight=1000')
            ->assertOk()->assertJson(['type' => 'photo', 'width' => 336, 'height' => 240, 'cache_age' => 300]);

        $this->user->forceFill(['profile_public' => false])->save();
        $model = $this->publish('r-glm-logit.json', ['slug' => 'model', 'title' => 'Transmission model']);
        $link = $this->getJson('/api/oembed?url='.urlencode('http://localhost/d/'.$model->document->key))->assertOk();
        $link->assertExactJson([
            'version' => '1.0', 'type' => 'link', 'title' => 'Transmission model',
            'provider_name' => 'Framework', 'provider_url' => 'http://localhost', 'cache_age' => 300,
            'framework_embed_url' => 'http://localhost/api/embed/v1/artifacts/'.$model->document->key.'/versions/1',
        ]);
        $this->getJson('/api/embed/v1/artifacts/'.$model->document->key)->assertOk()->assertJsonPath('author', null);

        $this->getJson('/api/oembed?url='.urlencode("https://framework.example/d/{$key}"))->assertNotFound();
        $this->get('/api/oembed?format=xml&url='.urlencode("http://localhost/d/{$key}"))->assertStatus(501);
    }

    public function test_discovery_is_stateless(): void
    {
        $response = $this->get('/.well-known/framework.json')->assertOk()->assertExactJson(['framework' => ['embed' => [
            'version' => 1,
            'resolve' => 'http://localhost/api/embed/v1/resolve',
            'artifact' => 'http://localhost/api/embed/v1/artifacts/{key}',
            'oembed' => 'http://localhost/api/oembed',
        ]]]);
        $this->assertPublic($response, 300);
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_every_embed_route_shares_one_rate_limit(): void
    {
        foreach (['embed.resolve', 'embed.artifact', 'embed.version', 'embed.image', 'embed.oembed', 'embed.discovery'] as $name) {
            $this->assertContains('throttle:embed', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
        $this->get('/.well-known/framework.json')->assertHeader('X-RateLimit-Limit', '300');
    }

    public function test_embeddable_pages_carry_discovery_links(): void
    {
        $version = $this->publish('r-glm-logit.json', ['slug' => 'logit', 'title' => 'Transmission model']);
        $key = $version->document->key;
        $this->get("/d/{$key}")->assertOk()
            ->assertSee('<link rel="alternate" type="application/json+oembed" href="'.e('http://localhost/api/oembed?url='.urlencode("http://localhost/d/{$key}/versions/1").'&format=json').'" title="Transmission model">', false)
            ->assertSee('<link rel="alternate" type="application/vnd.framework.embed+json" href="http://localhost/api/embed/v1/artifacts/'.$key.'/versions/1">', false);

        $private = $this->publish('r-glm-logit.json', ['slug' => 'private', 'visibility' => 'private']);
        $this->actingAs($this->user)->get('/d/'.$private->document->key)->assertOk()->assertDontSee('application/json+oembed', false);
        $locked = $this->publish('r-glm-logit.json', ['slug' => 'locked', 'password' => 'correct horse']);
        $this->actingAs($this->user)->get('/d/'.$locked->document->key)->assertOk()->assertDontSee('application/vnd.framework.embed+json', false);
    }

    public function test_alt_text_numbers_and_svg_sizes(): void
    {
        $this->assertSame('0.000123', EmbedAlt::number(0.000123456));
        $this->assertSame('1235', EmbedAlt::number(1234.5));
        $this->assertSame('-8.08', EmbedAlt::number(-8.08347));
        $this->assertSame(['width' => 300, 'height' => 150], ArtifactEmbed::svgSize('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150">'));
        $this->assertSame(['width' => 96, 'height' => 48], ArtifactEmbed::svgSize('<svg width="1in" viewBox="0 0 2 1">'));
        $this->assertSame(['width' => null, 'height' => null], ArtifactEmbed::svgSize('<svg width="100%" height="100%">'));
    }

    /**
     * The embed documents the framework-embed renderers test against, in
     * tests/fixtures/embeds. Written only with FW_WRITE_EMBED_FIXTURES=1, with
     * hosts, keys, and dates fixed so the files stay stable.
     */
    public function test_write_embed_fixtures(): void
    {
        if (! env('FW_WRITE_EMBED_FIXTURES')) {
            $this->markTestSkipped('Set FW_WRITE_EMBED_FIXTURES=1 to write tests/fixtures/embeds.');
        }
        config(['app.url' => 'https://framework.pub']);
        URL::forceRootUrl('https://framework.pub');
        URL::forceScheme('https');
        $this->travelTo('2026-11-16T12:00:00Z');
        $fixtures = [
            'r-glm-logit' => ['r-glm-logit.json', 'Transmission model', 'Manual transmission by weight and horsepower, from mtcars.'],
            'r-tibble' => ['r-tibble.json', 'Penguin measurements', null],
            'r-ttest-welch' => ['r-ttest-welch.json', 'Tooth growth by supplement', null],
            'r-ggplot' => ['r-ggplot.svg', 'Fuel economy by weight', null],
            'python-altair' => ['python-altair.vl.json', 'Tips by bill', null],
            'page' => ['<!doctype html><html><head><title>Annual report</title></head><body><h1>Annual report</h1></body></html>', 'Annual report', 'Rendered Quarto report.'],
        ];
        $directory = base_path('tests/fixtures/embeds');
        is_dir($directory) || mkdir($directory, 0755, true);
        foreach ($fixtures as $name => [$source, $title, $description]) {
            $version = $this->publish($source, ['slug' => $name, 'title' => $title, 'description' => $description, 'visibility' => 'public']);
            $key = substr(str_pad(preg_replace('/[^a-z0-9]/', '', $name), 16, '0'), 0, 16);
            $version->document->forceFill(['key' => $key])->save();
            $version = $version->fresh(['document.project.user']);
            $embed = ArtifactEmbed::document($version);
            file_put_contents("{$directory}/{$name}.json", json_encode($embed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }
        $this->assertFileExists("{$directory}/r-glm-logit.json");
    }
}
