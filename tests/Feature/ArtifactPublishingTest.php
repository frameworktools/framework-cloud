<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class ArtifactPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function publish(Project $project, string $name = 'results.html', string $content = '<html>RESULTS</html>', array $options = []): Document
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($project->issuePullToken())->post('/api/v1/documents', $options + [
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->app['auth']->forgetGuards();

        return Document::where('project_id', $project->id)->latest('id')->first();
    }

    public function test_files_are_presented_as_pages_with_downloads_and_safe_content_types(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $table = $this->publish($project, 'coefficients.csv', "term,estimate\nintercept,2.1");
        $this->assertSame('table', $table->kind);
        $this->get($table->publicUrl())->assertOk()->assertSee('Download coefficients.csv');
        $this->get($table->latestVersion()->downloadUrl())->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=coefficients.csv');
        $model = $this->publish($project, 'model.rds', "\x00\x01\x02model");
        $this->assertSame('data', $model->kind);
        $this->get($model->publicUrl())->assertOk()->assertSee('Download this data file to open it in R, Python, Stata, or a spreadsheet.');
        $this->get($model->rawUrl())->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=model.rds');
    }

    public function test_tables_and_text_are_escaped_and_bounded_and_html_models_are_embedded(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $rows = ['term,estimate'];
        $rows[] = '"<script>alert(1)</script>",2.5';
        for ($i = 0; $i < 110; $i++) {
            $rows[] = 'term-'.$i.','.$i;
        }
        $table = $this->publish($project, 'table.csv', implode("\n", $rows));
        $this->get($table->publicUrl())->assertOk()->assertSee('<table', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('111 rows');
        // Without its typed table (as for files published before them), a CSV keeps the bounded plain preview
        Storage::disk('local')->delete($table->latestVersion()->representationPath());
        $this->get($table->publicUrl())->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Preview limited to 100 rows')->assertDontSee('term-109');
        $text = $this->publish($project, 'notes.txt', '<script>unsafe</script>'.str_repeat('x', 1_048_576));
        $this->get($text->publicUrl())->assertOk()->assertSee('<pre', false)
            ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertSee('Preview limited to 1 MB.');
        $model = $this->publish($project, 'model.html', '<html>COEFFICIENTS</html>', ['kind' => 'model']);
        $this->get($model->publicUrl())->assertOk()->assertSee('<iframe', false)->assertDontSee('This artifact is available to download.');
    }

    public function test_password_covers_pages_raw_versions_assets_and_downloads_even_with_external_host(): void
    {
        Storage::fake('local');
        config(['framework.documents_base_url' => 'https://public.example.test']);
        $project = Project::factory()->create();
        $document = $this->publish($project, options: ['password' => 'secret-pass']);
        $this->get($document->publicUrl())->assertOk()->assertSee('Password-protected artifact')->assertDontSee('RESULTS');
        foreach ([$document->rawUrl(), $document->latestVersion()->rawUrl(), $document->latestVersion()->downloadUrl(), '/d/'.$document->key.'/anything.js'] as $url) {
            $this->get($url)->assertForbidden()->assertHeader('Cache-Control', 'no-cache, private');
        }
        $this->post('/d/'.$document->key.'/unlock', ['password' => 'incorrect'])->assertSessionHasErrors('password');
        $this->post('/d/'.$document->key.'/unlock', ['password' => 'secret-pass'])->assertRedirect($document->publicUrl());
        $raw = $this->get($document->rawUrl())->assertOk();
        $this->assertStringContainsString('RESULTS', $raw->streamedContent());
        $this->assertSame("sandbox allow-scripts allow-downloads; frame-ancestors 'self'", $raw->headers->get('Content-Security-Policy'));
        $this->get($document->publicUrl())->assertSee('sandbox="allow-scripts allow-downloads"', false)->assertDontSee('https://public.example.test', false);
        $document->update(['password_hash' => password_hash('new-password', PASSWORD_BCRYPT)]);
        $this->get($document->rawUrl())->assertForbidden();
    }

    public function test_unlock_returns_to_the_requested_immutable_version(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $document = $this->publish($project, options: ['password' => 'secret-pass']);
        $this->publish($project, content: '<html>VERSION TWO</html>');
        $version = $document->findVersion(1);
        $this->get($version->url())->assertOk()->assertSee('name="version" value="1"', false);
        $this->post('/d/'.$document->key.'/unlock', ['password' => 'secret-pass', 'version' => 1])->assertRedirect($version->url());
        $this->get($version->url())->assertOk()->assertSee('Version 1 of 2');
    }

    public function test_web_upload_requires_project_ownership_and_supports_protected_images(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($owner)->create();
        $upload = fn () => UploadedFile::fake()->image('plot.png');
        $this->actingAs($owner)->post('/projects/'.$project->id.'/artifacts', ['file' => $upload(), 'password' => 'plot-password'])->assertRedirect(route('published'));
        $document = Document::first();
        $this->assertSame('image', $document->kind);
        $this->get($document->publicUrl())->assertSee('<img', false);
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->post('/projects/'.$project->id.'/artifacts', ['file' => $upload()])->assertNotFound();
    }

    public function test_legacy_implicit_version_is_preserved_and_deleted_numbers_are_never_reused(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $document = $this->publish($project);
        $document->versions()->delete();
        $document->update(['next_version_number' => 1]);
        $oldPath = $document->disk_path;
        $this->publish($project);
        $document->refresh();
        $this->assertSame([2, 1], $document->versions()->pluck('number')->all());
        $this->assertSame($oldPath, $document->findVersion(1)->disk_path);
        $document->versions()->where('number', 2)->delete();
        $this->publish($project);
        $this->assertSame([3, 1], $document->versions()->pluck('number')->all());
    }

    public function test_merge_copies_versions_preserves_source_links_and_freezes_source(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $target = $this->publish($project, 'target.html', '<html>TARGET</html>');
        $source = $this->publish($project, 'source.html', '<html>SOURCE</html>');
        $sourcePath = $source->disk_path;
        $owner = $project->user;
        $owner->update(['email_verified_at' => now()]);
        $this->actingAs($owner)->post('/published/'.$target->id.'/merge', ['source_id' => $source->id])->assertRedirect();
        $target->refresh();
        $this->assertSame([2, 1], $target->versions()->pluck('number')->all());
        $this->assertSame($source->id, $target->latestVersion()->provenance['source_document_id']);
        $this->assertNotSame($sourcePath, $target->latestVersion()->disk_path);
        $this->get($source->rawUrl())->assertOk();
        $this->delete('/published/'.$source->id)->assertRedirect();
        $this->assertStringContainsString('SOURCE', $this->get($target->rawUrl())->streamedContent());
        // A separate source verifies that publishing cannot mutate a merged identity.
        $source = $this->publish($project, 'second-source.html');
        app(ArtifactPublisher::class)->merge($target, $source);
        $this->app['auth']->forgetGuards();
        $this->withToken($project->issuePullToken())->post('/api/v1/documents', ['file' => UploadedFile::fake()->createWithContent('second-source.html', '<html>changed</html>')], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_merged_source_stays_frozen_when_the_destination_is_deleted(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $target = $this->publish($project, 'target.html');
        $source = $this->publish($project, 'source.html');
        app(ArtifactPublisher::class)->merge($target, $source);
        $target->delete();
        $source->refresh();
        $this->assertNull($source->merged_into_id);
        $this->assertNotNull($source->merged_at);
        $this->expectException(ValidationException::class);
        app(ArtifactPublisher::class)->publish($project, ['file' => UploadedFile::fake()->createWithContent('source.html', '<html>new</html>')]);
    }

    public function test_rejected_publish_cleans_staged_files_without_mutating_existing_versions(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $document = $this->publish($project);
        $document->update(['merged_into_id' => $document->id]);
        $before = Storage::disk('local')->allFiles();
        try {
            app(ArtifactPublisher::class)->publish($project, ['file' => UploadedFile::fake()->createWithContent('results.html', '<html>new</html>')]);
            $this->fail('Expected publish to be rejected.');
        } catch (ValidationException) {
            $this->assertSame($before, Storage::disk('local')->allFiles());
            $this->assertSame(1, $document->versions()->count());
        }
    }

    public function test_database_failure_rolls_back_metadata_versions_and_staged_bytes(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $document = $this->publish($project);
        $before = Storage::disk('local')->allFiles();
        Event::listen('eloquent.creating: App\\Models\\DocumentVersion', function () {
            throw new \RuntimeException('Simulated version persistence failure.');
        });
        try {
            app(ArtifactPublisher::class)->publish($project, ['title' => 'Changed title', 'file' => UploadedFile::fake()->createWithContent('results.html', '<html>new</html>')]);
            $this->fail('Expected the version write to fail.');
        } catch (ValidationException) {
            $this->assertSame($before, Storage::disk('local')->allFiles());
            $this->assertSame(1, $document->versions()->count());
            $this->assertNotSame('Changed title', $document->fresh()->title);
        }
    }

    public function test_protected_book_assets_and_chapters_share_the_session_gate(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'artifact-book');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('index.html', '<html><a href="chapter.html">Chapter</a></html>');
        $zip->addFromString('chapter.html', '<html>CHAPTER</html>');
        $zip->addFromString('assets/plot.png', 'PNG');
        $zip->close();
        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'bundle' => new UploadedFile($path, 'book.zip', 'application/zip', test: true),
            'entrypoint' => 'index.html',
            'password' => 'book-password',
        ], ['Accept' => 'application/json'])->assertCreated();
        unlink($path);
        $this->app['auth']->forgetGuards();
        $document = Document::first();
        $prefix = '/d/'.$document->key;
        $this->get($prefix.'/chapter.html')->assertForbidden();
        $this->get($prefix.'/versions/1/assets/plot.png')->assertForbidden();
        $this->post($prefix.'/unlock', ['password' => 'book-password'])->assertRedirect();
        $this->get($prefix.'/chapter.html')->assertOk();
        $this->get($prefix.'/versions/1/assets/plot.png')->assertOk();
        $archive = $this->get($document->latestVersion()->downloadUrl())->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertStringStartsWith('PK', $archive->streamedContent());
    }

    public function test_nested_book_entrypoint_preserves_relative_chapters_and_assets(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $project = Project::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'artifact-book');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('book/index.html', '<html><link href="assets/style.css"><a href="chapter.html">Chapter</a></html>');
        $zip->addFromString('book/chapter.html', '<html>CHAPTER</html>');
        $zip->addFromString('book/assets/style.css', 'p{color:red}');
        $zip->close();
        $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'bundle' => new UploadedFile($path, 'book.zip', 'application/zip', test: true),
            'entrypoint' => 'book/index.html',
        ], ['Accept' => 'application/json'])->assertCreated();
        unlink($path);
        $document = Document::first();
        $entry = $document->latestVersion()->contentUrl();
        $prefix = substr($entry, 0, -strlen('index.html'));
        $this->get($document->publicUrl())->assertOk()->assertSee($entry, false);
        $this->get($document->rawUrl())->assertRedirect($entry);
        $this->get($prefix.'index.html')->assertOk();
        $this->get($prefix.'chapter.html')->assertOk();
        $this->get($prefix.'assets/style.css')->assertOk();
    }

    public function test_deleted_project_artifacts_cannot_be_read_or_downloaded(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $document = $this->publish($project);
        $project->delete();
        foreach ([$document->publicUrl(), $document->rawUrl(), $document->latestVersion()->downloadUrl(), '/d/'.$document->key.'/anything.js'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_existing_external_content_links_keep_working(): void
    {
        Storage::fake('local');
        config(['framework.documents_base_url' => 'https://public.example.test']);
        $project = Project::factory()->create();
        $document = $this->publish($project);
        $document->update(['storage_disk' => null]);
        $document->versions()->update(['storage_disk' => null]);
        $this->get($document->rawUrl())->assertRedirect('https://public.example.test/'.$document->disk_path);
        $this->get($document->publicUrl())->assertOk()->assertSee('https://public.example.test/'.$document->disk_path, false);
    }

    public function test_password_cannot_claim_to_protect_existing_external_content(): void
    {
        Storage::fake('local');
        config(['framework.documents_base_url' => 'https://public.example.test']);
        $project = Project::factory()->create();
        $document = $this->publish($project);
        $document->update(['storage_disk' => null]);
        $document->versions()->update(['storage_disk' => null]);
        $project->user->update(['email_verified_at' => now()]);
        $this->actingAs($project->user)->patch('/published/'.$document->id, ['password' => 'secret-pass'])->assertSessionHasErrors('password');
        $this->assertFalse($document->fresh()->isPasswordProtected());
    }
}
