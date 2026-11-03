<?php

use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;

it('creates project-scoped collections with private defaults and excludes secrets', function () {
    $project = Project::factory()->create();
    $this->withToken($project->issuePullToken())->postJson('/api/v2/collections', [
        'title' => 'Peer review', 'password' => 'review-secret',
    ])->assertCreated()->assertJsonPath('visibility', 'private')->assertJsonPath('password_protected', true)->assertJsonMissingPath('password_hash');
    Collection::create(['project_id' => Project::factory()->create()->id, 'title' => 'Other', 'slug' => 'other', 'key' => 'other']);
    $this->getJson('/api/v2/collections')->assertOk()->assertJsonCount(1, 'collections');
});

it('requires project tokens for collection management', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('user')->plainTextToken)->getJson('/api/v2/collections')->assertForbidden();
});

it('validates sharing consent, ownership, and pinned versions for API attachments', function () {
    $project = Project::factory()->create();
    $collection = Collection::create(['project_id' => $project->id, 'title' => 'Results', 'slug' => 'results', 'key' => 'results', 'visibility' => 'unlisted']);
    $document = Document::create(['project_id' => $project->id, 'title' => 'Plot', 'slug' => 'plot', 'key' => 'plot', 'content_type' => 'image/png', 'disk_path' => 'plot.png', 'size_bytes' => 32]);
    $this->withToken($project->issuePullToken())->postJson("/api/v2/collections/{$collection->id}/items", ['document_id' => $document->id])->assertUnprocessable()->assertJsonValidationErrors('share_unlisted');
    $this->postJson("/api/v2/collections/{$collection->id}/items", ['document_id' => $document->id, 'share_unlisted' => true, 'version_number' => 99])->assertUnprocessable()->assertJsonValidationErrors('version_number');
    $this->postJson("/api/v2/collections/{$collection->id}/items", ['document_id' => $document->id, 'share_unlisted' => true, 'version_number' => 1])->assertCreated()->assertJsonPath('version_number', 1);
    $other = Collection::create(['project_id' => Project::factory()->create()->id, 'title' => 'Other', 'slug' => 'other', 'key' => 'other']);
    $this->postJson("/api/v2/collections/{$other->id}/items", ['document_id' => $document->id])->assertNotFound();
});
