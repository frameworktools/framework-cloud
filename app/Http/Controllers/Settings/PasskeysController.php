<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Exceptions\InvalidPasskey;

class PasskeysController extends Controller
{
    private const SESSION_KEY = 'passkey_register_options';

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Passkeys', [
            'passkeys' => $request->user()->passkeys()->latest()->get()->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'created_at' => $passkey->created_at->diffForHumans(),
                'last_used_at' => $passkey->last_used_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Creation options for the browser's authenticator.
     */
    public function options(Request $request): JsonResponse
    {
        $options = app(GeneratePasskeyRegisterOptionsAction::class)->execute($request->user());

        $request->session()->put(self::SESSION_KEY, $options);

        return response()->json(json_decode($options, true));
    }

    /**
     * Verify the attestation and store the passkey.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'passkey' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $options = $request->session()->pull(self::SESSION_KEY);

        abort_unless($options, 422, 'That took too long. Please try again.');

        try {
            app(StorePasskeyAction::class)->execute(
                $request->user(),
                $validated['passkey'],
                $options,
                $request->getHost(),
                ['name' => $validated['name'] ?: 'Passkey'],
            );
        } catch (InvalidPasskey) {
            return back()->withErrors(['passkey' => 'That passkey could not be verified. Please try again.']);
        }

        return back()->with('status', 'passkey-added');
    }

    public function destroy(Request $request, int $passkeyId): RedirectResponse
    {
        $request->user()->passkeys()->whereKey($passkeyId)->delete();

        return back();
    }
}
