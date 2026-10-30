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
            ->assertSessionHas('status', 'magic-link-sent');

        Mail::assertSent(MagicLinkMail::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertSame(1, LoginToken::where('user_id', $user->id)->count());
    }

    public function test_unknown_email_without_name_sends_nothing_but_looks_identical(): void
    {
        Mail::fake();

        $this->post('/auth/magic-link', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status', 'magic-link-sent');

        Mail::assertNothingSent();
        $this->assertSame(0, User::where('email', 'nobody@example.com')->count());
    }

    public function test_registration_creates_passwordless_user_and_sends_link(): void
    {
        Mail::fake();

        $this->post('/auth/magic-link', ['name' => 'New Person', 'email' => 'new@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status', 'magic-link-sent');

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

        $this->post("/auth/magic/{$plain}")->assertRedirect(route('dashboard'));

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

        $this->post("/auth/magic/{$plain}")->assertRedirect(route('dashboard'));
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
            ->assertRedirect(route('dashboard'));
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

        $this->post("/auth/magic/{$plain}")->assertRedirect(route('dashboard'));

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

        $this->actingAs($user)->get('/settings/passkeys')->assertOk();
    }
}
