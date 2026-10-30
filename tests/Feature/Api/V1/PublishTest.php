<?php

namespace Tests\Feature\Api\V1;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    private function html(string $name = 'analysis.html'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            '<!doctype html><html><body><h1>Results</h1><p>p &lt; 0.001</p></body></html>',
        );
    }

    public function test_project_token_can_publish_a_document(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $response = $this->withToken($project->issuePullToken())
            ->post('/api/v1/documents', ['file' => $this->html(), 'title' => 'My Analysis'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('slug', 'analysis');

        $document = Document::first();
        $this->assertSame('My Analysis', $document->title);
        Storage::disk('local')->assertExists($document->disk_path);
        $this->assertStringContainsString('/d/'.$document->key, $response->json('url'));
    }

    public function test_public_page_and_raw_serve_and_count_views(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())
            ->post('/api/v1/documents', ['file' => $this->html()], ['Accept' => 'application/json'])
            ->assertCreated();

        $document = Document::first();

        $this->get('/d/'.$document->key)
            ->assertOk()
            ->assertSee($document->title)
            ->assertSee('published with Framework Cloud');

        $this->get('/d/'.$document->key.'/raw')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->assertSame(1, $document->fresh()->views);
    }

    public function test_republishing_keeps_the_url_and_adds_a_version(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $token = $project->issuePullToken();

        $this->withToken($token)->post('/api/v1/documents', ['file' => $this->html()], ['Accept' => 'application/json']);
        $first = Document::first();
        $firstKey = $first->key;
        $firstPath = $first->disk_path;

        auth()->forgetGuards();
        $this->withToken($token)->post('/api/v1/documents', ['file' => $this->html()], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(1, Document::count());
        $fresh = Document::first();
        $this->assertSame($firstKey, $fresh->key);
        $this->assertNotSame($firstPath, $fresh->disk_path);

        // The first version keeps its files and its own link
        Storage::disk('local')->assertExists($firstPath);
        $this->assertSame([2, 1], $fresh->versions()->pluck('number')->all());
        $this->get('/d/'.$firstKey.'/versions/1')->assertOk()->assertSee('Version 1 of 2');
        $this->get('/d/'.$firstKey.'/versions/1/raw')->assertOk();
        $this->get('/d/'.$firstKey.'/versions/3')->assertNotFound();
    }

    public function test_user_tokens_cannot_publish(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->post('/api/v1/documents', ['file' => $this->html()], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_non_html_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())
            ->post('/api/v1/documents', ['file' => UploadedFile::fake()->create('data.zip', 10, 'application/zip')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
