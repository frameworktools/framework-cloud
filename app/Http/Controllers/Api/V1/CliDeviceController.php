<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CliDeviceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * fw login's device flow (RFC 8628 in shape). The CLI starts a request,
 * the user approves it at /device, and the CLI polls for a user token.
 */
class CliDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['client_name' => ['nullable', 'string', 'max:100']]);

        [$code, $deviceCode] = CliDeviceCode::issue($validated['client_name'] ?? null);

        $verificationUrl = route('device');

        return response()->json([
            'device_code' => $deviceCode,
            'user_code' => $code->displayCode(),
            'verification_url' => $verificationUrl,
            'verification_url_complete' => $verificationUrl.'?code='.$code->displayCode(),
            'interval' => CliDeviceCode::INTERVAL_SECONDS,
            'expires_in' => CliDeviceCode::LIFETIME_SECONDS,
        ]);
    }

    /**
     * Unknown, expired, and already-used codes all answer 410 expired_token:
     * expired requests are pruned, so the CLI can't tell them apart anyway,
     * and either way the fix is to run fw login again.
     */
    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate(['device_code' => ['required', 'string', 'max:255']]);

        $code = CliDeviceCode::findByDeviceCode($validated['device_code']);

        if (! $code || $code->isExpired() || $code->status === CliDeviceCode::CONSUMED) {
            return response()->json(['error' => 'expired_token'], 410);
        }

        $tooSoon = $code->polledTooSoon();
        $code->forceFill(['last_polled_at' => now()])->save();

        if ($tooSoon) {
            return response()->json(['error' => 'slow_down'], 429);
        }

        if ($code->status === CliDeviceCode::DENIED) {
            return response()->json(['error' => 'access_denied'], 403);
        }

        if ($code->status === CliDeviceCode::PENDING) {
            return response()->json(['error' => 'authorization_pending'], 428);
        }

        if (! $code->consume()) {
            return response()->json(['error' => 'expired_token'], 410);
        }

        return response()->json([
            'token' => $code->user->createToken($code->client_name ?? 'fw CLI')->plainTextToken,
            'url' => rtrim((string) config('app.url'), '/'),
        ]);
    }
}
