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

        $this->actingAs($user)->put('/settings/password', [
            'password' => 'my first password',
            'password_confirmation' => 'my first password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('my first password', $user->fresh()->password));
    }

    public function test_changing_and_removing_a_password_need_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'the old password']);

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'not it',
            'password' => 'the new password',
            'password_confirmation' => 'the new password',
        ])->assertSessionHasErrors('current_password');

        $this->put('/settings/password', [
            'current_password' => 'the old password',
            'password' => 'the new password',
            'password_confirmation' => 'the new password',
        ])->assertSessionHasNoErrors();

        $this->delete('/settings/password', ['current_password' => 'the new password'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->password);
    }

    public function test_short_passwords_are_rejected(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->put('/settings/password', [
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
