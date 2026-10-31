<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * After an email link or password sign-in, accounts without a passkey are
 * offered one before they continue. The page the person was heading to
 * (session url.intended, such as fw's /device approval) is kept for after.
 * Skipping sets a cookie, so this browser is not asked again.
 */
class PasskeyPromptController extends Controller
{
    public const SKIP_COOKIE = 'fw_passkey_prompt';

    /**
     * Where to go once someone has signed in.
     */
    public static function afterSignIn(Request $request, User $user): RedirectResponse
    {
        if ($request->cookie(self::SKIP_COOKIE) || $user->passkeys()->exists()) {
            return redirect()->intended(route('projects'));
        }

        return redirect()->route('passkey.prompt');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->passkeys()->exists()) {
            return redirect()->intended(route('projects'));
        }

        return Inertia::render('Auth/AddPasskey', [
            'email' => $request->user()->email,
        ]);
    }

    public function skip(): RedirectResponse
    {
        Cookie::queue(Cookie::forever(self::SKIP_COOKIE, 'skipped'));

        return redirect()->intended(route('projects'));
    }
}
