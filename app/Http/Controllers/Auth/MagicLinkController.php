<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Passwordless auth: accounts are created and signed in via emailed
 * single-use links. Consuming a link also verifies the email address —
 * one ceremony, both jobs. Passkeys (spatie/laravel-passkeys) are the
 * fast path once an account exists.
 *
 * Mail scanners such as Outlook Safe Links open every link in a message,
 * so opening a link must not use it up. Requesting a link sets a cookie;
 * opening the link in that browser signs in directly. Anywhere else (a
 * scanner, another device) shows a confirm page whose button signs in.
 */
class MagicLinkController extends Controller
{
    /**
     * Email a sign-in link. With a `name`, unknown emails register a new
     * account first. Without one, unknown emails get the same response as
     * known ones (no account enumeration).
     */
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        // Set for every request, so the response says nothing about whether
        // the account exists
        $browser = Str::random(40);
        Cookie::queue(LoginToken::BROWSER_COOKIE, $browser, LoginToken::LIFETIME_MINUTES);

        $user = User::where('email', $validated['email'])->first();
        $isNew = false;

        if (! $user && isset($validated['name'])) {
            // Self-host seam: FW_REGISTRATION=closed turns off new accounts
            // (single-user homelabs; fw:login-link --create still works)
            if (config('framework.registration') === 'closed') {
                return back()->with('status', 'magic-link-sent');
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => null,
            ]);
            $isNew = true;
        }

        if ($user) {
            $url = route('magic-link.consume', array_filter([
                'token' => LoginToken::issue($user, $browser),
                // A pending computer sign-in survives an email opened in another browser.
                'next' => self::devicePath((string) $request->session()->get('url.intended', '')),
            ]));
            Mail::to($user->email)->send(new MagicLinkMail($url, $isNew));
        }

        return back()->with('status', 'magic-link-sent');
    }

    /**
     * Show the confirm page for a link, leaving the token unused.
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        if ($next = self::devicePath((string) $request->query('next', ''))) {
            $request->session()->put('url.intended', url($next));
        }

        if (LoginToken::issuedTo($token, $request->cookie(LoginToken::BROWSER_COOKIE))) {
            return $this->consume($request, $token);
        }

        $user = LoginToken::peek($token);

        if (! $user) {
            return $this->expired();
        }

        return Inertia::render('Auth/MagicLink', [
            'token' => $token,
            'email' => $user->email,
            'isNewAccount' => ! $user->hasVerifiedEmail(),
        ]);
    }

    /**
     * Consume a link: verify the email, sign in, move on.
     */
    public function consume(Request $request, string $token): RedirectResponse
    {
        $user = LoginToken::consume($token);

        if (! $user) {
            return $this->expired();
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Registered($user));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        Cookie::expire(LoginToken::BROWSER_COOKIE);

        return PasskeyPromptController::afterSignIn($request, $user);
    }

    /**
     * The device approval path in a URL, or null. Only /device paths are
     * carried through email links, so the parameter cannot redirect elsewhere.
     */
    public static function devicePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if ($path !== '/device') {
            return null;
        }
        parse_str((string) $query, $params);
        $code = $params['code'] ?? '';

        return is_string($code) && preg_match('/^[A-Za-z0-9-]{1,20}$/', $code) ? '/device?code='.$code : '/device';
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('login')
            ->withErrors(['email' => 'That sign-in link is invalid or has expired. Request a new one.']);
    }
}
