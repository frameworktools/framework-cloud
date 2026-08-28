<?php

namespace Tests\Feature;

use App\Models\LoginToken;
use App\Models\User;
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
            ->assertSessionHas('status', 'magic-link-sent');

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
