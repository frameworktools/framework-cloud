<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password sign-in (FW_PASSWORDS), alongside passkeys and email links.
 * Accounts start without a password; people add one in Settings, or an
 * admin sets one with `php artisan fw:password`.
 */
class PasswordController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Accounts without a password never match: the hasher rejects an
        // empty hash
        if (! Auth::attempt($credentials, remember: true)) {
            throw ValidationException::withMessages([
                'password' => 'That email and password don\'t match an account.',
            ]);
        }

        $request->session()->regenerate();

        return PasskeyPromptController::afterSignIn($request, $request->user());
    }

    public function forgot(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Email a reset link. The response is the same whether or not the
     * account exists.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'lowercase', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'reset-link-sent');
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'lowercase', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'password-reset');
    }

    /**
     * Set or change the signed-in user's password. Changing an existing
     * password requires the current one.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => $user->password ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ], [
            'current_password.current_password' => 'That isn\'t your current password.',
        ]);

        $user->forceFill(['password' => $request->input('password')])->save();

        return back()->with('status', 'password-updated');
    }

    /**
     * Remove the password, leaving passkeys, email links, and GitHub.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'That isn\'t your current password.',
        ]);

        $request->user()->forceFill(['password' => null])->save();

        return back()->with('status', 'password-removed');
    }
}
