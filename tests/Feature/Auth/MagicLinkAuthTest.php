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
        $this->assertStringContainsString('If there is an account for that address, a sign-in link and a six-digit code have been sent. The link works once and expires in 15 minutes.', $source);
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
}
