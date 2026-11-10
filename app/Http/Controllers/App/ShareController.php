<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Report;
use App\Support\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /share/{type}/{id}: one of your things' short link and QR code, for
 * the share dialog lists open from their "…" menus. Private things say so.
 */
class ShareController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();
        $projects = $user->projects()->withTrashed()->select('id');

        $model = match ($type) {
            'artifact' => Document::whereIn('project_id', $projects)->findOrFail($id),
            'report' => Report::whereIn('project_id', $projects)->findOrFail($id),
            'collection' => Collection::whereIn('project_id', $projects)->findOrFail($id),
            'blueprint' => Blueprint::where('user_id', $user->id)->findOrFail($id),
            'project' => $user->projects()->findOrFail($id),
        };

        return response()->json([
            'share' => Share::for($model),
            'reason' => Share::shareable($model) ? null : match ($type) {
                'project' => 'Turn on its public download link on the project\'s Setup page to share it.',
                'blueprint' => 'Make the blueprint public or unlisted to share it.',
                default => 'It\'s private. Make it unlisted or public in its sharing settings to share it.',
            },
        ]);
    }
}
