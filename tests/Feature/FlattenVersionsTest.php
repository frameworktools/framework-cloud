<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Support\ArtifactFlattener;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Flattening keeps an artifact's newest version as version 1, deletes the rest, and sends old links to the latest. */
class FlattenVersionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();
        $this->project = Project::factory()->for($this->user)->create(['slug' => 'mobility']);
    }

    /** One file per kind, each publish different from the last. */
    private function file(string $kind, int $n): UploadedFile
    {
        return match ($kind) {
            'html' => UploadedFile::fake()->createWithContent('report.html', "<html><body><p>Version {$n}</p></body></html>"),
            'table' => UploadedFile::fake()->createWithContent('table.csv', "a,b\n{$n},2\n"),
            'dag' => UploadedFile::fake()->createWithContent('dag.dagitty', "dag { x -> y z{$n} -> y }"),
            'tree' => UploadedFile::fake()->createWithContent('structure.json', json_encode([
                'schema' => 'framework.artifact/v1', 'kind' => 'tree', 'root' => 'p', 'nodes' => [['path' => "file-{$n}.R", 'type' => 'file']],
            ])),
        };
    }

    private function publish(string $kind, int $times, array $input = []): Document
    {
        $version = null;
        for ($n = 1; $n <= $times; $n++) {
            $version = app(ArtifactPublisher::class)->publish($this->project, $input + ['slug' => "artifact-{$kind}", 'file' => $this->file($kind, $n), 'visibility' => 'public']);
        }

        return $version->document->fresh();
    }

    public function test_every_kind_flattens_to_its_newest_version_as_version_1(): void
    {
        foreach (['html', 'table', 'dag', 'tree'] as $kind) {
            $document = $this->publish($kind, 3);
            $versions = $document->versions()->get();
            $newest = $versions->first();
            $oldPaths = $versions->slice(1)->pluck('disk_path');

            $result = app(ArtifactFlattener::class)->flatten($document);

            $document->refresh();
            $this->assertSame(2, $result['removed'], $kind);
            $this->assertSame([1], $document->versions()->withTrashed()->pluck('number')->all(), $kind);
            $this->assertSame($newest->id, $document->latestVersion()->id, $kind);
            $this->assertNotNull($document->flattened_at);
            Storage::disk('local')->assertExists($newest->disk_path);
            foreach ($oldPaths as $path) {
                Storage::disk('local')->assertMissing($path);
            }
            $this->get($document->publicUrl())->assertOk();
        }
    }

    public function test_versions_in_the_trash_go_too_and_numbers_are_never_reused(): void
    {
        $document = $this->publish('html', 3);
        $document->findVersion(2)->delete();

        app(ArtifactFlattener::class)->flatten($document);
        $this->assertSame(1, DocumentVersion::withTrashed()->where('document_id', $document->id)->count());

        // The next publish continues after the highest old number
        $next = app(ArtifactPublisher::class)->publish($this->project, ['slug' => 'artifact-html', 'file' => $this->file('html', 9)]);
        $this->assertSame(4, $next->number);
        $this->assertSame([4, 1], $document->versions()->pluck('number')->all());
    }

    public function test_old_version_links_go_to_the_latest(): void
    {
        $document = $this->publish('html', 3);
        app(ArtifactFlattener::class)->flatten($document);
        $key = $document->key;

        $this->get("/d/{$key}/versions/2")->assertStatus(301)->assertRedirect($document->publicUrl());
        $this->get("/d/{$key}/versions/3/raw")->assertStatus(301)->assertRedirect(route('documents.raw', ['key' => $key]));
        $this->get("/d/{$key}/versions/3/download")->assertStatus(301)->assertRedirect(route('documents.download', ['key' => $key]));
        $this->get('/@ada/mobility/artifact-html/versions/2')->assertStatus(301)->assertRedirect($document->readableUrl());
        // Version 1 is the flattened version itself
        $this->get("/d/{$key}/versions/1")->assertOk();
        $this->assertSame($document->latestVersion()->id, $document->findVersion(1)->id);
        // A number that never existed is still missing
        $this->get("/d/{$key}/versions/9")->assertNotFound();
    }

    public function test_old_version_embeds_show_the_latest(): void
    {
        $document = $this->publish('tree', 3);
        app(ArtifactFlattener::class)->flatten($document);

        $this->getJson("/api/embed/v1/artifacts/{$document->key}/versions/2")->assertOk()
            ->assertJsonPath('kind', 'tree')->assertJsonPath('version', 1)
            ->assertJsonPath('data.tree.children.0.name', 'file-3.R');
        $this->getJson('/api/embed/v1/resolve?url='.urlencode(url("/d/{$document->key}/versions/3")))->assertOk()
            ->assertJsonPath('version', 1);
    }

    public function test_pins_in_collections_and_reports_name_version_1(): void
    {
        $document = $this->publish('html', 3);
        $collection = Collection::create(['project_id' => $this->project->id, 'title' => 'Review', 'slug' => 'review', 'key' => Str::random(24), 'visibility' => 'private']);
        $pinned = $collection->items()->create(['document_id' => $document->id, 'version_number' => 2]);
        $other = Collection::create(['project_id' => $this->project->id, 'title' => 'Latest', 'slug' => 'latest', 'key' => Str::random(24), 'visibility' => 'private']);
        $latest = $other->items()->create(['document_id' => $document->id]);
        $block = fn (?int $n) => ['type' => 'artifact', 'document_id' => $document->id, 'version_number' => $n, 'caption' => null, 'share_unlisted' => false];
        $report = Report::create(['project_id' => $this->project->id, 'key' => Str::random(24), 'slug' => 'findings', 'title' => 'Findings', 'visibility' => 'private',
            'draft_blocks' => [['type' => 'text', 'text' => 'Hi'], $block(3), $block(null)]]);
        $published = $report->versions()->create(['number' => 1, 'title' => 'Findings', 'blocks' => [$block(2)]]);

        app(ArtifactFlattener::class)->flatten($document);

        $this->assertSame(1, $pinned->fresh()->version_number);
        $this->assertNull($latest->fresh()->version_number);
        $this->assertSame([null, 1, null], array_map(fn ($b) => $b['version_number'] ?? null, $report->fresh()->draft_blocks));
        $this->assertSame(1, $published->fresh()->blocks[0]['version_number']);
    }

    public function test_the_api_and_the_artifact_page_flatten(): void
    {
        $document = $this->publish('dag', 2);
        $token = $this->user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/artifacts/artifact-dag/flatten')->assertOk()
            ->assertJson(['artifact' => 'artifact-dag', 'version' => 1, 'removed' => 1]);

        // A project token flattens its own project's artifacts
        $mine = $this->publish('tree', 2);
        $this->withToken($this->project->issuePullToken())->postJson('/api/v1/artifacts/artifact-tree/flatten')->assertOk()->assertJsonPath('removed', 1);
        $elsewhere = Project::factory()->for($this->user)->create(['slug' => 'elsewhere']);
        $this->withToken($elsewhere->issuePullToken())->postJson('/api/v1/artifacts/artifact-html/flatten')->assertNotFound();

        $other = $this->publish('table', 2);
        $this->actingAs($this->user)->post("/published/{$other->id}/flatten")->assertRedirect()->assertSessionHas('status', 'versions-flattened');
        $this->assertSame([1], $other->versions()->pluck('number')->all());
    }

    public function test_another_accounts_artifact_cannot_be_flattened(): void
    {
        $document = $this->publish('html', 2);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->withToken($stranger->createToken('t')->plainTextToken)->postJson('/api/v1/artifacts/artifact-html/flatten')->assertNotFound();
        $this->actingAs($stranger)->post("/published/{$document->id}/flatten")->assertNotFound();
        $this->assertCount(2, $document->versions()->get());
    }

    public function test_an_external_publication_is_refused(): void
    {
        $document = $this->publish('html', 2);
        $document->forceFill(['driver' => 'github_pages'])->save();

        $this->expectException(ValidationException::class);
        app(ArtifactFlattener::class)->flatten($document);
    }
}
