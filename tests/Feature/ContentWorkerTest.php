<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
        $this->assertSame(1, $version->document->fresh()->downloads);
    }

    public function test_previews_are_built_when_publishing_not_when_viewing(): void
    {
        $version = $this->publish('public', 'table.csv', "a,b\n1,2\n");

        // The stored file is gone, yet the page still shows the preview made at publish time
        Storage::disk('local')->deleteDirectory(dirname($version->disk_path));
        $this->get('https://app.example.test/d/'.$version->document->key)->assertOk()->assertSee('<table', false);
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
        $sha = str_repeat('a', 40);
        Http::fake([
            'api.github.com/repos/lab/course/commits/HEAD' => Http::sequence()->push($sha)->push(str_repeat('b', 40)),
            'github.com/lab/course/archive/*' => Http::response(file_get_contents($zipPath), 200),
        ]);
        unlink($zipPath);
        $url = parse_url($project->publicDownloadUrl(), PHP_URL_PATH);
        $folder = Project::publicDownloadFolder($project->public_download_key);

        $claims = $this->claims($this->get($url)->assertRedirect()->headers->get('Location'));
        $this->assertSame("{$folder}/{$sha}.zip", $claims['k']);
        $this->assertSame("{$project->slug}.zip", $claims['a']);
        Storage::disk('local')->assertExists("{$folder}/{$sha}.zip");
        Http::assertSent(fn ($r) => str_ends_with($r->url(), "/archive/{$sha}.zip"));

        // The same commit: no second build
        $this->get($url)->assertRedirect();
        $this->assertCount(1, collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), '/archive/')));

        // A new commit: rebuilt, and the old zip removed
        Cache::forget("public-download-head:{$project->public_download_key}");
        $this->assertSame("{$folder}/".str_repeat('b', 40).'.zip', $this->claims($this->get($url)->headers->get('Location'))['k']);
        Storage::disk('local')->assertMissing("{$folder}/{$sha}.zip");
        $this->assertSame(3, $project->fresh()->public_downloads);

        // Turning it off removes the kept zips
        $project->setPublicDownload(false);
        Storage::disk('local')->assertMissing("{$folder}/".str_repeat('b', 40).'.zip');
    }

    public function test_charts_are_stored_pages_served_by_the_worker(): void
    {
        $spec = json_encode(['$schema' => 'https://vega.github.io/schema/vega-lite/v5.json', 'mark' => 'bar',
            'data' => ['values' => [['a' => 1]]], 'encoding' => ['x' => ['field' => 'a', 'type' => 'quantitative']]]);
        $version = $this->publish('public', 'figure.vl.json', $spec);
        $page = dirname($version->disk_path).'/chart.html';

        // Built at publish
        Storage::disk('local')->assertExists($page);
        $this->assertStringContainsString('data-phiz=', Storage::disk('local')->get($page));

        $html = $this->get('https://app.example.test/d/'.$version->document->key)->assertOk()->getContent();
        preg_match('/data-src="([^"]+)"/', $html, $m);
        $claims = $this->claims(html_entity_decode($m[1]));
        $this->assertSame($page, $claims['k']);
        $this->assertSame('chart', $claims['c']);

        // Old chart links redirect, keeping the theme
        config(['framework.content_signing_key' => null]);
        $old = $version->fresh()->chartUrl();
        config(['framework.content_signing_key' => self::KEY]);
        $location = $this->get($old.'?dark=1')->assertRedirect()->headers->get('Location');
        $this->assertStringEndsWith('?dark=1', $location);

        // A version from before stored pages gets one on first use
        Storage::disk('local')->delete($page);
        Cache::flush();
        $version->fresh()->chartUrl();
        Storage::disk('local')->assertExists($page);

        // Deleting the version's files takes the page too
        $version->fresh()->deleteFiles();
        Storage::disk('local')->assertMissing($page);
    }

    public function test_production_fails_its_health_check_without_the_worker_key(): void
    {
        $this->get('/up')->assertOk();
        $this->app->detectEnvironment(fn () => 'production');
        config(['framework.content_signing_key' => null]);
        $this->get('/up')->assertStatus(500);
    }
}
