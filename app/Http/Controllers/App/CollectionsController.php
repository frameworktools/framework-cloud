<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Document;
use App\Support\ArtifactChoices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CollectionsController extends Controller
{
    public function indexForSlug(Request $request, string $slug)
    {
        return $this->index($request, $request->user()->projects()->where('slug', $slug)->firstOrFail()->id);
    }

    public function index(Request $request, ?int $project = null)
    {
        $project = $project === null ? null : $request->user()->projects()->findOrFail($project);

        return Inertia::render('Collections/Index', [
            'project' => $project?->only('id', 'name', 'slug'),
            'projects' => $request->user()->projects()->listed()->orderBy('name')->get(['id', 'name', 'slug']),
            'collections' => Collection::whereIn('project_id', $request->user()->projects()->select('id'))->when($project, fn ($query) => $query->where('project_id', $project->id))->with('project:id,name,slug')->withCount('items')->latest()->get()->map(fn ($collection) => [
                ...$collection->only('id', 'title', 'description', 'visibility', 'items_count'),
                'project' => $collection->project->only('id', 'name', 'slug'),
                'url' => $collection->shareUrl(), 'protected' => (bool) $collection->password_hash,
                'expires_at' => $collection->expires_at?->toIso8601String(), 'expired' => $collection->isExpired(),
                'updated_at' => $collection->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, int $project)
    {
        $project = $request->user()->projects()->findOrFail($project);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $collection = Collection::create([
            ...$data, 'project_id' => $project->id, 'key' => Str::lower(Str::random(24)),
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(8)), 'visibility' => 'private',
        ]);

        return redirect('/collections/'.$collection->id.'/edit');
    }

    public function edit(Request $request, int $collection)
    {
        $collection = $this->owned($request, $collection);
        $collection->load(['project', 'items.document']);

        return Inertia::render('Collections/Edit', [
            'project' => $collection->project->only('id', 'name', 'slug'),
            'collection' => [...$collection->only('id', 'title', 'description', 'visibility'),
                'expires_at' => $collection->expires_at?->toIso8601String(), 'expired' => $collection->isExpired(),
                'url' => $collection->shareUrl(), 'protected' => (bool) $collection->password_hash],
            'items' => $collection->items->map(fn ($item) => $item->only('id', 'document_id', 'version_number', 'caption', 'share_unlisted')),
            'artifacts' => ArtifactChoices::for($collection->project_id),
        ]);
    }

    // Title and introduction save on their own as you type; sharing saves when you choose to
    public function update(Request $request, int $collection)
    {
        $collection = $this->owned($request, $collection);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'visibility' => ['sometimes', 'required', Rule::in(['private', 'unlisted', 'public'])],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'], 'remove_password' => ['sometimes', 'boolean'],
        ]);
        if (($data['visibility'] ?? null) === 'public') {
            $blocking = $collection->items()->with('document:id,title,slug')
                ->whereHas('document', fn ($q) => $q->where('visibility', '!=', 'public'))->get()
                ->map(fn ($item) => '“'.($item->document->title ?? $item->document->slug).'”');
            if ($blocking->isNotEmpty()) {
                throw ValidationException::withMessages(['visibility' => 'Public collections can contain only public artifacts. Make these public or remove them: '.$blocking->join(', ', ' and ').'.']);
            }
        }
        $attributes = collect($data)->only(['title', 'description', 'visibility'])->all();
        if (array_key_exists('expires_at', $data)) {
            $expires = $data['expires_at'] ? Carbon::parse($data['expires_at']) : null;
            if ($expires && $expires->isPast() && $expires->getTimestamp() !== $collection->expires_at?->getTimestamp()) {
                throw ValidationException::withMessages(['expires_at' => 'Choose a date in the future.']);
            }
            $attributes['expires_at'] = $expires;
        }
        if (! empty($data['remove_password'])) {
            $attributes['password_hash'] = null;
        } elseif (! empty($data['password'])) {
            $attributes['password_hash'] = Hash::make($data['password']);
        }
        $collection->update($attributes);

        return back();
    }

    public function destroy(Request $request, int $collection)
    {
        $collection = $this->owned($request, $collection);
        $project = $collection->project_id;
        $collection->delete();

        return redirect('/projects/'.$project.'/collections');
    }

    public function storeItem(Request $request, int $collection)
    {
        $collection = $this->owned($request, $collection);
        $data = $request->validate(['document_id' => ['required', 'integer'], 'share_unlisted' => ['sometimes', 'boolean']]);
        $document = Document::availableTo($collection->project_id)->where('driver', 'cloud')->findOrFail($data['document_id']);
        $this->validateSharing($collection, $document, $data['share_unlisted'] ?? false);
        $collection->items()->firstOrCreate(['document_id' => $document->id], [
            'share_unlisted' => $data['share_unlisted'] ?? false,
            'position' => ($collection->items()->max('position') ?? -1) + 1,
        ]);

        return back();
    }

    public function updateItem(Request $request, int $collection, int $item)
    {
        $collection = $this->owned($request, $collection);
        $item = $collection->items()->findOrFail($item);
        $data = $request->validate([
            'version_number' => ['nullable', 'integer', 'min:1'], 'caption' => ['nullable', 'string', 'max:2000'],
            'share_unlisted' => ['required', 'boolean'],
        ]);
        if (! empty($data['version_number']) && ! $item->document->findVersion($data['version_number'])) {
            throw ValidationException::withMessages(['version_number' => 'This artifact version does not exist.']);
        }
        $this->validateSharing($collection, $item->document, $data['share_unlisted']);
        $item->update($data);

        return back();
    }

    public function destroyItem(Request $request, int $collection, int $item)
    {
        $this->owned($request, $collection)->items()->findOrFail($item)->delete();

        return back();
    }

    public function reorder(Request $request, int $collection)
    {
        $collection = $this->owned($request, $collection);
        $data = $request->validate(['items' => ['required', 'array'], 'items.*' => ['required', 'integer', 'distinct']]);
        $current = $collection->items()->pluck('id')->sort()->values()->all();
        $submitted = collect($data['items'])->sort()->values()->all();
        if ($current !== $submitted) {
            throw ValidationException::withMessages(['items' => 'Include each collection entry exactly once.']);
        }
        DB::transaction(function () use ($collection, $data) {
            foreach ($data['items'] as $position => $id) {
                $collection->items()->whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }

    private function owned(Request $request, int $id): Collection
    {
        return Collection::whereIn('project_id', $request->user()->projects()->select('id'))->findOrFail($id);
    }

    private function validateSharing(Collection $collection, Document $document, bool $confirmed): void
    {
        if ($collection->visibility === 'public' && $document->visibility !== 'public') {
            throw ValidationException::withMessages(['document_id' => 'Make this artifact public before adding it to a public collection.']);
        }
        if ($collection->visibility === 'unlisted' && $document->visibility === 'unlisted' && ! $confirmed) {
            throw ValidationException::withMessages(['share_unlisted' => 'Confirm sharing this unlisted artifact with collection readers.']);
        }
    }
}
