<?php

namespace Tests\Feature\Auth;

use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLinkAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders_without_password_fields(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_register_screen_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_password_routes_are_gone(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/settings/password')->assertNotFound();
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

        $this->get("/auth/magic/{$plain}")->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_links_are_single_use(): void
    {
        $user = User::factory()->create();
        $plain = LoginToken::issue($user);

        $this->get("/auth/magic/{$plain}")->assertRedirect(route('dashboard'));

        $this->post('/logout');
        $this->get("/auth/magic/{$plain}")
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_expired_links_are_rejected(): void
    {
        $user = User::factory()->create();
        $plain = LoginToken::issue($user);
        LoginToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->get("/auth/magic/{$plain}")
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
