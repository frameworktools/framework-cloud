<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Support\Share;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GET /share/{type}/{id}: one of your things' short link and QR code, for
 * the share dialog lists open from their "…" menus. Private things say so.
 */
class ShareController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): JsonResponse
    {
        return $this->respond($type, $this->find($request, $type, $id));
    }

    /**
     * POST /share/{type}/{id}: make a private thing shareable from the share
     * dialog, by the same rules as its settings, then return its share. A
     * project gets its public download link; the rest become public, or
     * unlisted (anyone with the link).
     */
    public function open(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->find($request, $type, $id);
        $visibility = $request->validate(['visibility' => ['required', Rule::in(['public', 'unlisted'])]])['visibility'];

        match (true) {
            $model instanceof Project => $model->setPublicDownload(true),
            $model instanceof Document => $this->openDocument($model, $visibility),
            $model instanceof Collection => $this->openCollection($model, $visibility),
            default => $model->update(['visibility' => $visibility]),
        };

        return $this->respond($type, $model->fresh());
    }

    private function openDocument(Document $document, string $visibility): void
    {
        if (($document->driver ?? 'cloud') !== 'cloud') {
            throw ValidationException::withMessages(['visibility' => 'Access is managed at the publishing destination.']);
        }
        $document->update(['visibility' => Document::visibilityFor($document->visibility, ['visibility' => $visibility])]);
    }

    private function openCollection(Collection $collection, string $visibility): void
    {
        if ($visibility === 'public') {
            $blocking = $collection->items()->with('document')->whereHas('document', fn ($q) => $q->where('visibility', '!=', 'public'))->get()
                ->map(fn ($item) => $item->document->title ?? $item->document->slug);
            if ($blocking->isNotEmpty()) {
                throw ValidationException::withMessages(['visibility' => 'Public collections can contain only public artifacts. Make these public or remove them: '.$blocking->join(', ', ' and ').'.']);
            }
        }
        $collection->update(['visibility' => $visibility]);
    }

    private function respond(string $type, Model $model): JsonResponse
    {
        return response()->json([
            'share' => Share::for($model),
            'visibility' => $model instanceof Project ? null : $model->visibility,
            'reason' => Share::shareable($model) ? null : match ($type) {
                'project' => 'Sharing a project gives it a public download link: anyone with it can download the project as a zip.',
                'blueprint' => 'The blueprint is private. To share it, make it public or open it to anyone with the link.',
                default => 'It\'s private. To share it, make it public or open it to anyone with the link.',
            },
        ]);
    }

    private function find(Request $request, string $type, int $id): Model
    {
        $user = $request->user();
        $projects = $user->projects()->withTrashed()->select('id');

        return match ($type) {
            'artifact' => Document::whereIn('project_id', $projects)->findOrFail($id),
            'report' => Report::whereIn('project_id', $projects)->findOrFail($id),
            'collection' => Collection::whereIn('project_id', $projects)->findOrFail($id),
            'blueprint' => Blueprint::where('user_id', $user->id)->findOrFail($id),
            'project' => $user->projects()->findOrFail($id),
        };

        return $model;
    }
}
