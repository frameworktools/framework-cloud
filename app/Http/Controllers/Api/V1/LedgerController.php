<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    /**
     * Append an integrity entry (called by data_save() in R). Sequence
     * numbers are per-project and monotonic so gaps are auditable.
     */
    public function store(Request $request): JsonResponse
    {
        $project = $this->project($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'algo' => ['sometimes', 'string', 'in:sha256,sha512,md5'],
            'hash' => ['required', 'string', 'max:128', 'regex:/^[0-9a-f]+$/i'],
            'size_bytes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'recorded_at' => ['sometimes', 'date'],
            'client' => ['sometimes', 'array'],
        ]);

        $entry = DB::transaction(function () use ($project, $validated) {
            // Serialize appends per project (Postgres: aggregates can't take
            // FOR UPDATE, so lock the parent row instead)
            Project::whereKey($project->id)->lockForUpdate()->first();
            $count = LedgerEntry::where('project_id', $project->id)->count();

            return LedgerEntry::create([
                'project_id' => $project->id,
                'sequence' => $count + 1,
                'name' => $validated['name'],
                'algo' => $validated['algo'] ?? 'sha256',
                'hash' => strtolower($validated['hash']),
                'size_bytes' => $validated['size_bytes'] ?? null,
                'recorded_at' => $validated['recorded_at'] ?? now(),
                'client' => $validated['client'] ?? null,
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'sequence' => $entry->sequence,
            'recorded' => $entry->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Entry history, newest first; ?name= narrows to one dataset — which is
     * what data_verify() reads.
     */
    public function index(Request $request): JsonResponse
    {
        $project = $this->project($request);

        $query = LedgerEntry::where('project_id', $project->id)->orderByDesc('sequence');

        if ($request->filled('name')) {
            $query->where('name', $request->string('name'));
        }

        return response()->json([
            'entries' => $query->limit(200)->get([
                'sequence', 'name', 'algo', 'hash', 'size_bytes', 'recorded_at', 'created_at',
            ]),
        ]);
    }

    private function project(Request $request): Project
    {
        $tokenable = $request->user();

        abort_unless($tokenable instanceof Project, 403, 'The ledger requires a project token.');
        abort_unless($tokenable->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks ledger rights.');

        return $tokenable;
    }
}
