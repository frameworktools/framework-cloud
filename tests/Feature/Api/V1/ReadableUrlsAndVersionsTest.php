<?php

namespace Tests\Feature\Api\V1;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadableUrlsAndVersionsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $user = User::factory()->create();
        $user->forceFill(['handle' => 'ada'])->save();
        $this->project = Project::factory()->for($user)->create(['slug' => 'nhanes-study']);
        $this->token = $this->project->issuePullToken();
    }

    private function publish(array $fields = [], string $body = '<html>v</html>')
    {
        auth()->forgetGuards();

        return $this->withToken($this->token)->post('/api/v1/documents', $fields + [
            'file' => UploadedFile::fake()->createWithContent('analysis.html', $body),
        ], ['Accept' => 'application/json']);
    }

    public function test_documents_are_private_unless_readable_is_requested(): void
    {
        $this->publish()->assertCreated()->assertJsonPath('readable_url', null);

        $this->get('/@ada/nhanes-study/analysis')->assertNotFound();
        $this->get(Document::first()->publicUrl())->assertOk();
    }

    public function test_readable_documents_get_a_readable_url(): void
    {
        $this->publish(['slug' => 'Main Results', 'readable' => true])
            ->assertCreated()
            ->assertJsonPath('slug', 'main-results')
            ->assertJsonPath('readable_url', url('/@ada/nhanes-study/main-results'))
            ->assertJsonPath('url', url('/@ada/nhanes-study/main-results'));

        $this->get('/@ada/nhanes-study/main-results')->assertOk()->assertSee('Main Results');
    }

    public function test_republishing_without_readable_keeps_the_setting(): void
    {
        $this->publish(['slug' => 'analysis', 'readable' => true]);
        $this->publish(['slug' => 'analysis'])->assertJsonPath('version', 2);

        $this->get('/@ada/nhanes-study/analysis')->assertOk();
    }

    public function test_the_r_package_form_fields_turn_the_readable_url_on_and_off(): void
    {
        // framework::publish() sends readable as the multipart strings "1" and "0"
        $this->publish(['slug' => 'analysis', 'readable' => '1'])->assertJsonPath('readable_url', url('/@ada/nhanes-study/analysis'));
        $this->publish(['slug' => 'analysis', 'readable' => '0'])->assertJsonPath('readable_url', null);

        $this->get('/@ada/nhanes-study/analysis')->assertNotFound();
    }

    public function test_readable_needs_a_handle(): void
    {
        $this->project->user->forceFill(['handle' => null])->save();

        $this->publish(['readable' => true])
            ->assertCreated()
            ->assertJsonPath('readable_url', null)
            ->assertJsonPath('notice', fn ($notice) => str_contains($notice, 'handle'));
    }

    public function test_versions_are_numbered_and_viewable_at_both_urls(): void
    {
        $this->publish(['readable' => true], '<html>first</html>');
        $this->publish([], '<html>second</html>')
            ->assertJsonPath('version', 2)
            ->assertJsonPath('version_url', url('/@ada/nhanes-study/analysis/versions/2'));

        $key = Document::first()->key;

        $this->assertStringContainsString('second', $this->get("/d/{$key}/raw")->streamedContent());
        $this->assertStringContainsString('first', $this->get("/d/{$key}/versions/1/raw")->streamedContent());
        $this->get('/@ada/nhanes-study/analysis/versions/1')->assertOk()->assertSee('Version 1 of 2');
    }

    public function test_owner_can_turn_the_readable_url_off_and_delete_versions(): void
    {
        $this->publish(['readable' => true], '<html>first</html>');
        $this->publish([], '<html>second</html>');
        $document = Document::first();
        $owner = $this->project->user;

        $this->actingAs($owner)->patch("/published/{$document->id}", ['readable' => false])->assertRedirect();
        $this->get('/@ada/nhanes-study/analysis')->assertNotFound();

        // Deleting the newest makes the previous version current
        $newest = $document->latestVersion();
        $this->actingAs($owner)->delete("/published/{$document->id}/versions/2")->assertRedirect();
        Storage::disk('local')->assertMissing($newest->disk_path);
        $this->assertStringContainsString('first', $this->get("/d/{$document->key}/raw")->streamedContent());

        // Deleting the only version takes the document down
        $this->actingAs($owner)->delete("/published/{$document->id}/versions/1")->assertRedirect();
        $this->assertSame(0, Document::count());
    }

    public function test_other_users_cannot_manage_documents(): void
    {
        $this->publish();
        $document = Document::first();

        $this->actingAs(User::factory()->create())
            ->delete("/published/{$document->id}/versions/1")->assertNotFound();
    }
}
