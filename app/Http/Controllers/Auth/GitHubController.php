<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\GitHubAccount;
use App\Models\User;
use App\Support\StaySignedIn;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * GitHub, two ways. Connecting GitHub to a signed-in account goes through
 * the Framework GitHub App, which creates repositories. Signing in and up
 * goes through a separate OAuth app that only reads email addresses, so
 * signing in never asks for repository access. Accounts match by verified
 * email, the same identity magic links use, so one person has one account.
 */
class GitHubController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(filled(config('services.github.client_id')), 404);

        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(filled(config('services.github.client_id')), 404);

        $failed = fn (string $message) => to_route('account.github')->withErrors(['github' => $message]);

        if ($request->has('error')) {
            return $failed('Connecting GitHub was cancelled.');
        }

        // Back from installing the app on an account or organization. GitHub
        // sends installation_id and setup_action but no sign-in state, so
        // there is no code to exchange: the install itself is done.
        if ($request->filled('setup_action') && ! $request->filled('state')) {
            return to_route('account.github')->with('status', 'github-installed');
        }

        try {
            /** @var GitHubUser $github */
            $github = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            return $failed('That GitHub request expired. Try again.');
        }

        $account = GitHubAccount::where('github_id', $github->getId())->first();
        $current = $request->user();

        if ($account && $account->user_id !== $current->id) {
            return $failed("@{$github->getNickname()} is connected to another Framework Cloud account.");
        }

        $this->store($current, $github, $account);

        return to_route('account.github')->with('status', 'github-connected');
    }

    public function loginRedirect(Request $request): SymfonyRedirect
    {
        abort_unless(config('framework.github_login'), 404);
        // Kept signed in unless the sign-in page said it's a shared computer
        $request->session()->put('auth.remember', StaySignedIn::wanted($request));

        return Socialite::driver('github-login')->redirect();
    }

    public function loginCallback(Request $request): RedirectResponse
    {
        abort_unless(config('framework.github_login'), 404);

        $failed = fn (string $message) => to_route('login')->withErrors(['email' => $message]);

        if ($request->has('error')) {
            return $failed('GitHub sign-in was cancelled.');
        }

        try {
            /** @var GitHubUser $github */
            $github = Socialite::driver('github-login')->user();
        } catch (InvalidStateException) {
            return $failed('That GitHub sign-in expired. Try again.');
        }

        // A connected GitHub account first, then the verified email
        $user = GitHubAccount::where('github_id', $github->getId())->first()?->user
            ?? $this->userForEmail($github->getEmail());

        if (! $user) {
            if (blank($github->getEmail())) {
                return $failed('GitHub did not share a verified email address. Sign up with email instead.');
            }
            if (config('framework.registration') === 'closed') {
                return $failed('This server is not accepting new accounts.');
            }

            $user = User::create([
                'name' => $github->getName() ?: $github->getNickname(),
                'email' => strtolower($github->getEmail()),
                'password' => null,
            ]);
        }

        // GitHub only reports verified addresses, so this confirms the email
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Registered($user));
        }

        // The login app's token reads only email, so nothing is stored; connecting
        // GitHub for repositories is a separate step in account settings
        Auth::login($user, remember: (bool) $request->session()->pull('auth.remember', false));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->githubAccount?->delete();

        return back()->with('status', 'github-disconnected');
    }

    private function userForEmail(?string $email): ?User
    {
        return filled($email) ? User::where('email', strtolower($email))->first() : null;
    }

    private function store(User $user, GitHubUser $github, ?GitHubAccount $account): void
    {
        $account ??= $user->githubAccount ?? new GitHubAccount(['user_id' => $user->id]);

        // Socialite does not pass on the refresh token's lifetime; an expired
        // one fails at refresh time and asks the user to connect again
        $account->fill(['github_id' => $github->getId(), 'login' => $github->getNickname()])
            ->fillTokens($github->token, $github->refreshToken, $github->expiresIn, null)
            ->save();
    }
}
