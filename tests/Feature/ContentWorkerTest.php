<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** With the content Worker on, the app signs links and redirects; it never sends file bytes. */
class ContentWorkerTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT = 'https://content.example.test';

    private const KEY = 'test-signing-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        config(['app.url' => 'https://app.example.test', 'framework.content_url' => self::CONTENT,
            'framework.content_signing_key' => self::KEY, 'framework.content_worker_disk' => 'local']);
        URL::forceRootUrl('https://app.example.test');
    }

    private function publish(string $visibility, string $name = 'notebook.html', string $body = '<html>Hi</html>'): DocumentVersion
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($user)->create();

        return app(ArtifactPublisher::class)->publish($project, ['slug' => 'notebook', 'visibility' => $visibility,
            'file' => UploadedFile::fake()->createWithContent($name, $body)]);
    }

    /** The claims in a Worker link, after checking its signature the way the Worker does. */
    private function claims(string $url): array
    {
        $this->assertStringStartsWith(self::CONTENT.'/c/', $url);
        preg_match('#/c/([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)(?:/(.*))?$#', $url, $m);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $m[1], self::KEY, true)), '+/', '-_'), '=');
        $this->assertSame($expected, $m[2]);

        return json_decode(base64_decode(strtr($m[1], '-_', '+/')), true) + ['path' => $m[3] ?? ''];
    }

    public function test_the_page_frames_a_signed_worker_link(): void
    {
        $version = $this->publish('public');

        $page = $this->get('https://app.example.test/d/'.$version->document->key)->assertOk();
        preg_match('/<iframe[^>]+src="([^"]+)"/', $page->getContent(), $m);
        $claims = $this->claims(html_entity_decode($m[1]));

        $this->assertSame($version->disk_path, $claims['k']);
        $this->assertStringStartsWith('text/html', $claims['t']);
        $this->assertArrayNotHasKey('a', $claims);
        $this->assertSame('notebook.html', $claims['path']);
        $this->assertGreaterThan(now()->timestamp, $claims['e']);
    }

    public function test_downloads_redirect_to_the_worker_as_attachments(): void
    {
        $version = $this->publish('public');

        $response = $this->get('https://app.example.test/d/'.$version->document->key.'/download')->assertRedirect();
        $claims = $this->claims($response->headers->get('Location'));

        $this->assertSame($version->disk_path, $claims['k']);
        $this->assertSame('notebook.html', $claims['a']);
    }

    public function test_old_content_host_links_redirect_to_the_worker(): void
    {
        $version = $this->publish('public');
        config(['framework.content_signing_key' => null]);
        $old = $version->fresh()->contentUrl();
        config(['framework.content_signing_key' => self::KEY]);

        $response = $this->get($old)->assertRedirect();
        $this->assertSame($version->disk_path, $this->claims($response->headers->get('Location'))['k']);
    }

    public function test_private_artifacts_still_need_their_reader(): void
    {
        $version = $this->publish('private');

        $this->get('https://app.example.test/d/'.$version->document->key)->assertNotFound();
        $this->get('https://app.example.test/d/'.$version->document->key.'/download')->assertForbidden();
    }

    public function test_files_on_another_disk_are_still_served_by_the_app(): void
    {
        config(['framework.content_worker_disk' => 'r2']);
        $version = $this->publish('public');

        $this->assertStringStartsWith(self::CONTENT.'/d/', $version->contentUrl());
    }

    public function test_public_project_downloads_are_kept_in_the_bucket_and_sent_by_the_worker(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'github_private' => false, 'scaffold_status' => 'done']);
        $project->setPublicDownload(true);

        $zipPath = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('course-abc/README.md', '# Course');
        $zip->close();
        Http::fake(['github.com/lab/course/archive/HEAD.zip' => Http::response(file_get_contents($zipPath), 200)]);
        unlink($zipPath);

        $response = $this->get(parse_url($project->publicDownloadUrl(), PHP_URL_PATH))->assertRedirect();
        $object = Project::publicDownloadObject($project->public_download_key);
        $claims = $this->claims($response->headers->get('Location'));
        $this->assertSame($object, $claims['k']);
        $this->assertSame("{$project->slug}.zip", $claims['a']);
        Storage::disk('local')->assertExists($object);

        // Kept for five minutes: no second build
        $this->get(parse_url($project->publicDownloadUrl(), PHP_URL_PATH))->assertRedirect();
        Http::assertSentCount(1);

        // Turning it off removes the kept zip
        $project->setPublicDownload(false);
        Storage::disk('local')->assertMissing($object);
    }
}
