<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\ReportPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Deleting forever, by any path, removes artifact files; the Trash empties itself after FW_TRASH_DAYS. */
class TrashPurgeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->project = Project::factory()->for($this->user)->create();
    }

    private function publish(string $slug = 'notebook'): DocumentVersion
    {
        return app(ArtifactPublisher::class)->publish($this->project, ['slug' => $slug,
            'file' => UploadedFile::fake()->createWithContent("{$slug}.csv", "a\n1\n")]);
    }

    public function test_deleting_forever_by_any_path_removes_the_files(): void
    {
        $version = $this->publish();
        $sidecar = $version->representationPath();
        Storage::disk('local')->assertExists([$version->disk_path, $sidecar]);

        $version->document->forceDelete();

        Storage::disk('local')->assertMissing([$version->disk_path, $sidecar]);
    }

    public function test_a_project_deleted_forever_takes_its_artifacts_files_and_tokens(): void
    {
        $kept = $this->publish('kept');
        $trashed = $this->publish('trashed');
        $trashed->document->delete();
        $this->project->issuePullToken();

        $this->project->forceDelete();

        Storage::disk('local')->assertMissing([$kept->disk_path, $trashed->disk_path]);
        $this->assertSame(0, Document::withTrashed()->count());
        $this->assertSame(0, $this->project->tokens()->count());
    }

    public function test_the_trash_empties_after_its_days(): void
    {
        $old = $this->publish('old');
        $recent = $this->publish('recent');
        $report = app(ReportPublisher::class)->create($this->project, ['title' => 'Old report']);
        $skill = Skill::create(['user_id' => $this->user->id, 'key' => 'old-skill', 'name' => 'Old', 'description' => 'x', 'visibility' => 'private']);
        $gone = Project::factory()->for($this->user)->create(['slug' => 'gone']);

        $this->travel(-40)->days();
        $old->document->delete();
        $report->delete();
        $skill->delete();
        $gone->delete();
        $this->travelBack();
        $recent->document->delete();

        $this->artisan('fw:purge-trash')->expectsOutputToContain('1 artifacts')->assertSuccessful();

        $this->assertNull(Document::withTrashed()->find($old->document_id));
        Storage::disk('local')->assertMissing($old->disk_path);
        $this->assertTrue(Document::withTrashed()->find($recent->document_id)->trashed());
        Storage::disk('local')->assertExists($recent->disk_path);
        $this->assertNull(Report::withTrashed()->find($report->id));
        $this->assertNull(Skill::withTrashed()->find($skill->id));
        $this->assertNull(Project::withTrashed()->find($gone->id));
    }

    public function test_zero_days_keeps_the_trash(): void
    {
        $version = $this->publish();
        $this->travel(-400)->days();
        $version->document->delete();
        $this->travelBack();

        config(['framework.trash_days' => 0]);
        $this->artisan('fw:purge-trash')->assertSuccessful();

        $this->assertTrue(Document::withTrashed()->find($version->document_id)->trashed());
    }

    public function test_opening_the_trash_purges_only_your_own(): void
    {
        $this->withoutVite();
        $mine = $this->publish('mine');
        $otherUser = User::factory()->create();
        $theirs = app(ArtifactPublisher::class)->publish(Project::factory()->for($otherUser)->create(), ['slug' => 'theirs',
            'file' => UploadedFile::fake()->createWithContent('theirs.csv', "a\n1\n")]);
        $this->travel(-40)->days();
        $mine->document->delete();
        $theirs->document->delete();
        $this->travelBack();

        $this->actingAs($this->user)->get('/trash')->assertOk();

        $this->assertNull(Document::withTrashed()->find($mine->document_id));
        $this->assertNotNull(Document::withTrashed()->find($theirs->document_id));
    }
}
