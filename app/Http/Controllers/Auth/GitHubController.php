<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\GitHubAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Sign in with GitHub, or connect GitHub to a signed-in account, through
 * the Framework GitHub App. Accounts match by verified email, the same
 * identity magic links use, so one person has one account either way.
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

        $failed = fn (string $message) => $request->user()
            ? to_route('settings.github')->withErrors(['github' => $message])
            : to_route('login')->withErrors(['email' => $message]);

        if ($request->has('error')) {
            return $failed('GitHub sign-in was cancelled.');
        }

        try {
            /** @var GitHubUser $github */
            $github = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            return $failed('That GitHub sign-in expired. Try again.');
        }

        $account = GitHubAccount::where('github_id', $github->getId())->first();
        $current = $request->user();

        // Connecting from settings
        if ($current) {
            if ($account && $account->user_id !== $current->id) {
                return $failed("@{$github->getNickname()} is connected to another Framework Cloud account.");
            }

            $this->store($current, $github, $account);

            return to_route('settings.github')->with('status', 'github-connected');
        }

        $user = $account?->user ?? $this->userForEmail($github->getEmail());

        if (! $user) {
            if (blank($github->getEmail())) {
                return $failed('GitHub did not share a verified email address. Sign up with email, then connect GitHub.');
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

        $this->store($user, $github, $account);

        Auth::login($user, remember: true);
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
