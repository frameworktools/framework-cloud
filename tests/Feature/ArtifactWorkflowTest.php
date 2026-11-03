<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArtifactWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_publishing_and_a_pinned_review_collection_keep_the_original_results(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($owner)->create();
        $this->actingAs($owner)->post("/projects/{$project->id}/artifacts", [
            'file' => UploadedFile::fake()->createWithContent('estimates.csv', "term,estimate\ntreatment,1.25\n"),
            'slug' => 'estimates', 'title' => 'Adjusted estimates', 'visibility' => 'unlisted',
        ])->assertRedirect('/published');
        $artifact = Document::firstOrFail();
        $this->get('/published')->assertInertia(fn (Assert $page) => $page
            ->component('Published')->where('projects.0.id', $project->id)
            ->where('documents.0.id', $artifact->id)->where('documents.0.kind', 'table'));
        $this->get("/projects/{$project->slug}/published")->assertInertia(fn (Assert $page) => $page
            ->where('canPublish', true)->where('project.documents.0.kind', 'table'));

        $this->post("/projects/{$project->id}/collections", ['title' => 'Manuscript review'])->assertRedirect();
        $collection = Collection::firstOrFail();
        $this->post("/collections/{$collection->id}/items", ['document_id' => $artifact->id, 'share_unlisted' => true])->assertRedirect();
        $entry = $collection->items()->firstOrFail();
        $this->patch("/collections/{$collection->id}/items/{$entry->id}", ['version_number' => 1, 'share_unlisted' => true])->assertRedirect();
        $this->patch("/collections/{$collection->id}", ['title' => $collection->title, 'visibility' => 'unlisted'])->assertRedirect();
        $this->post("/projects/{$project->id}/artifacts", [
            'file' => UploadedFile::fake()->createWithContent('estimates.csv', "term,estimate\ntreatment,2.50\n"),
            'slug' => 'estimates',
        ])->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->get($collection->shareUrl())->assertOk()->assertSee('/versions/1')->assertSee('Adjusted estimates');
        $this->get($artifact->findVersion(1)->url())->assertOk()->assertSee('1.25')->assertDontSee('2.50');
        $this->get($artifact->publicUrl())->assertOk()->assertSee('2.50');
        $this->actingAs($owner)->delete("/published/{$artifact->id}/versions/1")->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->get($collection->shareUrl())->assertOk()->assertDontSee('Adjusted estimates');
    }

    public function test_owner_can_preview_unlisted_artifacts_in_a_private_collection(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($owner)->create();
        $this->actingAs($owner)->post("/projects/{$project->id}/artifacts", [
            'file' => UploadedFile::fake()->createWithContent('findings.txt', 'Preliminary findings'),
            'title' => 'Preliminary findings',
        ])->assertRedirect();
        $this->post("/projects/{$project->id}/collections", ['title' => 'Draft review'])->assertRedirect();
        $collection = Collection::firstOrFail();
        $this->post("/collections/{$collection->id}/items", ['document_id' => Document::firstOrFail()->id])->assertRedirect();
        $this->get($collection->shareUrl())->assertOk()->assertSee('Preliminary findings');
        $this->app['auth']->forgetGuards();
        $this->get($collection->shareUrl())->assertNotFound();
    }

    public function test_legacy_public_disk_content_cannot_be_password_protected_without_migration(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['framework.documents_disk' => 'public', 'framework.documents_base_url' => null]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($owner)->create();
        $artifact = Document::create([
            'project_id' => $project->id, 'key' => Document::newKey(), 'slug' => 'legacy',
            'disk_path' => 'documents/legacy.html', 'content_type' => 'text/html', 'size_bytes' => 10,
        ]);
        Storage::disk('public')->put($artifact->disk_path, '<p>legacy</p>');
        $this->actingAs($owner)->patch("/published/{$artifact->id}", ['password' => 'new-password'])
            ->assertSessionHasErrors('password');
        $this->assertNull($artifact->fresh()->password_hash);
        $this->post("/projects/{$project->id}/artifacts", [
            'file' => UploadedFile::fake()->createWithContent('legacy.html', '<p>new content</p>'),
            'slug' => 'legacy', 'password' => 'new-password',
        ])->assertSessionHasErrors('password');
        $this->assertNull($artifact->fresh()->password_hash);
        $this->assertSame(0, $artifact->versions()->count());
        Storage::disk('public')->assertExists($artifact->disk_path);
        $this->assertSame([], Storage::disk('local')->allFiles('artifacts'));
    }
}
