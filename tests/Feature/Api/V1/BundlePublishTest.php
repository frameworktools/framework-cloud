<?php

namespace Tests\Feature\Api\V1;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BundlePublishTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a zip shaped like a Quarto render: entry html + _files assets.
     */
    private function bundle(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'fwbundle').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'analysis.zip', 'application/zip', test: true);
    }

    private function quartoLikeBundle(): UploadedFile
    {
        return $this->bundle([
            'analysis.html' => '<html><head><link rel="stylesheet" href="analysis_files/libs/quarto.css"></head><body>BUNDLE-BODY</body></html>',
            'analysis_files/libs/quarto.css' => 'body { color: rebeccapurple; }',
            'analysis_files/libs/quarto.js' => 'console.log("hi")',
            'analysis_files/figure-html/plot-1.png' => 'PNGBYTES',
        ]);
    }

    public function test_bundle_publish_serves_entry_and_assets(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $response = $this->withToken($project->issuePullToken())
            ->post('/api/v1/documents', [
                'bundle' => $this->quartoLikeBundle(),
                'entrypoint' => 'analysis.html',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('slug', 'analysis');

        $document = Document::first();
        $this->assertSame('analysis.html', $document->entrypoint);

        // Entry serves at /raw (streamed)
        $raw = $this->get('/d/'.$document->key.'/raw')->assertOk();
        $this->assertStringContainsString('BUNDLE-BODY', $raw->streamedContent());

        // Relative assets resolve through the wildcard route
        $css = $this->get('/d/'.$document->key.'/analysis_files/libs/quarto.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $this->assertStringContainsString('rebeccapurple', $css->streamedContent());

        $this->get('/d/'.$document->key.'/analysis_files/figure-html/plot-1.png')->assertOk();

        // Wrapper page still works
        $this->get('/d/'.$document->key)->assertOk()->assertSee('published with framework');
    }

    public function test_traversal_and_missing_assets_404(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'bundle' => $this->quartoLikeBundle(),
            'entrypoint' => 'analysis.html',
        ], ['Accept' => 'application/json'])->assertCreated();

        $key = Document::first()->key;
        $this->get('/d/'.$key.'/analysis_files/../../secrets')->assertNotFound();
        $this->get('/d/'.$key.'/nope.js')->assertNotFound();
    }

    public function test_single_documents_do_not_serve_asset_paths(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('single.html', '<html>x</html>'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->get('/d/'.Document::first()->key.'/anything.js')->assertNotFound();
    }

    public function test_unsafe_bundle_paths_are_rejected(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'bundle' => $this->bundle([
                'analysis.html' => '<html>x</html>',
                '../evil.sh' => 'rm -rf /',
            ]),
            'entrypoint' => 'analysis.html',
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, Document::count());
    }

    public function test_missing_entrypoint_is_rejected(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'bundle' => $this->bundle(['other.html' => '<html>x</html>']),
            'entrypoint' => 'analysis.html',
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_r2_base_url_switches_iframe_and_redirects(): void
    {
        Storage::fake('local');
        config(['framework.documents_base_url' => 'https://files.framework.pub']);
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'bundle' => $this->quartoLikeBundle(),
            'entrypoint' => 'analysis.html',
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = Document::first();
        $expected = 'https://files.framework.pub/'.$document->disk_path.'/analysis.html';

        // Wrapper iframes the R2 origin (separate-origin sandbox for uploads)
        $this->get('/d/'.$document->key)->assertOk()->assertSee($expected, false);

        // Old app-served URLs keep working via redirect
        $this->get('/d/'.$document->key.'/raw')->assertRedirect($expected);
        $this->get('/d/'.$document->key.'/analysis_files/libs/quarto.css')
            ->assertRedirect('https://files.framework.pub/'.$document->disk_path.'/analysis_files/libs/quarto.css');
    }

    public function test_republishing_a_bundle_replaces_content_and_keeps_url(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $token = $project->issuePullToken();

        $this->withToken($token)->post('/api/v1/documents', [
            'bundle' => $this->quartoLikeBundle(),
            'entrypoint' => 'analysis.html',
        ], ['Accept' => 'application/json'])->assertCreated();

        $first = Document::first();
        $firstKey = $first->key;
        $firstPrefix = $first->disk_path;

        auth()->forgetGuards();
        $this->withToken($token)->post('/api/v1/documents', [
            'bundle' => $this->bundle([
                'analysis.html' => '<html>REVISED</html>',
                'analysis_files/new.css' => 'p{}',
            ]),
            'entrypoint' => 'analysis.html',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(1, Document::count());
        $fresh = Document::first();
        $this->assertSame($firstKey, $fresh->key);
        $this->assertNotSame($firstPrefix, $fresh->disk_path);
        Storage::disk('local')->assertMissing($firstPrefix.'/analysis.html');
        $this->assertStringContainsString('REVISED', $this->get('/d/'.$firstKey.'/raw')->streamedContent());
    }
}
