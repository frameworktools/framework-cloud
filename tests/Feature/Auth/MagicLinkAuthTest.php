<?php

namespace Tests\Feature\Auth;

use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MagicLinkAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_choice_made_when_asking_holds_for_a_link_opened_in_the_same_browser(): void
    {
        $user = User::factory()->create(['password' => null]);
        $remember = fn ($response) => collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $response = $this->withCookies([LoginToken::BROWSER_COOKIE => 'this-browser', LoginToken::REMEMBER_COOKIE => '1'])
            ->get('/auth/magic/'.LoginToken::issue($user, 'this-browser'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($remember($response));
        $this->post('/logout');

        $response = $this->withCookies([LoginToken::BROWSER_COOKIE => 'this-browser', LoginToken::REMEMBER_COOKIE => ''])
            ->get('/auth/magic/'.LoginToken::issue($user, 'this-browser'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($remember($response));
    }

    public function test_a_link_keeps_you_signed_in_only_when_asked(): void
    {
        $user = User::factory()->create(['password' => null]);
        $remember = fn ($response) => collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNull($remember($this->post('/auth/magic/'.LoginToken::issue($user))));
        $this->post('/logout');

        $this->assertNotNull($remember($this->post('/auth/magic/'.LoginToken::issue($user), ['remember' => 1])));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_screen_renders(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_register_screen_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_password_routes_are_gone_when_passwords_are_off(): void
    {
        config(['framework.passwords' => false]);

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/login/password', ['email' => 'a@b.test', 'password' => 'x'])->assertNotFound();
    }

    public function test_existing_user_receives_magic_link(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post('/auth/magic-link', ['email' => $user->email])
            ->assertRedirect()
            ->assertRedirect(route('magic-link.sent'));

        Mail::assertSent(MagicLinkMail::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertSame(1, LoginToken::where('user_id', $user->id)->count());
    }

    public function test_the_sent_page_leads_with_the_link_and_folds_the_code_away(): void
    {
        $this->get(route('magic-link.sent'))->assertOk()->assertInertia(fn ($page) => $page->component('Auth/MagicLinkSent'));

        $source = file_get_contents(resource_path('js/Pages/Auth/MagicLinkSent.vue'));
        $this->assertStringContainsString('If this address has an account, we sent a sign-in link and a six-digit code. The link works once and expires in 15 minutes.', $source);
        $this->assertStringContainsString('Have the code? Enter it instead', $source);
        $this->assertStringContainsString('Didn’t receive an email?', $source);
        $this->assertStringContainsString('Try again', $source);
        $this->assertStringNotContainsString('The code works only in the browser that asked for it.', $source);
        $this->assertStringNotContainsString('Nothing arrived', $source);
    }

    public function test_the_emailed_code_signs_in_once_in_the_browser_that_asked(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => null]);
        $code = null;

        $this->post('/auth/magic-link', ['email' => $user->email]);
        Mail::assertSent(MagicLinkMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // A wrong code comes back to the page with its error, and the form open
        $this->followingRedirects()->post('/auth/magic-code', ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertInertia(fn ($page) => $page->component('Auth/MagicLinkSent')->where('errors.code', 'That code is not valid, or it has expired. Ask for a new sign-in link.'));
        $this->assertGuest();

        $this->post('/auth/magic-code', ['code' => $code])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // Used once: the link is spent too
        $this->post('/logout');
        $this->post('/auth/magic-code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_code_does_not_work_in_another_browser_or_after_five_wrong_guesses(): void
    {
        $user = User::factory()->create(['password' => null]);
        $issued = LoginToken::issueWithCode($user);

        // Another browser has no session pointing at the token
        $this->post('/auth/magic-code', ['code' => $issued['code']])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->withSession([LoginToken::CODE_SESSION_KEY => $issued['id']]);
        $wrong = $issued['code'] === '999999' ? '888888' : '999999';
        foreach (range(1, LoginToken::MAX_CODE_ATTEMPTS) as $i) {
            $this->post('/auth/magic-code', ['code' => $wrong]);
        }
        $this->post('/auth/magic-code', ['code' => $issued['code']])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_unknown_email_without_name_sends_nothing_but_looks_identical(): void
    {
        Mail::fake();

        $this->post('/auth/magic-link', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertRedirect(route('magic-link.sent'));

        Mail::assertNothingSent();
        $this->assertSame(0, User::where('email', 'nobody@example.com')->count());
    }

    public function test_registration_creates_passwordless_user_and_sends_link(): void
    {
        Mail::fake();

        $this->post('/auth/magic-link', ['name' => 'New Person', 'email' => 'new@example.com'])
            ->assertRedirect()
            ->assertRedirect(route('magic-link.sent'));

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
        $this->assertNull($user->email_verified_at);
        Mail::assertSent(MagicLinkMail::class);
    }

    public function test_consuming_a_link_verifies_and_signs_in(): void
    {
        $user = User::factory()->unverified()->create(['password' => null]);
        $plain = LoginToken::issue($user);

        $this->post("/auth/magic/{$plain}")->assertRedirect(route('passkey.prompt'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_opening_a_link_shows_a_confirm_page_and_keeps_it_usable(): void
    {
        $user = User::factory()->unverified()->create(['password' => null]);
        $plain = LoginToken::issue($user);

        // A mail scanner opening the link twice must not use it up
        $this->get("/auth/magic/{$plain}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/MagicLink')
                ->where('email', $user->email)
                ->where('isNewAccount', true));
        $this->get("/auth/magic/{$plain}")->assertOk();
        $this->assertGuest();

        $this->post("/auth/magic/{$plain}")->assertRedirect(route('passkey.prompt'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_browser_that_asked_signs_in_directly(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $browser = $this->post('/auth/magic-link', ['email' => $user->email])
            ->assertCookie(LoginToken::BROWSER_COOKIE)
            ->getCookie(LoginToken::BROWSER_COOKIE, decrypt: true)
            ->getValue();

        $url = null;
        Mail::assertSent(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        // A scanner, or another device, gets the confirm page
        $this->get($url)->assertOk();
        $this->assertGuest();

        $this->withCookie(LoginToken::BROWSER_COOKIE, $browser)
            ->get($url)
            ->assertRedirect(route('passkey.prompt'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_another_browsers_cookie_does_not_sign_in_directly(): void
    {
        $user = User::factory()->create();
        $plain = LoginToken::issue($user, 'the-browser-that-asked');

        $this->withCookie(LoginToken::BROWSER_COOKIE, 'some-other-browser')
            ->get("/auth/magic/{$plain}")
            ->assertOk();
        $this->assertGuest();
    }

    public function test_opening_a_used_link_says_it_expired(): void
    {
        $user = User::factory()->create();
        $plain = LoginToken::issue($user);
        LoginToken::consume($plain);

        $this->get("/auth/magic/{$plain}")
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_links_are_single_use(): void
    {
        $user = User::factory()->create();
        $plain = LoginToken::issue($user);

        $this->post("/auth/magic/{$plain}")->assertRedirect(route('passkey.prompt'));

        $this->post('/logout');
        $this->post("/auth/magic/{$plain}")
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_expired_links_are_rejected(): void
    {
        $user = User::factory()->create();
        $plain = LoginToken::issue($user);
        LoginToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->post("/auth/magic/{$plain}")
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_passkey_login_routes_are_registered(): void
    {
        $this->assertTrue(app('router')->has('passkeys.authentication_options'));
        $this->assertTrue(app('router')->has('passkeys.login'));
    }

    public function test_passkeys_settings_page_renders(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/account/security')->assertOk();
    }

    public function test_signing_out_loads_the_home_page_in_full(): void
    {
        $user = User::factory()->create();

        // From an Inertia page, a 409 with the location makes the browser load it,
        // instead of showing the non-Inertia home page in a modal
        $this->actingAs($user)->post('/logout', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/'));
        $this->assertGuest();
    }

    public function test_a_second_link_is_not_sent_within_a_minute(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post('/auth/magic-link', ['email' => $user->email])->assertRedirect(route('magic-link.sent'));
        $this->post('/auth/magic-link', ['email' => $user->email])->assertRedirect(route('magic-link.sent'));
        Mail::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->post('/auth/magic-link', ['email' => $user->email]);
        Mail::assertSentCount(2);
    }

    public function test_an_account_that_turned_links_off_gets_no_email_and_the_same_page(): void
    {
        config(['framework.passwords' => true]);
        Mail::fake();
        $user = User::factory()->create(['password' => 'correct horse battery']);
        $user->forceFill(['email_sign_in_links' => false])->save();

        $this->post('/auth/magic-link', ['email' => $user->email])
            ->assertRedirect(route('magic-link.sent'))
            ->assertSessionMissing(LoginToken::CODE_SESSION_KEY);

        Mail::assertNothingSent();
        $this->assertSame(0, LoginToken::count());
    }

    public function test_an_account_without_a_password_always_gets_a_link(): void
    {
        config(['framework.passwords' => true]);
        Mail::fake();
        $user = User::factory()->create(['password' => null]);
        $user->forceFill(['email_sign_in_links' => false])->save();

        $this->post('/auth/magic-link', ['email' => $user->email]);

        Mail::assertSent(MagicLinkMail::class);
    }

    public function test_the_sent_page_offers_the_password_and_the_login_page_opens_to_it(): void
    {
        config(['framework.passwords' => true]);
        Mail::fake();

        $this->post('/auth/magic-link', ['email' => 'someone@example.com']);
        $this->get('/login?use=password')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('email', 'someone@example.com')
            ->where('usePassword', true)
            ->where('prefersPassword', false));

        $source = file_get_contents(resource_path('js/Pages/Auth/MagicLinkSent.vue'));
        $this->assertStringContainsString('Use your password instead', $source);
    }

    public function test_a_password_sign_in_makes_this_browser_open_to_the_password_next_time(): void
    {
        config(['framework.passwords' => true]);
        $user = User::factory()->create(['password' => 'correct horse battery']);

        $response = $this->post('/login/password', ['email' => $user->email, 'password' => 'correct horse battery']);
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === LoginToken::METHOD_COOKIE);
        $this->assertNotNull($cookie);
        $this->post('/logout');

        $this->withCookie(LoginToken::METHOD_COOKIE, 'password')->get('/login')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prefersPassword', true));

        config(['framework.passwords' => false]);
        $this->withCookie(LoginToken::METHOD_COOKIE, 'password')->get('/login')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prefersPassword', false));
    }

    public function test_the_sign_in_link_setting_saves(): void
    {
        config(['framework.passwords' => true]);
        $user = User::factory()->create(['password' => 'correct horse battery']);

        $this->actingAs($user)->put('/account/sign-in-links', ['email_sign_in_links' => false])->assertRedirect();
        $this->assertFalse($user->fresh()->email_sign_in_links);

        $this->actingAs($user)->get('/account/security')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('signInLinks', false));
    }
}
