<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\Layer;
use App\Support\ProjectDefaults;
use App\Support\PublishingQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublishDriversTest extends TestCase
{
    use RefreshDatabase;

    private function metadata(array $extra = []): array
    {
        return array_replace(['slug' => 'test-model', 'title' => 'Test model', 'kind' => 'model', 'driver' => 'folder', 'version' => 1, 'size_bytes' => 42, 'sha256' => str_repeat('a', 64), 'visibility' => 'private'], $extra);
    }

    public function test_external_receipts_are_metadata_only_versioned_idempotent_and_account_scoped(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $url = '/api/v1/projects/'.$project->slug.'/artifacts/external';
        $this->withToken($project->issuePullToken())->postJson($url, $this->metadata() + ['path' => '/Volumes/private/secret'])->assertOk()->assertJsonPath('version', 1)->assertJsonPath('url', null);
        $document = Document::sole();
        $this->assertSame('', $document->disk_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertStringNotContainsString('/Volumes', $document->toJson().$document->versions->toJson());
        $this->postJson($url, $this->metadata())->assertOk();
        $this->assertCount(1, $document->versions()->get());
        $this->postJson($url, $this->metadata(['sha256' => str_repeat('b', 64)]))->assertConflict();
        $this->postJson($url, $this->metadata(['version' => 2, 'sha256' => str_repeat('b', 64)]))->assertOk();
        $this->assertSame(3, $document->fresh()->next_version_number);
        $other = Project::factory()->create();
        $this->postJson('/api/v1/projects/'.$other->slug.'/artifacts/external', $this->metadata())->assertForbidden();
        $this->actingAs($project->user)->get('/artifacts/'.$document->id)->assertInertia(fn (Assert $page) => $page
            ->where('artifact.driver', 'folder')->where('artifact.capabilities.password', false)->where('artifact.location', 'Folder'));
        $this->patch('/published/'.$document->id, ['visibility' => 'public', 'password' => 'secret-password'])->assertSessionHasErrors('visibility');
    }

    public function test_external_urls_are_safe_and_content_stays_at_the_destination(): void
    {
        $project = Project::factory()->create();
        $this->actingAs($project->user);
        $endpoint = '/api/v1/projects/'.$project->slug.'/artifacts/external';
        $this->postJson($endpoint, $this->metadata(['driver' => 'github', 'visibility' => 'public', 'url' => 'javascript:alert(1)']))->assertUnprocessable();
        $this->postJson($endpoint, $this->metadata(['url' => 'https://user:secret@example.org/result']))->assertUnprocessable();
        $this->postJson($endpoint, $this->metadata(['driver' => 'github', 'visibility' => 'public', 'url' => 'https://example.org/research/test/']))->assertOk();
        $document = Document::sole();
        $this->get($document->publicUrl())->assertRedirect('https://example.org/research/test/');
        $this->get($document->rawUrl())->assertNotFound();
        $this->get($document->latestVersion()->downloadUrl())->assertNotFound();
        $this->assertSame(0, PublishingQuota::used($project->user));
    }

    public function test_private_cloud_artifacts_require_the_owner_even_with_the_key(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $version = app(ArtifactPublisher::class)->publish($project, ['file' => UploadedFile::fake()->createWithContent('plot.svg', '<svg></svg>'), 'visibility' => 'private']);
        $this->get($version->document->publicUrl())->assertNotFound();
        $this->get($version->rawUrl())->assertForbidden();
        $this->actingAs(User::factory()->create())->get($version->document->publicUrl())->assertNotFound();
        $this->actingAs($project->user)->get($version->document->publicUrl())->assertOk();
        $this->get($version->rawUrl())->assertOk();
    }

    public function test_hosted_publishing_is_free_and_all_versions_share_the_account_quota(): void
    {
        Storage::fake('local');
        config(['framework.mode' => 'hosted', 'framework.publish_storage_limit_bytes' => 20]);
        $project = Project::factory()->create();
        $this->assertTrue($project->user->canPublish());
        $publish = fn (Project $p) => app(ArtifactPublisher::class)->publish($p, ['file' => UploadedFile::fake()->createWithContent('notes.txt', '12345678')]);
        $publish($project);
        $publish($project);
        $this->assertSame(16, PublishingQuota::used($project->user));
        try {
            $publish(Project::factory()->for($project->user)->create());
            $this->fail('Quota was not enforced.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('file', $error->errors());
        }
        $this->assertSame(16, PublishingQuota::used($project->user));
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_destination_credentials_stay_environment_references(): void
    {
        $bad = ['publish' => ['destinations' => ['store' => ['driver' => 's3', 'secret_key' => 'literal']]]];
        $this->assertNotEmpty(Layer::problems($bad, 'setting'));
        $bad['publish']['destinations']['store']['secret_key'] = 'env("S3_SECRET")';
        $this->assertSame([], Layer::problems($bad, 'setting'));
    }

    public function test_publishing_defaults_become_a_project_layer(): void
    {
        $user = User::factory()->create(['project_defaults' => ['publish_destination' => 'github']]);
        $layer = ProjectDefaults::personalLayer(ProjectDefaults::for($user), 'python');
        $this->assertSame(['default' => 'github', 'destinations' => ['github' => ['driver' => 'github']]], $layer['publish']);
        $this->assertSame([], Layer::problems($layer, 'setting'));
        $this->actingAs($user)->get('/settings/publishing')->assertInertia(fn (Assert $page) => $page->where('values.publish_destination', 'github'));
    }
}
