<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Profile', [
            'profile' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'handle' => $request->user()->handle,
                'profile_public' => (bool) $request->user()->profile_public,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'handle' => [
                'nullable', 'string', 'lowercase', 'min:2', 'max:30',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::notIn(config('framework.reserved_handles')),
                Rule::unique(User::class)->ignore($user->id),
            ],
            'profile_public' => ['boolean'],
        ], [
            'handle.regex' => 'Handles are lowercase letters, numbers, and hyphens.',
            'handle.not_in' => 'That handle is reserved.',
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // A profile can only be public once it has a handle to be public at
        $user->forceFill([
            'handle' => $validated['handle'] ?: null,
            'profile_public' => ($validated['profile_public'] ?? false) && filled($validated['handle']),
        ]);

        $user->save();

        return back()->with('status', 'profile-updated');
    }

    /**
     * Passwordless account deletion: confirmed by typing the account email.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_email' => ['required', 'string', 'in:'.$request->user()->email],
        ], [
            'confirm_email.in' => 'That does not match your account email.',
        ]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
