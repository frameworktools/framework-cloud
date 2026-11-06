<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\ArtifactPublisher;
use App\Support\PublishingQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Deleted artifacts and versions keep their files in the Trash until restored or deleted forever. */
class ArtifactTrashTest extends TestCase
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
        $this->project = Project::factory()->for($this->user)->create(['slug' => 'penguin-study']);
    }

    private function publish(string $body): DocumentVersion
    {
        return app(ArtifactPublisher::class)->publish($this->project, ['slug' => 'notebook', 'visibility' => 'unlisted',
            'file' => UploadedFile::fake()->createWithContent('notebook.html', $body)]);
    }

    public function test_a_deleted_artifact_keeps_its_files_and_comes_back(): void
    {
        $version = $this->publish('<html>One</html>');
        $document = $version->document;

        $this->actingAs($this->user)->delete("/published/{$document->id}")->assertRedirect();

        $this->assertSoftDeleted($document);
        Storage::disk('local')->assertExists($version->disk_path);
        $this->get(route('documents.show', ['key' => $document->key]))->assertNotFound();
        $this->get('/trash')->assertInertia(fn (AssertableInertia $page) => $page->where('items.0.type', 'artifact'));
        $this->assertGreaterThan(0, PublishingQuota::used($this->user));

        $this->post("/trash/artifact/{$document->id}/restore")->assertRedirect();
        $this->get(route('documents.show', ['key' => $document->key]))->assertOk();
    }

    public function test_deleting_forever_removes_the_files(): void
    {
        $version = $this->publish('<html>One</html>');
        $document = $version->document;
        $this->actingAs($this->user)->delete("/published/{$document->id}");

        $this->delete("/trash/artifact/{$document->id}")->assertRedirect();

        $this->assertNull(Document::withTrashed()->find($document->id));
        Storage::disk('local')->assertMissing($version->disk_path);
        $this->assertSame(0, PublishingQuota::used($this->user));
    }

    public function test_a_deleted_version_waits_in_the_trash(): void
    {
        $first = $this->publish('<html>One</html>');
        $second = $this->publish('<html>Two</html>');
        $document = $first->document;

        $this->actingAs($this->user)->delete("/published/{$document->id}/versions/2")->assertRedirect();
        $this->assertSame($first->disk_path, $document->fresh()->disk_path);
        Storage::disk('local')->assertExists($second->disk_path);

        $this->post("/trash/version/{$second->id}/restore")->assertRedirect();
        $this->assertSame($second->disk_path, $document->fresh()->disk_path);

        // Numbers are never reused, even past a deleted version
        $this->delete("/published/{$document->id}/versions/2");
        $this->assertSame(3, $this->publish('<html>Three</html>')->number);
    }

    public function test_publishing_a_deleted_artifacts_name_brings_it_back(): void
    {
        $document = $this->publish('<html>One</html>')->document;
        $this->actingAs($this->user)->delete("/published/{$document->id}");

        $again = $this->publish('<html>Two</html>');

        $this->assertSame($document->id, $again->document_id);
        $this->assertSame(2, $again->number);
        $this->assertFalse($again->document->trashed());
    }

    public function test_another_account_cannot_restore_or_delete_forever(): void
    {
        $document = $this->publish('<html>One</html>')->document;
        $this->actingAs($this->user)->delete("/published/{$document->id}");
        $other = User::factory()->create(['is_admin' => true]);

        $this->actingAs($other)->post("/trash/artifact/{$document->id}/restore")->assertNotFound();
        $this->delete("/trash/artifact/{$document->id}")->assertNotFound();
        $this->assertTrue(Document::withTrashed()->find($document->id)->trashed());
    }

    public function test_deleting_the_account_removes_files_in_the_trash(): void
    {
        $version = $this->publish('<html>One</html>');
        $this->actingAs($this->user)->delete("/published/{$version->document_id}");

        app(AccountDeletion::class)->delete($this->user);

        Storage::disk('local')->assertMissing($version->disk_path);
    }
}
