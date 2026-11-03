<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollectionsController extends Controller
{
    public function index(Request $request)
    {
        $project = $this->project($request);

        return response()->json(['collections' => Collection::where('project_id', $project->id)->withCount('items')->get()->map(fn ($collection) => $this->resource($collection))]);
    }

    public function store(Request $request)
    {
        $project = $this->project($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('collections')->where('project_id', $project->id)],
            'visibility' => ['sometimes', Rule::in(['private', 'unlisted', 'public'])],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);
        $collection = Collection::create([
            'project_id' => $project->id, 'key' => Str::lower(Str::random(24)),
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'slug' => $data['slug'] ?? Str::slug($data['title']).'-'.Str::lower(Str::random(8)),
            'visibility' => $data['visibility'] ?? 'private',
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
        ]);

        return response()->json($this->resource($collection), 201);
    }

    public function storeItem(Request $request, int $collection)
    {
        $project = $this->project($request);
        $collection = Collection::where('project_id', $project->id)->findOrFail($collection);
        $data = $request->validate([
            'document_id' => ['required', 'integer'], 'version_number' => ['nullable', 'integer', 'min:1'],
            'caption' => ['nullable', 'string', 'max:2000'], 'share_unlisted' => ['sometimes', 'boolean'],
        ]);
        $document = Document::where('project_id', $project->id)->findOrFail($data['document_id']);
        if (! empty($data['version_number']) && ! $document->findVersion($data['version_number'])) {
            throw ValidationException::withMessages(['version_number' => 'This artifact version does not exist.']);
        }
        if ($collection->visibility === 'public' && $document->visibility !== 'public') {
            throw ValidationException::withMessages(['document_id' => 'Public collections require public artifacts.']);
        }
        if ($collection->visibility === 'unlisted' && $document->visibility === 'unlisted' && empty($data['share_unlisted'])) {
            throw ValidationException::withMessages(['share_unlisted' => 'Confirm sharing this unlisted artifact with collection readers.']);
        }
        $item = $collection->items()->updateOrCreate(['document_id' => $document->id], [
            'version_number' => $data['version_number'] ?? null, 'caption' => $data['caption'] ?? null,
            'share_unlisted' => $data['share_unlisted'] ?? false,
        ]);
        if ($item->wasRecentlyCreated) {
            $item->update(['position' => $collection->items()->max('position') + 1]);
        }

        return response()->json(['id' => $item->id, 'collection_id' => $collection->id, 'document_id' => $document->id, 'version_number' => $item->version_number], $item->wasRecentlyCreated ? 201 : 200);
    }

    private function project(Request $request): Project
    {
        $project = $request->user();
        abort_unless($project instanceof Project && $project->tokenCan(Project::PULL_ABILITY), 403, 'A project token is required.');
        abort_unless($project->user()->exists(), 403);

        return $project;
    }

    private function resource(Collection $collection): array
    {
        return [...$collection->only('id', 'title', 'slug', 'description', 'visibility', 'items_count'),
            'url' => $collection->shareUrl(), 'password_protected' => (bool) $collection->password_hash];
    }
}
