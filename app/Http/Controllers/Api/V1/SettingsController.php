<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * The authenticated user's settings document (seeded on first access).
     */
    public function show(Request $request): JsonResponse
    {
        $setting = $this->user($request)->globalSettings();

        return response()->json([
            'revision' => $setting->revision,
            'schema_version' => $setting->schema_version,
            'document' => $setting->document,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Replace the settings document. Optimistic concurrency: the request's
     * base_revision must match the current revision or the write is rejected
     * with 409 and the current state, so the client can reconcile and retry.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'array'],
            'base_revision' => ['required', 'integer', 'min:0'],
            'schema_version' => ['sometimes', 'string', 'max:20'],
            'client' => ['sometimes', 'array'],
            'client.device' => ['sometimes', 'string', 'max:120'],
            'client.app' => ['sometimes', 'string', 'max:120'],
            'client.version' => ['sometimes', 'string', 'max:40'],
        ]);

        $setting = $this->user($request)->globalSettings();

        if ($setting->revision !== $validated['base_revision']) {
            return response()->json([
                'message' => 'Settings changed since your last sync. Pull the current revision and retry.',
                'revision' => $setting->revision,
                'document' => $setting->document,
            ], 409);
        }

        $setting->replaceDocument(
            $validated['document'],
            $validated['schema_version'] ?? null,
            $validated['client'] ?? null,
        );

        return response()->json(['revision' => $setting->revision]);
    }

    private function user(Request $request): User
    {
        $tokenable = $request->user();

        abort_unless($tokenable instanceof User, 403, 'This endpoint requires a user token.');

        return $tokenable;
    }
}
