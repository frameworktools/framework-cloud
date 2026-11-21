<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactDisplay;
use App\Support\ArtifactEmbed;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Display settings: what an artifact's embeds show, owned by Cloud. */
class ArtifactDisplaySettingsTest extends TestCase
{
    use RefreshDatabase;

    private const DAG = [
        'schema' => 'framework.artifact/v1', 'kind' => 'dag',
        'dagitty' => 'dag { age -> smoking -> cancer age -> cancer smoking [exposure] cancer [outcome] }',
        'code' => ['language' => 'r', 'text' => 'dag <- dagify(cancer ~ smoking + age, smoking ~ age)'],
        'analysis' => ['adjustment_sets' => [['age']], 'implications' => []],
    ];

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->user = User::factory()->create(['name' => 'Ada Example', 'email_verified_at' => now()]);
        $this->user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();
        $this->project = Project::factory()->for($this->user)->create(['slug' => 'smoking']);
    }

    private function publish(string $filename, string $content, array $input = []): DocumentVersion
    {
        return app(ArtifactPublisher::class)->publish($this->project, $input + [
            'slug' => 'artifact', 'visibility' => 'unlisted', 'description' => 'Age confounds smoking and cancer.',
            'file' => UploadedFile::fake()->createWithContent($filename, $content),
        ]);
    }

    private function embed(DocumentVersion $version): array
    {
        return $this->getJson('/api/embed/v1/artifacts/'.$version->document->key)->assertOk()->json();
    }

    public function test_a_dag_embeds_as_its_drawing_alone_until_its_sections_are_turned_on(): void
    {
        $version = $this->publish('dag.json', json_encode(self::DAG));

        $this->assertNull($version->document->display);
        $embed = $this->embed($version);
        $this->assertNull($embed['description']);
        $this->assertSame(['dag'], array_keys(array_intersect_key($embed['data'], array_flip(['dag', 'analysis', 'dagitty', 'code']))));
        $this->assertArrayNotHasKey('display', $embed['data']);
        $this->assertArrayNotHasKey('display', $embed);
        $this->assertNotNull($embed['title']);
    }

    public function test_a_package_sets_what_a_dag_embed_shows(): void
    {
        $version = $this->publish('dag.json', json_encode(self::DAG), ['display' => '{"analysis": true, "code": true, "description": true, "legend": false}']);

        $this->assertSame(['analysis' => true, 'code' => true, 'description' => true, 'legend' => false], $version->document->display);
        $embed = $this->embed($version);
        $this->assertSame('Age confounds smoking and cancer.', $embed['description']);
        $this->assertArrayHasKey('analysis', $embed['data']);
        $this->assertArrayHasKey('code', $embed['data']);
        $this->assertArrayNotHasKey('dagitty', $embed['data']);
        $this->assertSame(['legend' => false], $embed['data']['display']);
        // The reader page still shows everything
        $this->get($version->document->publicUrl())->assertOk()->assertSee('Adjustment sets')->assertSee('dagify');
    }

    public function test_a_later_publish_changes_only_the_settings_it_names(): void
    {
        $this->publish('dag.json', json_encode(self::DAG), ['display' => ['analysis' => true, 'code' => true]]);
        $version = $this->publish('dag.json', json_encode(self::DAG), ['display' => ['code' => false]]);
        $this->assertSame(['analysis' => true, 'code' => false], $version->document->fresh()->display);

        $version = $this->publish('dag.json', json_encode(self::DAG));
        $this->assertSame(['analysis' => true, 'code' => false], $version->document->fresh()->display);
    }

    public function test_unknown_settings_and_other_values_are_refused_and_nothing_is_stored(): void
    {
        foreach (['{"sparkles": false}', '{"analysis": "no"}', '[false]', 'not json'] as $display) {
            try {
                $this->publish('dag.json', json_encode(self::DAG), ['display' => $display]);
                $this->fail("Accepted {$display}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('display', $e->errors());
            }
        }
        $this->assertSame(0, $this->project->documents()->count());
        try {
            ArtifactDisplay::parse(['sparkles' => false]);
        } catch (ValidationException $e) {
            $this->assertSame('Unknown display setting "sparkles". Settings: '.implode(', ', ArtifactDisplay::keys()).'.', $e->errors()['display'][0]);
        }
    }

    public function test_the_settings_tab_lists_what_applies_and_saves_changes(): void
    {
        $version = $this->publish('dag.json', json_encode(self::DAG));
        $document = $version->document;

        $options = collect(ArtifactEmbed::displayOptions($document))->keyBy('key');
        $this->assertSame(['title', 'description', 'author', 'credit', 'legend', 'analysis', 'dagitty', 'code'], $options->keys()->all());
        $this->assertSame('R code', $options['code']['label']);

        // A DAG's sections start off; its title, legend, and link back start on
        $this->assertSame([true, false, true, true, true, false, false, false], $options->pluck('value')->all());

        $this->actingAs($this->user)->patch('/published/'.$document->id, ['display' => ['dagitty' => true, 'title' => true]])->assertRedirect();
        $this->assertSame(['dagitty' => true, 'title' => true], $document->fresh()->display);
        $this->actingAs($this->user)->patch('/published/'.$document->id, ['display' => ['dagitty' => false]])->assertRedirect();
        $this->assertSame(['dagitty' => false, 'title' => true], $document->fresh()->display);

        $this->actingAs($this->user)->get('/artifacts/'.$document->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('artifact.display.7.label', 'R code')->where('artifact.display.7.value', false));
    }

    public function test_a_dag_without_code_offers_no_code_setting(): void
    {
        $version = $this->publish('model.dagitty', 'x -> y');

        $this->assertNotContains('code', array_column(ArtifactEmbed::displayOptions($version->document), 'key'));
        $this->assertNotContains('analysis', array_column(ArtifactEmbed::displayOptions($version->document), 'key'));
    }

    public function test_model_test_table_and_snippet_settings(): void
    {
        $model = ['kind' => 'model', 'title' => 'T', 'description' => 'D', 'author' => ['name' => 'Ada'], 'image' => null, 'spec' => null,
            'data' => ['variables' => [['name' => 'x']], 'terms' => [['term' => 'x']], 'tables' => []]];
        $applied = ArtifactDisplay::apply($model, ['details' => false, 'variable_tips' => false, 'title' => false, 'author' => false]);
        $this->assertSame(['details' => false], $applied['data']['display']);
        $this->assertArrayNotHasKey('variables', $applied['data']);
        $this->assertArrayNotHasKey('terms', $applied['data']);
        $this->assertNull($applied['title']);
        $this->assertNull($applied['author']);
        $this->assertArrayNotHasKey('display', $applied);
        // The link back goes as a flag, since renderers would otherwise link the artifact's URL
        $this->assertSame(['credit' => false], ArtifactDisplay::apply($model, ['credit' => false])['display']);

        $table = ['kind' => 'table', 'title' => 'T', 'description' => null, 'author' => null, 'data' => ['tables' => [['columns' => [['name' => 'a', 'label' => 'A', 'summary' => ['count' => 3]]]]]]];
        $this->assertSame([['name' => 'a', 'label' => 'A']], ArtifactDisplay::apply($table, ['column_summaries' => false])['data']['tables'][0]['columns']);

        $snippet = ['kind' => 'code_snippet', 'title' => 'T', 'description' => null, 'author' => null, 'data' => ['display' => ['links' => [['rel' => 'notebook', 'url' => 'https://example.org']]]]];
        $this->assertSame(['links' => [], 'output' => false], ArtifactDisplay::apply($snippet, ['output' => false, 'links' => false])['data']['display']);

        $chart = ['kind' => 'vega-lite', 'title' => 'T', 'description' => null, 'author' => null, 'spec' => ['mark' => 'point'], 'image' => ['url' => 'https://example.org/i.svg'], 'data' => null];
        $static = ArtifactDisplay::apply($chart, ['interactive' => false]);
        $this->assertSame('image', $static['kind']);
        $this->assertNull($static['spec']);
        // Without a static image, a chart stays a chart
        $this->assertSame('vega-lite', ArtifactDisplay::apply(['image' => null] + $chart, ['interactive' => false])['kind']);
    }

    public function test_publishing_returns_the_link_to_embed(): void
    {
        $version = $this->publish('dag.json', json_encode(self::DAG), ['visibility' => 'public']);
        $document = $version->document;
        $this->assertStringContainsString('/go/', ArtifactEmbed::embedLink($document));

        $document->update(['visibility' => 'unlisted']);
        $this->assertSame($document->shareUrl(), ArtifactEmbed::embedLink($document->fresh()));

        $document->update(['visibility' => 'private']);
        $this->assertNull(ArtifactEmbed::embedLink($document->fresh()));

        // The project publish API answers with it
        $token = $this->project->createToken('publish', [Project::PULL_ABILITY])->plainTextToken;
        $this->withToken($token)->post('/api/v1/documents', ['slug' => 'api-dag', 'visibility' => 'unlisted',
            'file' => UploadedFile::fake()->createWithContent('model.dagitty', 'x -> y')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('embed_link', fn ($link) => is_string($link) && str_contains($link, '/d/'))
            ->assertJsonPath('artifact_url', fn ($link) => (bool) preg_match('#/artifacts/[0-9]+$#', $link));
    }
}
