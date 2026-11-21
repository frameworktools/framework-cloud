<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\Dags\ArtifactDag;
use App\Support\Dags\DagittyParser;
use App\Support\Share;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

/** The dag kind: a causal diagram with the DAGitty code that defines it. */
class DagArtifactTest extends TestCase
{
    use RefreshDatabase;

    private const DAGITTY = "dag {\nage [pos=\"1,0\"]\ncancer [outcome,pos=\"2,1\"]\nsmoking [exposure,pos=\"0,1\"]\nage -> cancer\nage -> smoking\nsmoking -> cancer\n}";

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();
        $this->project = Project::factory()->for($user)->create(['slug' => 'smoking']);
    }

    private function dag(array $changes = []): array
    {
        return array_replace([
            'schema' => 'framework.artifact/v1',
            'kind' => 'dag',
            'source' => ['language' => 'r'],
            'dagitty' => self::DAGITTY,
            'labels' => ['smoking' => 'Smoking status'],
            'code' => ['language' => 'r', 'text' => "dag <- dagify(cancer ~ smoking + age, smoking ~ age,\n  exposure = \"smoking\", outcome = \"cancer\")"],
            'analysis' => ['effect' => 'total', 'adjustment_sets' => [['age']], 'implications' => []],
        ], $changes);
    }

    private function publish(string $filename, string $content, array $input = []): DocumentVersion
    {
        return app(ArtifactPublisher::class)->publish($this->project, $input + [
            'slug' => 'smoking-dag',
            'file' => UploadedFile::fake()->createWithContent($filename, $content),
        ]);
    }

    public function test_dagitty_text_parses_chains_groups_roles_and_positions(): void
    {
        $graph = DagittyParser::parse("dag {\n  x [exposure, pos=\"0,1\"]\n  \"Income level\" [latent]\n  a -> {x y} <- b\n  x <- z -> y\n  x <-> y [beta=\".3\"]\n  bb=\"0,0,1,1\"\n}");

        $this->assertSame(['x', 'Income level', 'a', 'y', 'b', 'z'], array_column($graph['nodes'], 'name'));
        $this->assertSame(['exposure'], $graph['nodes'][0]['roles']);
        $this->assertSame([0.0, 1.0], $graph['nodes'][0]['pos']);
        $this->assertSame(['latent'], $graph['nodes'][1]['roles']);
        $this->assertSame([
            ['a', 'x', '->'], ['a', 'y', '->'], ['b', 'x', '->'], ['b', 'y', '->'], ['z', 'x', '->'], ['z', 'y', '->'], ['x', 'y', '<->'],
        ], array_map(fn ($e) => [$e['from'], $e['to'], $e['type']], $graph['edges']));
        // A graph without the dag header reads too
        $this->assertCount(2, DagittyParser::parse('x -> y')['nodes']);
    }

    public function test_a_broken_graph_is_refused_with_a_reason(): void
    {
        $cases = [
            'dag { a -> b -> c -> a }' => 'The graph has a cycle: a -> b -> c -> a. A DAG has none.',
            'pdag { a -> b }' => 'This is a pdag graph. Framework draws dag graphs only.',
            'dag { a -- b }' => 'DAGitty code, line 1: undirected edges (--) belong to other graph types. A DAG uses -> and <->.',
            "dag {\n a [pos=\"one\"] }" => 'DAGitty code, line 2: pos for a must be two numbers, such as pos="1,0".',
            'dag { a -> b' => "DAGitty code: the graph's braces are never closed.",
            'dag { a -> a }' => 'DAGitty code, line 1: a points to itself.',
            'dag { }' => 'The DAGitty code names no variables.',
        ];
        foreach ($cases as $text => $message) {
            try {
                ArtifactDag::fromDagitty($text);
                $this->fail("Accepted: {$text}");
            } catch (InvalidArgumentException $e) {
                $this->assertSame($message, $e->getMessage(), $text);
            }
        }

        $this->expectException(ValidationException::class);
        $this->publish('broken.dagitty', 'dag { a -> b -> a }');
    }

    public function test_the_layout_keeps_boxes_apart_and_inside_the_drawing(): void
    {
        foreach ([self::DAGITTY, "a -> b -> c -> d\na -> d\nu -> {b c d}\nb <-> d\ne"] as $text) {
            $dag = ArtifactDag::fromDagitty($text)['dag'];
            $nodes = $dag['nodes'];
            foreach ($nodes as $i => $a) {
                $this->assertGreaterThanOrEqual(0, $a['x']);
                $this->assertLessThanOrEqual($dag['width'], $a['x'] + $a['width']);
                $this->assertLessThanOrEqual($dag['height'], $a['y'] + $a['height']);
                $this->assertSame(0, $a['width'] % 2, 'Labels center on whole pixels');
                foreach (array_slice($nodes, $i + 1) as $b) {
                    $apart = $b['x'] >= $a['x'] + $a['width'] || $a['x'] >= $b['x'] + $b['width']
                        || $b['y'] >= $a['y'] + $a['height'] || $a['y'] >= $b['y'] + $b['height'];
                    $this->assertTrue($apart, "{$a['name']} overlaps {$b['name']}");
                }
            }
            foreach ($dag['edges'] as $edge) {
                $this->assertMatchesRegularExpression('/^M -?[0-9.]+ -?[0-9.]+ (L|Q -?[0-9.]+ -?[0-9.]+) -?[0-9.]+ -?[0-9.]+$/', $edge['path']);
                $this->assertCount($edge['type'] === '<->' ? 2 : 1, $edge['heads']);
            }
        }
        // The same graph lays out the same way every time
        $this->assertSame(ArtifactDag::fromDagitty(self::DAGITTY), ArtifactDag::fromDagitty(self::DAGITTY));
    }

    public function test_a_dagitty_file_publishes_as_a_dag_and_reads_as_its_drawing(): void
    {
        $version = $this->publish('smoking.dagitty', self::DAGITTY, ['visibility' => 'public']);

        $this->assertSame('dag', $version->document->kind);
        $this->get($version->document->publicUrl())->assertOk()
            ->assertSee('class="dag-graph', false)
            ->assertSee('dag-node dag-node--exposure', false)
            ->assertSee('Download smoking.dagitty')
            ->assertSee('>Copy<', false)
            ->assertSee('Causal diagram of 3 variables and 3 arrows. Exposure: smoking. Outcome: cancer.');
    }

    public function test_dag_json_carries_labels_code_and_analysis(): void
    {
        $version = $this->publish('dag.json', json_encode($this->dag()), ['visibility' => 'public']);

        $this->assertSame('dag', $version->document->kind);
        $page = $this->get($version->document->publicUrl())->assertOk();
        $page->assertSee('Smoking status')->assertSee('Download JSON')
            ->assertSee('Adjustment sets')
            ->assertSee('Minimal sufficient adjustment sets for the total effect of', false)
            ->assertSee('<span class="t-fn">dagify</span>', false)
            ->assertSee('<span class="t-kw">dag</span>', false);
    }

    public function test_analysis_keeps_only_names_in_the_graph(): void
    {
        $dag = ArtifactDag::fromJson(json_encode($this->dag(['analysis' => [
            'adjustment_sets' => [['age'], ['height'], []],
            'implications' => [['x' => 'age', 'y' => 'cancer', 'given' => ['smoking']], ['x' => 'age', 'y' => 'nobody', 'given' => []]],
        ]])));

        $this->assertSame([['age'], []], $dag['analysis']['adjustment_sets']);
        $this->assertSame([['x' => 'age', 'y' => 'cancer', 'given' => ['smoking']]], $dag['analysis']['implications']);
        $this->assertSame(['smoking'], $dag['analysis']['exposures']);
        $this->assertSame('total', $dag['analysis']['effect']);
    }

    public function test_the_embed_document_carries_the_drawing_and_the_code(): void
    {
        $version = $this->publish('dag.json', json_encode($this->dag()), ['visibility' => 'unlisted']);

        $response = $this->getJson('/api/embed/v1/artifacts/'.$version->document->key)->assertOk();
        $response->assertJsonPath('kind', 'dag')
            ->assertJsonPath('alt', 'Causal diagram of 3 variables and 3 arrows. Exposure: Smoking status. Outcome: cancer.')
            ->assertJsonPath('data.dag.nodes.2.label', 'Smoking status')
            ->assertJsonPath('data.dagitty.text', self::DAGITTY)
            ->assertJsonPath('data.code.language', 'r')
            ->assertJsonPath('data.analysis.adjustment_sets', [['age']]);
        $this->assertIsInt($response->json('data.dag.width'));
        $this->assertStringStartsWith('M ', $response->json('data.dag.edges.0.path'));
    }

    public function test_a_dag_sent_as_a_plain_file_is_still_a_dag(): void
    {
        $this->assertSame('dag', $this->publish('smoking.dagitty', self::DAGITTY, ['kind' => 'file'])->document->kind);
        $this->assertSame('dag', $this->publish('dag.json', json_encode($this->dag()), ['kind' => 'text', 'slug' => 'json-dag'])->document->kind);
    }

    public function test_kind_dag_on_a_json_file_that_is_not_one_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->publish('dag.json', json_encode(['schema' => 'framework.artifact/v1', 'kind' => 'dag']), ['kind' => 'dag']);
    }

    public function test_a_public_artifacts_short_link_embeds_and_an_unlisted_ones_does_not(): void
    {
        $version = $this->publish('smoking.dagitty', self::DAGITTY, ['visibility' => 'public']);
        $code = Share::for($version->document)['code'];
        $short = rtrim(config('app.url'), '/').'/go/'.$code;

        $this->getJson('/api/embed/v1/resolve?url='.urlencode($short))->assertOk()->assertJsonPath('kind', 'dag')
            ->assertJsonPath('key', $version->document->key);

        $version->document->update(['visibility' => 'unlisted']);
        $this->getJson('/api/embed/v1/resolve?url='.urlencode($short))->assertNotFound();
    }
}
