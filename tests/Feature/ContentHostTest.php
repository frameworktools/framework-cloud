<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Published files served from a host of their own (FW_CONTENT_URL), apart from the app. */
class ContentHostTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT = 'https://content.example.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        config(['app.url' => 'https://app.example.test', 'framework.content_url' => self::CONTENT]);
        URL::forceRootUrl('https://app.example.test');
    }

    private function publish(string $visibility, string $name = 'notebook.html', string $body = '<html><script>document.title="x"</script>Hi</html>'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($user)->create();
        $version = app(ArtifactPublisher::class)->publish($project, ['slug' => 'notebook', 'visibility' => $visibility,
            'file' => UploadedFile::fake()->createWithContent($name, $body)]);

        return [$user, $version];
    }

    /** @param  DocumentVersion  $version */
    private function contentUrl($version): string
    {
        return $version->contentUrl();
    }

    public function test_files_open_on_the_content_host_with_signed_links(): void
    {
        [$user, $version] = $this->publish('private');

        $page = $this->actingAs($user)->get('https://app.example.test/d/'.$version->document->key)->assertOk();
        preg_match('/<iframe[^>]+src="([^"]+)"/', $page->getContent(), $m);
        $src = html_entity_decode($m[1]);
        $this->assertStringStartsWith(self::CONTENT.'/d/'.$version->document->key.'/content/1/', $src);

        // Signed-out, cookie-free, framed only by the app
        auth()->logout();
        $file = $this->get($src)->assertOk();
        $this->assertStringContainsString('Hi', $file->streamedContent());
        $this->assertSame("sandbox allow-scripts allow-downloads; frame-ancestors 'self' https://app.example.test", $file->headers->get('Content-Security-Policy'));
        $this->assertFalse($file->headers->has('X-Frame-Options'));
        $this->assertSame([], $file->headers->getCookies());

        // A changed signature is refused
        $this->get(preg_replace('#/content/1/[0-9]+\.[a-f0-9]{6}#', '/content/1/1.000000', $src))->assertForbidden();
    }

    public function test_the_app_sends_file_links_to_the_content_host(): void
    {
        [$user, $version] = $this->publish('private');
        $key = $version->document->key;

        $this->get("https://app.example.test/d/{$key}/raw")->assertForbidden();
        $raw = $this->actingAs($user)->get("https://app.example.test/d/{$key}/raw")->assertRedirect();
        $this->assertStringStartsWith(self::CONTENT.'/d/'.$key.'/content/1/', $raw->headers->get('Location'));

        $this->get("https://app.example.test/d/{$key}/content/1/123.abc/notebook.html")
            ->assertRedirect(self::CONTENT."/d/{$key}/content/1/123.abc/notebook.html");
    }

    public function test_the_content_host_serves_nothing_else(): void
    {
        foreach (['/', '/login', '/dashboard', '/api/v1/me', '/mcp', '/.well-known/oauth-authorization-server', '/d/abc'] as $path) {
            $this->get(self::CONTENT.$path)->assertNotFound();
        }
    }
}
