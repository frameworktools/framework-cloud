<?php

namespace Tests\Feature\Api\V1;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->project = Project::factory()->create();
    }

    private function publish(array $fields): string
    {
        $file = UploadedFile::fake()->createWithContent('analysis.html', '<html><body>Results</body></html>');
        $this->withToken($this->project->issuePullToken())
            ->post('/api/v1/documents', ['file' => $file, 'slug' => 'analysis'] + $fields, ['Accept' => 'application/json'])
            ->assertCreated();

        return Document::where('slug', 'analysis')->value('visibility');
    }

    public function test_explicit_visibility_wins_over_readable(): void
    {
        // What framework-py and the R package send for publish(slug = "analysis", visibility = ...)
        $this->assertSame('private', $this->publish(['readable' => '1', 'visibility' => 'private']));
        $this->assertSame('unlisted', $this->publish(['readable' => '1', 'visibility' => 'unlisted']));
        $this->assertSame('private', $this->publish(['readable' => '0', 'visibility' => 'private']));
        $this->assertSame('public', $this->publish(['readable' => '0', 'visibility' => 'public']));
    }

    public function test_readable_alone_switches_public_and_unlisted(): void
    {
        $this->assertSame('public', $this->publish(['readable' => '1']));
        $this->assertSame('unlisted', $this->publish(['readable' => '0']));
        $this->assertSame('unlisted', $this->publish([]));
    }

    public function test_readable_never_opens_a_private_artifact(): void
    {
        $this->publish(['visibility' => 'private']);

        $this->assertSame('private', $this->publish(['readable' => '1']));
        $this->assertSame('private', $this->publish(['readable' => '0']));
        $this->assertSame('private', $this->publish([]));
    }

    public function test_new_artifacts_start_unlisted(): void
    {
        $this->assertSame('unlisted', $this->publish([]));
    }

    public function test_settings_update_prefers_visibility_and_keeps_private_closed(): void
    {
        $user = $this->project->user;
        $this->publish(['visibility' => 'private']);
        $document = Document::where('slug', 'analysis')->firstOrFail();

        $this->actingAs($user)->patch("/published/{$document->id}", ['readable' => true])->assertSessionHasNoErrors();
        $this->assertSame('private', $document->fresh()->visibility);

        $this->actingAs($user)->patch("/published/{$document->id}", ['readable' => true, 'visibility' => 'unlisted'])->assertSessionHasNoErrors();
        $this->assertSame('unlisted', $document->fresh()->visibility);

        $this->actingAs($user)->patch("/published/{$document->id}", ['readable' => true])->assertSessionHasNoErrors();
        $this->assertSame('public', $document->fresh()->visibility);

        $this->actingAs($user)->patch("/published/{$document->id}", ['visibility' => 'private'])->assertSessionHasNoErrors();
        $this->assertSame('private', $document->fresh()->visibility);
    }
}
