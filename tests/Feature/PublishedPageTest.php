<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublishedPageTest extends TestCase
{
    use RefreshDatabase;

    private function publish(Project $project, string $name = 'analysis.html'): Document
    {
        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent($name, '<html>x</html>'),
        ], ['Accept' => 'application/json'])->assertCreated();

        // The token guard remembers the project; sign in as a user again below
        $this->app['auth']->forgetGuards();

        return Document::latest('id')->first();
    }

    public function test_lists_only_your_published_documents(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $mine = Project::factory()->for($user)->create(['name' => 'Mine']);
        $theirs = Project::factory()->create();

        $document = $this->publish($mine);
        $this->publish($theirs, 'other.html');

        $this->actingAs($user)->get('/published')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Published')
                ->has('documents', 1)
                ->where('documents.0.id', $document->id)
                ->where('documents.0.project.name', 'Mine')
                ->where('documents.0.url', $document->publicUrl()));
    }

    public function test_taking_a_document_down_deletes_its_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->publish(Project::factory()->for($user)->create());

        Storage::disk('local')->assertExists($document->disk_path);

        $this->actingAs($user)->delete("/published/{$document->id}")->assertRedirect();

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->disk_path);
        $this->get('/d/'.$document->key)->assertNotFound();
    }

    public function test_you_cannot_take_down_someone_elses_document(): void
    {
        Storage::fake('local');
        $document = $this->publish(Project::factory()->create());

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->delete("/published/{$document->id}")
            ->assertNotFound();

        $this->assertModelExists($document);
    }

    public function test_an_artifact_has_its_own_page_outside_its_project(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($user)->create(['name' => 'Pilot']);
        $document = $this->publish($project);
        $other = $this->publish($project, 'second.html');
        $collection = \App\Models\Collection::create(['project_id' => $project->id, 'title' => 'Review set', 'slug' => 'review', 'key' => 'k'.str_repeat('a', 23), 'visibility' => 'private']);
        $collection->items()->create(['document_id' => $document->id, 'position' => 0]);

        $this->actingAs($user)->get("/artifacts/{$document->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Artifacts/Show')
            ->where('artifact.id', $document->id)->where('project.name', 'Pilot')
            ->has('versions', 1)->where('usedIn.0.title', 'Review set')
            ->has('mergeSources', 1)->where('mergeSources.0.id', $other->id)
            ->missing('artifact.password_hash'));

        $this->actingAs(User::factory()->create())->get("/artifacts/{$document->id}")->assertNotFound();
    }

    public function test_taking_down_from_the_artifact_page_returns_to_the_list(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $document = $this->publish(Project::factory()->for($user)->create());

        $this->actingAs($user)->delete("/published/{$document->id}?then=list")->assertRedirect(route('published'));
        $this->assertModelMissing($document);
    }
}
