<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The share dialog's Embed view: GET /share/artifact/{id} carries embed links, or why there are none. */
class ShareEmbedTest extends TestCase
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
        $this->user->forceFill(['handle' => 'ada'])->save();
        $this->project = Project::factory()->for($this->user)->create(['slug' => 'admissions']);
    }

    private function publish(array $input = []): DocumentVersion
    {
        return app(ArtifactPublisher::class)->publish($this->project, $input + [
            'file' => UploadedFile::fake()->createWithContent('r-glm-logit.json', file_get_contents(base_path('tests/fixtures/artifacts/r-glm-logit.json'))),
        ]);
    }

    public function test_an_unlisted_artifact_gets_key_links_for_the_newest_and_current_version(): void
    {
        $document = $this->publish(['visibility' => 'unlisted'])->document;
        $this->publish(['visibility' => 'unlisted', 'slug' => $document->slug]);
        $key = $document->key;

        $this->actingAs($this->user)->getJson("/share/artifact/{$document->id}")->assertOk()
            ->assertJsonPath('embed_reason', null)
            ->assertJsonPath('embed', [
                'key' => $key,
                'kind' => 'model',
                'link' => "http://localhost/d/{$key}",
                'version_link' => "http://localhost/d/{$key}/versions/2",
                'latest_version' => 2,
                'snippet_src' => "http://localhost/api/embed/v1/artifacts/{$key}",
                'version_snippet_src' => "http://localhost/api/embed/v1/artifacts/{$key}/versions/2",
            ]);
    }

    public function test_a_public_artifact_links_by_its_readable_address(): void
    {
        $document = $this->publish(['visibility' => 'public'])->document;
        $slug = $document->slug;

        $this->actingAs($this->user)->getJson("/share/artifact/{$document->id}")->assertOk()
            ->assertJsonPath('embed.link', "http://localhost/@ada/admissions/{$slug}")
            ->assertJsonPath('embed.version_link', "http://localhost/@ada/admissions/{$slug}/versions/1");
    }

    public function test_private_and_password_protected_artifacts_say_why_they_cannot_be_embedded(): void
    {
        $document = $this->publish(['visibility' => 'private'])->document;
        $this->actingAs($this->user)->getJson("/share/artifact/{$document->id}")->assertOk()
            ->assertJsonPath('embed', null)
            ->assertJsonPath('embed_reason', "Private artifacts can't be embedded.");

        $document->forceFill(['visibility' => 'unlisted', 'password_hash' => Hash::make('secret-password')])->save();
        $this->actingAs($this->user)->getJson("/share/artifact/{$document->id}")->assertOk()
            ->assertJsonPath('embed', null)
            ->assertJsonPath('embed_reason', "Password-protected artifacts can't be embedded.");
    }

    public function test_opening_a_private_artifact_returns_its_embed_links(): void
    {
        $document = $this->publish(['visibility' => 'private'])->document;

        $this->actingAs($this->user)->postJson("/share/artifact/{$document->id}", ['visibility' => 'unlisted'])->assertOk()
            ->assertJsonPath('embed.link', "http://localhost/d/{$document->key}")
            ->assertJsonPath('embed_reason', null);
    }

    public function test_other_things_have_no_embed(): void
    {
        $document = $this->publish(['visibility' => 'unlisted'])->document;

        $this->actingAs($this->user)->getJson("/share/project/{$this->project->id}")->assertOk()
            ->assertJsonMissingPath('embed')->assertJsonMissingPath('embed_reason');
        $this->actingAs(User::factory()->create())->getJson("/share/artifact/{$document->id}")->assertNotFound();
    }

    public function test_the_embed_preview_renderer_is_vendored(): void
    {
        foreach (['framework-embed.css', 'framework-embed.iife.js'] as $file) {
            $this->assertFileExists(public_path("vendor/framework-embed/{$file}"));
        }
    }
}
