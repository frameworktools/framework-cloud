<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PasswordAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['framework.passwords' => true]);
    }

    public function test_passwords_default_on_for_self_hosted_and_off_for_hosted(): void
    {
        $this->assertTrue((bool) (require base_path('config/framework.php'))['passwords']);
    }

    public function test_login_page_shares_the_password_setting(): void
    {
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('passwords', true));

        config(['framework.passwords' => false]);
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('passwords', false));
    }

    public function test_signs_in_with_the_right_password(): void
    {
        $user = User::factory()->create(['password' => 'correct horse battery']);

        $this->post('/login/password', ['email' => $user->email, 'password' => 'correct horse battery'])
            ->assertRedirect(route('passkey.prompt'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_keep_me_signed_in_sets_the_long_lived_cookie_only_when_checked(): void
    {
        $user = User::factory()->create(['password' => 'correct horse battery']);
        $remember = fn ($response) => collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNull($remember($this->post('/login/password', ['email' => $user->email, 'password' => 'correct horse battery'])));
        $this->post('/logout');

        $this->assertNotNull($remember($this->post('/login/password', ['email' => $user->email, 'password' => 'correct horse battery', 'remember' => 1])));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_sign_in_page_asks_for_the_address_first_and_keeps_the_link_in_its_own_form(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));

        $source = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));
        $this->assertStringContainsString('Email me a sign-in link', $source);
        $this->assertStringContainsString("post('/auth/magic-link'", $source);
        $this->assertStringNotContainsString('formaction', $source);
    }

    public function test_remember_me_lasts_thirty_days(): void
    {
        $user = User::factory()->create(['password' => 'correct horse battery']);

        $response = $this->post('/login/password', ['login_step' => 'password', 'email' => $user->email, 'password' => 'correct horse battery', 'remember' => 1]);
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 120);
    }

    public function test_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'correct horse battery']);

        $this->post('/login/password', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_an_account_without_a_password_cannot_sign_in_with_one(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->post('/login/password', ['email' => $user->email, 'password' => ''])->assertSessionHasErrors('password');
        $this->post('/login/password', ['email' => $user->email, 'password' => 'anything'])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_reset_link_and_new_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => null]);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', 'reset-link-sent');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
                ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ResetPassword'));

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a brand new password',
                'password_confirmation' => 'a brand new password',
            ])->assertRedirect('/login');

            return true;
        });

        $this->assertTrue(Hash::check('a brand new password', $user->fresh()->password));
    }

    public function test_reset_request_for_an_unknown_email_looks_the_same(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHas('status', 'reset-link-sent');

        Notification::assertNothingSent();
    }

    public function test_adding_a_password_needs_no_current_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->put('/account/password', [
            'password' => 'my first password',
            'password_confirmation' => 'my first password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('my first password', $user->fresh()->password));
    }

    public function test_changing_and_removing_a_password_need_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'the old password']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'not it',
            'password' => 'the new password',
            'password_confirmation' => 'the new password',
        ])->assertSessionHasErrors('current_password');

        $this->put('/account/password', [
            'current_password' => 'the old password',
            'password' => 'the new password',
            'password_confirmation' => 'the new password',
        ])->assertSessionHasNoErrors();

        $this->delete('/account/password', ['current_password' => 'the new password'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->password);
    }

    public function test_short_passwords_are_rejected(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->put('/account/password', [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_command_sets_a_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->artisan('fw:password', ['email' => $user->email])
            ->expectsQuestion('New password (at least 10 characters)', 'set from the shell')
            ->expectsQuestion('Repeat the password', 'set from the shell')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('set from the shell', $user->fresh()->password));
    }
}
