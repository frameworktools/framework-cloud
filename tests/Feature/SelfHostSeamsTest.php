<?php

namespace Tests\Feature;

use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The self-host seams: registration gating and the no-SMTP login link
 * command.
 */
class SelfHostSeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_registration_creates_no_accounts_but_looks_identical(): void
    {
        Mail::fake();
        config(['framework.registration' => 'closed']);

        $this->post('/auth/magic-link', ['name' => 'Stranger', 'email' => 'stranger@example.com'])
            ->assertRedirect()
            ->assertRedirect(route('magic-link.sent'));

        Mail::assertNothingSent();
        $this->assertSame(0, User::where('email', 'stranger@example.com')->count());
    }

    public function test_closed_registration_still_lets_existing_users_in(): void
    {
        Mail::fake();
        config(['framework.registration' => 'closed']);
        $user = User::factory()->create();

        $this->post('/auth/magic-link', ['email' => $user->email])->assertRedirect();

        Mail::assertSentCount(1);
    }

    public function test_a_production_server_without_email_says_so_instead_of_sending(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';
        // CSRF checks skip only the testing environment
        $this->withoutMiddleware(PreventRequestForgery::class);
        $user = User::factory()->create();

        $this->post('/auth/magic-link', ['name' => 'Stranger', 'email' => 'stranger@example.com'])
            ->assertSessionHasErrors(['email' => 'This server can’t send email yet, so new accounts can’t be created here. Ask its administrator to set up email or to create your account.']);
        $this->post('/auth/magic-link', ['email' => $user->email])->assertSessionHasErrors('email');
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertSame(0, User::where('email', 'stranger@example.com')->count());
        $this->assertSame(0, LoginToken::count());
    }

    public function test_the_log_mailer_still_issues_links_outside_production(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $user = User::factory()->create();

        $this->post('/auth/magic-link', ['email' => $user->email])->assertRedirect(route('magic-link.sent'));

        Mail::assertSentCount(1);
    }

    public function test_login_link_command_prints_a_working_link(): void
    {
        $user = User::factory()->unverified()->create(['password' => null]);

        $this->artisan('fw:login-link', ['email' => $user->email])
            ->expectsOutputToContain('Sign-in link')
            ->assertSuccessful();

        $token = LoginToken::where('user_id', $user->id)->first();
        $this->assertNotNull($token);
    }

    public function test_login_link_command_can_create_the_account(): void
    {
        $this->artisan('fw:login-link', ['email' => 'lab@example.com', '--create' => true])
            ->assertSuccessful();

        $user = User::where('email', 'lab@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
    }
}
