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
}
