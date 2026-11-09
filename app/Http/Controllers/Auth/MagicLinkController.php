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
     * account first. Every address gets the same response, and nothing on
     * screen says whether an account exists or how it signs in. Only the
     * inbox shows it: no email for an unknown address, an account whose
     * owner turned links off, or a second request within RESEND_SECONDS.
     */
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        // Set for every request, so the response says nothing about whether
        // the account exists. A browser keeps its value, so an earlier link
        // still opens directly here.
        $browser = (string) $request->cookie(LoginToken::BROWSER_COOKIE);
        if (strlen($browser) !== 40) {
            $browser = Str::random(40);
        }
        Cookie::queue(LoginToken::BROWSER_COOKIE, $browser, LoginToken::LIFETIME_MINUTES);
        // "Keep me signed in" holds for a link opened in this same browser.
        // Another device asks again on the confirmation page.
        $request->boolean('remember')
            ? Cookie::queue(LoginToken::REMEMBER_COOKIE, '1', LoginToken::LIFETIME_MINUTES)
            : Cookie::queue(Cookie::forget(LoginToken::REMEMBER_COOKIE));
        // For "Use your password instead" on the next page
        $request->session()->put(LoginToken::EMAIL_SESSION_KEY, $validated['email']);

        $user = User::where('email', $validated['email'])->first();
        $isNew = false;

        if (! $user && isset($validated['name'])) {
            // Self-host seam: FW_REGISTRATION=closed turns off new accounts
            // (single-user homelabs; fw:login-link --create still works)
            if (config('framework.registration') === 'closed') {
                $request->session()->forget(LoginToken::CODE_SESSION_KEY);

                return to_route('magic-link.sent');
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => null,
            ]);
            $isNew = true;
        }

        if (! $user || ! ($isNew || $user->wantsSignInLinks())) {
            // The same page, with a code form that accepts nothing
            $request->session()->forget(LoginToken::CODE_SESSION_KEY);

            return to_route('magic-link.sent');
        }

        $recent = LoginToken::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', now()->subSeconds(LoginToken::RESEND_SECONDS))
            ->latest('id')
            ->first();

        if ($recent) {
            // The last email still stands. Its code works here only if this
            // browser asked for it.
            if ($request->session()->get(LoginToken::CODE_SESSION_KEY) !== $recent->id) {
                $request->session()->forget(LoginToken::CODE_SESSION_KEY);
            }

            return to_route('magic-link.sent');
        }

        $issued = LoginToken::issueWithCode($user, $browser);
        $url = route('magic-link.consume', array_filter([
            'token' => $issued['token'],
            // A pending computer sign-in survives an email opened in another browser.
            'next' => self::devicePath((string) $request->session()->get('url.intended', '')),
        ]));
        Mail::to($user->email)->send(new MagicLinkMail($url, $isNew, $issued['code']));
        // The code works only in this browser, through this session
        $request->session()->put(LoginToken::CODE_SESSION_KEY, $issued['id']);

        return to_route('magic-link.sent');
    }

    /** "Check your email": the same for every address. */
    public function sent(): Response
    {
        return Inertia::render('Auth/MagicLinkSent');
    }

    /** Sign in with the six-digit code from the email, in the browser that asked for it. */
    public function code(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $id = $request->session()->get(LoginToken::CODE_SESSION_KEY);
        $user = is_int($id) ? LoginToken::consumeCode($id, $validated['code']) : null;

        if (! $user) {
            return to_route('magic-link.sent')->withErrors(['code' => 'That code is not valid, or it has expired. Ask for a new sign-in link.']);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Registered($user));
        }

        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->forget(LoginToken::CODE_SESSION_KEY);
        $request->session()->regenerate();
        Cookie::expire(LoginToken::BROWSER_COOKIE);
        Cookie::expire(LoginToken::REMEMBER_COOKIE);
        Cookie::expire(LoginToken::METHOD_COOKIE);

        return PasskeyPromptController::afterSignIn($request, $user);
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

        $remember = $request->boolean('remember')
            || ($request->isMethod('GET') && $request->cookie(LoginToken::REMEMBER_COOKIE) === '1');
        Auth::login($user, remember: $remember);
        $request->session()->regenerate();
        Cookie::expire(LoginToken::BROWSER_COOKIE);
        Cookie::expire(LoginToken::REMEMBER_COOKIE);
        Cookie::expire(LoginToken::METHOD_COOKIE);

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
