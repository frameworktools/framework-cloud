<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    private function collection(Project $project, array $attributes = []): Collection
    {
        return Collection::create(array_merge([
            'project_id' => $project->id, 'title' => 'Review materials', 'slug' => 'review',
            'key' => Str::random(24), 'visibility' => 'private',
        ], $attributes));
    }

    private function artifact(Project $project, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'project_id' => $project->id, 'title' => 'Model estimates', 'slug' => Str::random(12),
            'key' => Str::random(24), 'content_type' => 'text/html', 'disk_path' => 'example.html',
            'size_bytes' => 32, 'visibility' => 'public',
        ], $attributes));
    }

    public function test_owner_can_create_and_manage_collection_while_other_users_cannot(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->actingAs($owner)->post("/projects/{$project->id}/collections", ['title' => 'Paper figures'])->assertRedirect();
        $collection = Collection::firstOrFail();
        $this->assertSame('private', $collection->visibility);
        $this->get("/collections/{$collection->id}/edit")->assertInertia(fn (Assert $page) => $page->component('Collections/Edit'));
        $this->actingAs(User::factory()->create())->get("/collections/{$collection->id}/edit")->assertNotFound();
        $this->delete("/collections/{$collection->id}")->assertNotFound();
        $this->post("/projects/{$project->id}/collections", ['title' => 'Other'])->assertNotFound();
    }

    public function test_artifacts_can_appear_in_multiple_collections_but_must_belong_to_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $collection = $this->collection($project);
        $second = $this->collection($project, ['slug' => 'second']);
        $document = $this->artifact($project);
        $this->actingAs($owner)->post("/collections/{$collection->id}/items", ['document_id' => $document->id])->assertRedirect();
        $this->post("/collections/{$second->id}/items", ['document_id' => $document->id])->assertRedirect();
        $this->assertSame(1, $collection->items()->count());
        $this->assertSame(1, $second->items()->count());
        $other = $this->artifact(Project::factory()->create());
        $this->post("/collections/{$collection->id}/items", ['document_id' => $other->id])->assertNotFound();
    }

    public function test_unlisted_artifacts_require_explicit_consent_and_are_not_exposed_in_public_collections(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $collection = $this->collection($project, ['visibility' => 'unlisted']);
        $document = $this->artifact($project, ['visibility' => 'unlisted']);
        $this->actingAs($owner)->post("/collections/{$collection->id}/items", ['document_id' => $document->id])->assertSessionHasErrors();
        $this->post("/collections/{$collection->id}/items", ['document_id' => $document->id, 'share_unlisted' => true])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->get($collection->shareUrl())->assertOk()->assertSee('Model estimates');
        $collection->update(['visibility' => 'public']);
        $this->get($collection->shareUrl())->assertOk()->assertDontSee('Model estimates');
    }

    public function test_pinning_requires_an_existing_version_and_reader_does_not_fall_forward_when_it_is_deleted(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $collection = $this->collection($project, ['visibility' => 'public']);
        $document = $this->artifact($project);
        foreach ([1, 2] as $number) {
            $document->versions()->create(['number' => $number, 'content_type' => 'text/html', 'disk_path' => "v{$number}.html", 'size_bytes' => 32]);
        }
        $item = $collection->items()->create(['document_id' => $document->id]);
        $this->actingAs($owner)->patch("/collections/{$collection->id}/items/{$item->id}", ['version_number' => 9, 'share_unlisted' => false])->assertSessionHasErrors();
        $this->patch("/collections/{$collection->id}/items/{$item->id}", ['version_number' => 1, 'share_unlisted' => false])->assertRedirect();
        $this->get($collection->shareUrl())->assertSee('/versions/1', false)->assertSee('Version 1');
        $document->versions()->where('number', 1)->delete();
        $this->get($collection->shareUrl())->assertDontSee('Model estimates');
    }

    public function test_password_gates_all_listing_and_changes_revoke_existing_access(): void
    {
        $project = Project::factory()->create();
        $collection = $this->collection($project, ['visibility' => 'unlisted', 'password_hash' => Hash::make('review-password')]);
        $document = $this->artifact($project);
        $collection->items()->create(['document_id' => $document->id]);
        $this->get($collection->shareUrl())->assertSee('Password-protected collection')->assertDontSee('Model estimates')->assertDontSee('Review materials');
        $this->post('/c/'.$collection->key.'/unlock', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/c/'.$collection->key.'/unlock', ['password' => 'review-password'])->assertRedirect($collection->shareUrl());
        $this->get($collection->shareUrl())->assertSee('Model estimates');
        $collection->update(['password_hash' => Hash::make('replacement-password')]);
        $this->get($collection->shareUrl())->assertSee('Password-protected collection')->assertDontSee('Model estimates');
        $collection->update(['visibility' => 'private']);
        $this->get($collection->shareUrl())->assertNotFound();
    }

    public function test_reordering_rejects_foreign_or_missing_entries(): void
    {
        $project = Project::factory()->create();
        $collection = $this->collection($project);
        $one = $collection->items()->create(['document_id' => $this->artifact($project)->id, 'position' => 0]);
        $two = $collection->items()->create(['document_id' => $this->artifact($project)->id, 'position' => 1]);
        $this->actingAs($project->user)->put("/collections/{$collection->id}/order", ['items' => [$one->id]])->assertSessionHasErrors();
        $this->put("/collections/{$collection->id}/order", ['items' => [$two->id, $one->id]])->assertRedirect();
        $this->assertSame([$two->id, $one->id], $collection->items()->pluck('id')->all());
    }

    public function test_private_collection_reader_is_owner_only(): void
    {
        $project = Project::factory()->create();
        $collection = $this->collection($project);
        $this->get($collection->shareUrl())->assertNotFound();
        $this->actingAs(User::factory()->create())->get($collection->shareUrl())->assertNotFound();
        $this->actingAs($project->user)->get($collection->shareUrl())->assertOk()->assertSee('Review materials');
    }

    public function test_unlocking_collection_does_not_unlock_artifact_or_its_raw_content(): void
    {
        $project = Project::factory()->create();
        $collection = $this->collection($project, ['visibility' => 'unlisted', 'password_hash' => Hash::make('collection-password')]);
        $document = $this->artifact($project, ['password_hash' => Hash::make('artifact-password')]);
        $collection->items()->create(['document_id' => $document->id]);
        $this->post('/c/'.$collection->key.'/unlock', ['password' => 'collection-password'])->assertRedirect();
        $this->get($collection->shareUrl())->assertOk()->assertSee('Model estimates');
        $this->get($document->publicUrl())->assertOk()->assertViewIs('documents.password');
        $this->get($document->rawUrl())->assertForbidden();
    }

    public function test_title_and_introduction_save_without_touching_sharing(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $collection = $this->collection($project, ['visibility' => 'unlisted', 'password_hash' => Hash::make('secret-pass')]);
        $this->actingAs($owner)->patch("/collections/{$collection->id}", ['title' => 'Renamed', 'description' => 'For reviewers'])->assertRedirect();
        $collection->refresh();
        $this->assertSame(['Renamed', 'For reviewers', 'unlisted'], [$collection->title, $collection->description, $collection->visibility]);
        $this->assertNotNull($collection->password_hash);
        $this->patch("/collections/{$collection->id}", ['title' => ''])->assertSessionHasErrors('title');
    }

    public function test_an_expired_link_stops_working_for_readers_but_not_the_owner(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $collection = $this->collection($project, ['visibility' => 'unlisted']);
        $collection->items()->create(['document_id' => $this->artifact($project)->id, 'position' => 0]);

        $this->actingAs($owner)->patch("/collections/{$collection->id}", ['visibility' => 'unlisted', 'expires_at' => now()->subDay()->toIso8601String()])
            ->assertSessionHasErrors('expires_at');
        $this->patch("/collections/{$collection->id}", ['visibility' => 'unlisted', 'expires_at' => now()->addDays(7)->toIso8601String()])->assertSessionHasNoErrors();
        $this->assertTrue($collection->refresh()->expires_at->isFuture());

        $this->travel(8)->days();
        $this->get($collection->shareUrl())->assertOk()->assertSee('Model estimates');
        $this->get("/collections/{$collection->id}/edit")->assertInertia(fn (Assert $page) => $page->where('collection.expired', true));
        // Saving sharing again keeps the past date without complaint
        $this->patch("/collections/{$collection->id}", ['visibility' => 'unlisted', 'expires_at' => $collection->expires_at->toIso8601String()])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get($collection->shareUrl())->assertStatus(410)->assertSee('Expired link')->assertDontSee('Model estimates');

        $this->actingAs($owner)->patch("/collections/{$collection->id}", ['visibility' => 'unlisted', 'expires_at' => null]);
        auth()->logout();
        $this->get($collection->shareUrl())->assertOk()->assertSee('Model estimates');
    }

    public function test_editor_lists_the_project_artifacts_with_their_kinds_and_versions(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $collection = $this->collection($project);
        $document = $this->artifact($project, ['kind' => 'model']);
        $collection->items()->create(['document_id' => $document->id, 'position' => 0, 'caption' => 'Main model']);
        $this->artifact(Project::factory()->create());

        $this->actingAs($owner)->get("/collections/{$collection->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->has('artifacts', 1)->where('artifacts.0.kind', 'model')->has('artifacts.0.versions')
            ->where('items.0.document_id', $document->id)->where('items.0.caption', 'Main model'));
    }
}
