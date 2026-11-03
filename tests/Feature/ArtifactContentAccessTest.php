<?php

use App\Models\Document;
use App\Models\Project;
use App\Support\ArtifactContentAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

function sandboxBook(bool $protected = true): Document
{
    Storage::fake('local');
    $project = Project::factory()->create();
    $document = Document::create([
        'project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'book', 'title' => 'Research book',
        'content_type' => 'text/html', 'size_bytes' => 100, 'disk_path' => 'book-v1', 'entrypoint' => 'book/index.html',
        'storage_disk' => 'local', 'password_hash' => $protected ? Hash::make('book-password') : null,
    ]);
    foreach ([1, 2] as $number) {
        $document->versions()->create([
            'number' => $number, 'disk_path' => "book-v{$number}", 'entrypoint' => 'book/index.html',
            'content_type' => 'text/html', 'size_bytes' => 100, 'storage_disk' => 'local',
        ]);
        Storage::disk('local')->put("book-v{$number}/book/index.html", '<html><script type="module" src="assets/main.js"></script></html>');
        Storage::disk('local')->put("book-v{$number}/book/assets/main.js", 'fetch("../search.json").then(r => r.json())');
        Storage::disk('local')->put("book-v{$number}/book/search.json", '{"results":[]}');
        Storage::disk('local')->put("book-v{$number}/book/chapter.html", '<html>Chapter</html>');
    }

    return $document;
}

function sandboxEntryUrl($test, Document $document, int $version = 1): string
{
    $page = $test->get(route('documents.version', ['key' => $document->key, 'number' => $version]))->assertOk();
    expect(preg_match('/<iframe[^>]+src="([^"]+)"/', $page->getContent(), $matches))->toBe(1);

    return html_entity_decode($matches[1]);
}

it('supports protected nested book modules and search fetches without cookies', function () {
    $document = sandboxBook();
    $this->get($document->publicUrl())->assertViewIs('documents.password')->assertDontSee('/content/', false);
    $this->post('/d/'.$document->key.'/unlock', ['password' => 'book-password'])->assertRedirect();
    $entry = sandboxEntryUrl($this, $document);
    expect($entry)->toContain('/content/1/')->toEndWith('/book/index.html');
    $this->flushSession();
    $this->app['auth']->forgetGuards();
    foreach (['index.html', 'assets/main.js', 'search.json', 'chapter.html'] as $path) {
        $url = substr($entry, 0, -strlen('index.html')).$path;
        $this->get($url, ['Origin' => 'null'])->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Content-Security-Policy', "sandbox allow-scripts allow-downloads; frame-ancestors 'self'")
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }
    $this->get($document->findVersion(1)->rawUrl())->assertForbidden();
});

it('expires content capabilities and rejects malformed signatures', function () {
    $document = sandboxBook(false);
    $entry = sandboxEntryUrl($this, $document);
    $this->get($entry)->assertOk();
    $this->get(preg_replace('~/content/1/[^/]+/~', '/content/1/invalid/', $entry))->assertForbidden();
    $this->travel(61)->minutes();
    $this->get($entry)->assertForbidden();
    $this->travelBack();
});

it('binds capabilities to exact artifact version and password state', function () {
    $document = sandboxBook(false);
    $entry = sandboxEntryUrl($this, $document);
    $this->get(str_replace('/content/1/', '/content/2/', $entry))->assertForbidden();
    $other = $document->replicate(['key', 'slug']);
    $other->key = Str::random(24);
    $other->slug = 'other-book';
    $other->save();
    $other->versions()->create($document->findVersion(1)->only(['number', 'disk_path', 'entrypoint', 'content_type', 'size_bytes', 'storage_disk']));
    $this->get(str_replace('/d/'.$document->key.'/', '/d/'.$other->key.'/', $entry))->assertForbidden();
    $document->update(['password_hash' => Hash::make('replacement-password')]);
    $this->get($entry)->assertForbidden();
    $this->post('/d/'.$document->key.'/unlock', ['password' => 'replacement-password'])->assertRedirect();
    $replacement = sandboxEntryUrl($this, $document);
    $this->flushSession();
    $this->get($replacement)->assertOk();
    $document->update(['password_hash' => Hash::make('rotated-password')]);
    $this->get($replacement)->assertForbidden();
});

it('does not mint content capabilities before the password gate is satisfied', function () {
    $document = sandboxBook();
    $this->get($document->publicUrl())->assertViewIs('documents.password');
    try {
        ArtifactContentAccess::issue($document->findVersion(1));
        $this->fail('Expected unauthorized capability issuance to fail.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('rejects traversal through content routes and revokes content when project is deleted', function () {
    $document = sandboxBook(false);
    $entry = sandboxEntryUrl($this, $document);
    $prefix = substr($entry, 0, -strlen('book/index.html'));
    $this->get($prefix.'book/%2e%2e/secret')->assertNotFound();
    $document->project->delete();
    $this->get($entry)->assertNotFound();
});

it('resolves root and nested book directory links after checking content access', function () {
    $document = sandboxBook(false);
    $entry = sandboxEntryUrl($this, $document);
    $prefix = substr($entry, 0, -strlen('book/index.html'));
    $this->get($prefix)->assertRedirect($entry);
    $this->get($prefix.'book/')->assertRedirect($entry);
    $this->get($prefix.'book')->assertRedirect($entry);
    $this->get($prefix.'missing/')->assertNotFound();
    $invalid = preg_replace('~/content/1/[^/]+/~', '/content/1/invalid/', $prefix);
    $this->get($invalid)->assertForbidden();
    $this->travel(61)->minutes();
    $this->get($prefix)->assertForbidden();
    $this->travelBack();
});
