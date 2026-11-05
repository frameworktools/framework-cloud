<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExternalArtifactsController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $actor = $request->user();
        if ($actor instanceof Project) {
            abort_unless($actor->slug === $slug && $actor->tokenCan(Project::PULL_ABILITY), 403);
            $project = $actor;
        } else {
            abort_unless($actor instanceof User, 403);
            $project = $actor->projects()->where('slug', $slug)->firstOrFail();
        }
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:10000'],
            'kind' => ['required', Rule::in(['html', 'image', 'figure', 'chart', 'table', 'data', 'model', 'test', 'text', 'pdf', 'file', 'notebook', 'report', 'book'])],
            'driver' => ['required', Rule::in(['github', 'folder', 's3'])],
            'url' => ['nullable', 'url:http,https', 'max:2048'], 'version_url' => ['nullable', 'url:http,https', 'max:2048'],
            'version' => ['required', 'integer', 'min:1', 'max:2147483646'],
            'size_bytes' => ['required', 'integer', 'min:0'], 'sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'visibility' => ['sometimes', Rule::in(['public', 'private'])], 'published_at' => ['nullable', 'date'],
            'source' => ['sometimes', 'array:language,class'], 'source.language' => ['sometimes', 'string', 'max:30'],
            'source.class' => ['sometimes', 'array', 'max:20'], 'source.class.*' => ['string', 'max:200'],
        ]);
        foreach (['url', 'version_url'] as $field) {
            if (! empty($data[$field]) && (parse_url($data[$field], PHP_URL_USER) !== null || parse_url($data[$field], PHP_URL_PASS) !== null)) {
                throw ValidationException::withMessages([$field => 'Publication URLs cannot contain credentials.']);
            }
        }
        $result = DB::transaction(function () use ($project, $data) {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $document = Document::firstOrNew(['project_id' => $project->id, 'slug' => $data['slug']]);
            abort_if($document->exists && (($document->driver ?? 'cloud') !== $data['driver'] || $document->merged_at || $document->merged_into_id), 409, 'This slug belongs to another publication.');
            if ($document->exists && ($version = $document->versions()->where('number', $data['version'])->first())) {
                abort_unless(hash_equals($version->checksum ?? '', $data['sha256']) && $version->external_url === ($data['version_url'] ?? $data['url'] ?? null), 409, 'This version already has different content.');

                return [$document, $version];
            }
            abort_if($document->exists && $data['version'] < $document->next_version_number, 409, 'An older delivery cannot replace the current publication.');
            $document->fill([
                'key' => $document->key ?? Document::newKey(), 'title' => $data['title'], 'description' => $data['description'] ?? null,
                'driver' => $data['driver'], 'external_url' => $data['url'] ?? null, 'checksum' => $data['sha256'],
                'kind' => $data['kind'] === 'figure' ? 'image' : $data['kind'],
                'visibility' => $data['visibility'] ?? 'public', 'password_hash' => null,
                'size_bytes' => $data['size_bytes'], 'disk_path' => '', 'content_type' => 'application/octet-stream',
                'next_version_number' => $data['version'] + 1,
            ])->save();
            $version = $document->versions()->create([
                'number' => $data['version'], 'driver' => $data['driver'], 'external_url' => $data['version_url'] ?? $data['url'] ?? null,
                'checksum' => $data['sha256'], 'kind' => $document->kind, 'disk_path' => '', 'content_type' => 'application/octet-stream',
                'size_bytes' => $data['size_bytes'], 'provenance' => ['source' => $data['source'] ?? null, 'published_at' => $data['published_at'] ?? null],
            ]);

            return [$document, $version];
        });
        [$document, $version] = $result;

        return response()->json(['id' => $document->id, 'slug' => $document->slug, 'driver' => $document->driver,
            'url' => $document->external_url, 'version' => $version->number, 'version_url' => $version->external_url]);
    }
}
