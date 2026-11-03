<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Support\ArtifactPublisher;
use App\Support\ReportPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportReaderPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function artifact(Project $project, array $attributes = []): Document
    {
        return Document::create($attributes + [
            'project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'estimates', 'title' => 'Adjusted estimates',
            'content_type' => 'text/html', 'disk_path' => 'estimates.html', 'size_bytes' => 32, 'visibility' => 'public',
        ]);
    }

    private function report(Project $project, array $blocks, array $attributes = []): Report
    {
        $publisher = app(ReportPublisher::class);
        $report = $publisher->create($project, $attributes + [
            'title' => 'Study findings', 'description' => 'Results from the pilot study.', 'visibility' => 'unlisted', 'blocks' => $blocks,
        ]);
        $publisher->publish($report);

        return $report;
    }

    public function test_reader_renders_cover_navigation_and_safe_markdown(): void
    {
        $project = Project::factory()->create();
        $report = $this->report($project, [['id' => 'intro', 'type' => 'text',
            'text' => "## Main findings\n\nA **careful** interpretation.\n\n<script>window.reportXss=true</script>\n\n[unsafe](javascript:alert(1))\n\n- First result\n- Second result",
        ]]);
        $response = $this->get($report->shareUrl())->assertOk()
            ->assertSee('Study findings')->assertSee('Results from the pilot study.')
            ->assertSee('Contents')->assertSee('href="#section-1"', false)
            ->assertSee('<strong>careful</strong>', false)->assertSee('<ul>', false)
            ->assertDontSee('<script>window.reportXss', false)->assertDontSee('href="javascript:', false)
            ->assertSee('Published report · Version 1');
        $this->assertStringContainsString('Skip to report content', $response->getContent());
    }

    public function test_artifact_sections_embed_exact_version_and_offer_versioned_downloads(): void
    {
        $project = Project::factory()->create();
        $document = $this->artifact($project);
        $report = $this->report($project, [['id' => 'output', 'type' => 'artifact', 'document_id' => $document->id,
            'version_number' => 1, 'caption' => 'Estimates adjusted for age.', 'share_unlisted' => false,
        ]]);
        $this->get($report->shareUrl())->assertOk()->assertSee('Adjusted estimates')
            ->assertSee('/d/'.$document->key.'/versions/1?embed=1', false)
            ->assertSee(route('documents.version.download', ['key' => $document->key, 'number' => 1]), false)
            ->assertSee('Estimates adjusted for age.')->assertSee('title="Adjusted estimates"', false);
        $document->delete();
        $this->get($report->shareUrl())->assertOk()->assertSee('Artifact unavailable')
            ->assertDontSee('<iframe', false)->assertSee('does not substitute a newer version');
    }

    public function test_report_gate_and_artifact_gate_remain_separate_in_reader(): void
    {
        $project = Project::factory()->create();
        $document = $this->artifact($project, ['password_hash' => Hash::make('artifact-password')]);
        $report = $this->report($project, [['id' => 'output', 'type' => 'artifact', 'document_id' => $document->id,
            'version_number' => 1, 'caption' => 'Sensitive supporting output.', 'share_unlisted' => false,
        ]], ['password' => 'report-password']);
        $this->get($report->shareUrl())->assertOk()->assertViewIs('reports.password')
            ->assertDontSee('Study findings')->assertDontSee('Sensitive supporting output.');
        $this->post('/r/'.$report->key.'/unlock', ['password' => 'report-password', 'version' => 1])
            ->assertRedirect(route('reports.version', ['key' => $report->key, 'number' => 1]));
        $this->get($report->shareUrl())->assertOk()->assertSee('Open protected artifact')
            ->assertDontSee('<iframe', false)->assertSee('reload this report');
        $this->get($document->rawUrl())->assertForbidden();
    }

    public function test_owner_preview_distinguishes_saved_draft_from_published_report(): void
    {
        $project = Project::factory()->create();
        $report = app(ReportPublisher::class)->create($project, [
            'title' => 'Draft study', 'blocks' => [['id' => 'intro', 'type' => 'text', 'text' => '## Preliminary notes']],
        ]);
        $this->actingAs($project->user)->get('/reports/'.$report->id.'/preview')->assertOk()
            ->assertSee('Saved draft preview')->assertSee('Draft preview')->assertSee('Preliminary notes');
    }

    public function test_published_artifact_labels_stay_fixed_after_rename_and_republish(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $document = $this->artifact($project, ['kind' => 'image', 'content_type' => 'image/png', 'storage_disk' => 'local']);
        $report = $this->report($project, [['id' => 'output', 'type' => 'artifact', 'document_id' => $document->id,
            'version_number' => 1, 'caption' => 'Original figure.', 'share_unlisted' => false,
        ]]);
        app(ArtifactPublisher::class)->publish($project, ['slug' => $document->slug, 'title' => 'New downloadable model',
            'kind' => 'file', 'file' => UploadedFile::fake()->createWithContent('model.bin', 'new model output'),
        ]);
        $this->assertSame('New downloadable model', $document->fresh()->title);
        $this->get($report->shareUrl())->assertOk()->assertSee('Adjusted estimates')
            ->assertSee('>Figure</p>', false)->assertSee('title="Adjusted estimates"', false)
            ->assertDontSee('New downloadable model')->assertDontSee('>File</p>', false)
            ->assertSee('/d/'.$document->key.'/versions/1?embed=1', false);
    }
}
