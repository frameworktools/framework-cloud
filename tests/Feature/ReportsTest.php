<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\ReportPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        return Project::factory()->for(User::factory()->create(['email_verified_at' => now()]))->create();
    }

    private function artifact(Project $project, array $data = []): Document
    {
        return app(ArtifactPublisher::class)->publish($project, $data + ['file' => UploadedFile::fake()->createWithContent('figure.html', '<html>FIGURE</html>')])->document;
    }

    private function block(Document $document, ?int $version = null, bool $share = true): array
    {
        return ['id' => 'figure', 'type' => 'artifact', 'document_id' => $document->id, 'version_number' => $version, 'caption' => 'Figure caption', 'share_unlisted' => $share];
    }

    public function test_a_report_and_a_collection_need_no_project_and_can_use_any_of_the_accounts_artifacts(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project);
        $user = $project->user;

        $this->actingAs($user)->post('/reports', ['title' => 'Loose notes'])->assertRedirect();
        $report = Report::where('title', 'Loose notes')->sole();
        $this->assertTrue($report->project->is_home);

        // The editor offers every artifact in the account, and the draft can use one from another project
        $this->get('/reports/'.$report->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('project.is_home', true)->where('artifacts.0.id', $document->id));
        $this->patch('/reports/'.$report->id, ['title' => 'Loose notes', 'blocks' => [$this->block($document)]])->assertSessionHasNoErrors();

        $this->get('/reports')->assertInertia(fn (Assert $page) => $page->where('reports.0.project.is_home', true));

        $this->post('/collections', ['title' => 'Loose set'])->assertRedirect();
        $this->assertTrue(\App\Models\Collection::where('title', 'Loose set')->sole()->project->is_home);
    }

    public function test_owner_creates_edits_and_previews_a_draft_without_quarto(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project);
        $this->actingAs($project->user)->post('/projects/'.$project->id.'/reports', ['title' => 'Research findings'])->assertRedirect();
        $report = Report::first();
        $this->assertSame('private', $report->visibility);
        $this->patch('/reports/'.$report->id, ['title' => 'Updated findings', 'description' => 'Summary', 'blocks' => [
            ['id' => 'intro', 'type' => 'text', 'text' => "# Findings\n\n<script>alert(1)</script> [unsafe](javascript:alert(2))"],
            $this->block($document),
        ]])->assertRedirect();
        $report->refresh();
        $this->assertCount(2, $report->draft_blocks);
        $this->get('/reports/'.$report->id.'/edit')->assertInertia(fn (Assert $page) => $page->component('Reports/Edit')->where('report.title', 'Updated findings')->has('artifacts', 1));
        $this->get('/reports/'.$report->id.'/preview')->assertOk()->assertSee('Updated findings')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('href="javascript:', false);
        $this->assertSame(0, $report->versions()->count());
        $this->app['auth']->forgetGuards();
        $this->get('/reports/'.$report->id.'/preview')->assertRedirect('/login');
        $this->get($report->shareUrl())->assertNotFound();
    }

    public function test_publish_snapshots_metadata_and_pins_latest_artifact_version(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project);
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'First title', 'description' => 'First description', 'visibility' => 'unlisted', 'blocks' => [$this->block($document)]]);
        $first = $service->publish($report);
        $this->assertSame(1, $first->blocks[0]['version_number']);
        $this->artifact($project);
        $service->saveDraft($report, ['title' => 'Draft title', 'description' => 'Draft description']);
        $this->get($report->shareUrl())->assertOk()->assertSee('First title')->assertSee('First description')->assertDontSee('Draft title');
        $this->assertSame(1, $first->fresh()->blocks[0]['version_number']);
        $second = $service->publish($report);
        $this->assertSame(2, $second->number);
        $this->assertSame(2, $second->blocks[0]['version_number']);
        $this->get($first->url())->assertViewHas('blocks', fn ($blocks) => $blocks[0]['version_number'] === 1);
        $this->get($second->url())->assertSee('Draft title');
    }

    public function test_artifact_title_and_kind_are_snapshotted_and_ignore_client_supplied_labels(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project, ['title' => 'Original figure', 'kind' => 'html']);
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Stable labels', 'visibility' => 'unlisted',
            'blocks' => [$this->block($document) + ['artifact_title' => 'Forged title', 'artifact_kind' => 'model']]]);
        $version = $service->publish($report);
        $this->assertSame('Original figure', $version->blocks[0]['artifact_title']);
        $this->assertSame('html', $version->blocks[0]['artifact_kind']);
        $this->assertArrayNotHasKey('artifact_title', $report->fresh()->draft_blocks[0]);
        $this->artifact($project, ['title' => 'Revised model', 'kind' => 'model']);
        $this->get($version->url())->assertOk()->assertViewHas('blocks', fn ($blocks) => $blocks[0]['artifact_title'] === 'Original figure' && $blocks[0]['artifact_kind'] === 'html');
    }

    public function test_missing_or_deleted_pinned_versions_are_unavailable_without_latest_fallback(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project);
        $this->artifact($project);
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Pinned', 'visibility' => 'unlisted', 'blocks' => [$this->block($document, 1)]]);
        $version = $service->publish($report);
        $document->versions()->where('number', 1)->delete();
        $this->get($version->url())->assertOk()->assertViewHas('blocks', fn ($blocks) => ! $blocks[0]['available'] && $blocks[0]['url'] === null);
        $document->delete();
        $this->get($version->url())->assertOk()->assertViewHas('blocks', fn ($blocks) => $blocks[0]['document'] === null);
    }

    public function test_ownership_and_cross_project_block_references_are_enforced(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $other = $this->project();
        $document = $this->artifact($other);
        $report = app(ReportPublisher::class)->create($project, ['title' => 'Owned']);
        $this->actingAs($project->user)->patch('/reports/'.$report->id, ['blocks' => [$this->block($document)]])->assertSessionHasErrors('blocks.0.document_id');
        $this->actingAs($other->user)->patch('/reports/'.$report->id, ['title' => 'Stolen'])->assertNotFound();
        $this->post('/reports/'.$report->id.'/publish')->assertNotFound();
        $this->get('/reports/'.$report->id.'/preview')->assertNotFound();
        $this->delete('/reports/'.$report->id)->assertNotFound();
        $this->assertSame('Owned', $report->fresh()->title);
    }

    public function test_public_reports_require_public_artifacts_and_unlisted_reports_require_consent(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project);
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Sharing', 'blocks' => [$this->block($document, share: false)]]);
        $this->actingAs($project->user)->post('/reports/'.$report->id.'/publish', ['visibility' => 'public'])->assertSessionHasErrors('blocks.0.document_id');
        $this->assertSame('private', $report->fresh()->visibility);
        $this->post('/reports/'.$report->id.'/publish', ['visibility' => 'unlisted'])->assertSessionHasErrors('blocks.0.share_unlisted');
        $this->assertSame(0, $report->versions()->count());
        $this->post('/reports/'.$report->id.'/publish', ['visibility' => 'unlisted', 'blocks' => [$this->block($document)]])->assertRedirect();
        $this->assertSame(1, $report->versions()->count());
        $document->update(['visibility' => 'public']);
        $this->post('/reports/'.$report->id.'/publish', ['visibility' => 'public'])->assertRedirect();
        $document->update(['visibility' => 'unlisted']);
        $this->app['auth']->forgetGuards();
        $this->get($report->shareUrl())->assertViewHas('blocks', fn ($blocks) => ! $blocks[0]['available']);
    }

    public function test_report_password_does_not_unlock_independently_protected_artifacts(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project, ['password' => 'artifact-secret']);
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Password report', 'visibility' => 'unlisted', 'password' => 'report-secret', 'blocks' => [$this->block($document)]]);
        $first = $service->publish($report);
        $service->publish($report, ['title' => 'Second version']);
        $this->get($first->url())->assertOk()->assertViewIs('reports.password')->assertSee('name="version" value="1"', false);
        $this->post('/r/'.$report->key.'/unlock', ['password' => 'incorrect'])->assertSessionHasErrors('password');
        $this->post('/r/'.$report->key.'/unlock', ['password' => 'report-secret', 'version' => 1])->assertRedirect($first->url());
        $this->get($first->url())->assertOk()->assertViewHas('blocks', fn ($blocks) => $blocks[0]['available'] && ! $blocks[0]['can_read'])->assertDontSee('<iframe', false);
        $this->get($document->rawUrl())->assertForbidden();
        $service->saveDraft($report, ['password' => 'new-report-secret']);
        $this->get($first->url())->assertViewIs('reports.password');
        $this->get($document->rawUrl())->assertForbidden();
    }

    public function test_private_published_reports_and_soft_deleted_projects_are_hidden(): void
    {
        $project = $this->project();
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Private report', 'blocks' => [['id' => 'text', 'type' => 'text', 'text' => 'Private results']]]);
        $version = $service->publish($report);
        $this->get($version->url())->assertNotFound();
        $this->post('/r/'.$report->key.'/unlock', ['password' => 'anything'])->assertNotFound();
        $this->actingAs($project->user)->get($version->url())->assertOk();
        $project->delete();
        $this->get($version->url())->assertNotFound();
        $this->get('/reports/'.$report->id.'/edit')->assertNotFound();
    }

    public function test_publish_failure_rolls_back_submitted_draft_and_version_number(): void
    {
        $project = $this->project();
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Original']);
        Event::listen('eloquent.creating: App\Models\ReportVersion', function () {
            throw new \RuntimeException('Simulated failure.');
        });
        try {
            $service->publish($report, ['title' => 'Changed', 'visibility' => 'public']);
            $this->fail('Expected version creation to fail.');
        } catch (\RuntimeException) {
            $report->refresh();
            $this->assertSame('Original', $report->title);
            $this->assertSame('private', $report->visibility);
            $this->assertSame(1, $report->next_version_number);
            $this->assertSame(0, $report->versions()->count());
        }
    }

    public function test_blank_password_replacements_preserve_protection_and_removal_is_explicit(): void
    {
        $project = $this->project();
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Protected', 'password' => 'report-secret']);
        $hash = $report->password_hash;
        $this->actingAs($project->user);
        foreach (['        ', ' ', '', null] as $blank) {
            $this->patch('/reports/'.$report->id, ['password' => $blank])->assertRedirect();
            $this->assertSame($hash, $report->fresh()->password_hash);
        }
        $this->patch('/reports/'.$report->id, ['remove_password' => true])->assertRedirect();
        $this->assertNull($report->fresh()->password_hash);
    }

    public function test_long_research_titles_receive_a_slug_within_the_storage_bound(): void
    {
        $project = $this->project();
        $title = str_repeat('a', 200);
        $this->actingAs($project->user)->post('/projects/'.$project->id.'/reports', ['title' => $title])->assertRedirect();
        $report = Report::first();
        $this->assertSame(120, strlen($report->slug));
        $this->patch('/reports/'.$report->id, ['title' => $title, 'slug' => $report->slug])->assertRedirect();
        $this->post('/reports/'.$report->id.'/publish')->assertRedirect();
        $this->assertSame($title, $report->versions()->first()->title);
    }

    public function test_duplicate_block_identifiers_and_invalid_reference_versions_are_rejected(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $document = $this->artifact($project);
        $service = app(ReportPublisher::class);
        $report = $service->create($project, ['title' => 'Invalid blocks']);
        $this->actingAs($project->user)->patch('/reports/'.$report->id, ['blocks' => [$this->block($document), $this->block($document)]])->assertSessionHasErrors('blocks.1.id');
        $this->patch('/reports/'.$report->id, ['blocks' => [$this->block($document, 999)]])->assertSessionHasErrors('blocks.0.version_number');
        $this->patch('/reports/'.$report->id, ['blocks' => array_fill(0, 101, $this->block($document))])->assertSessionHasErrors('blocks');
    }
}
