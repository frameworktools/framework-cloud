<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\PasskeyPromptController;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\LaravelPasskeys\Database\Factories\PasskeyFactory;
use Tests\TestCase;

class PasskeyPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_in_without_a_passkey_offers_one(): void
    {
        $user = User::factory()->create();

        $this->post('/auth/magic/'.LoginToken::issue($user))->assertRedirect(route('passkey.prompt'));

        $this->get('/welcome/passkey')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/AddPasskey')
                ->where('email', $user->email));
    }

    public function test_signing_in_with_a_passkey_skips_the_offer(): void
    {
        $user = User::factory()->create();
        PasskeyFactory::new()->create(['authenticatable_id' => $user->id]);

        $this->post('/auth/magic/'.LoginToken::issue($user))->assertRedirect(route('dashboard'));
        $this->get('/welcome/passkey')->assertRedirect(route('dashboard'));
    }

    public function test_not_now_continues_to_the_page_the_person_wanted_and_is_remembered(): void
    {
        $user = User::factory()->create();

        // fw login sends people to /device, which needs a signed-in account
        $this->get('/device?code=ABCD-EFGH')->assertRedirect('/login');
        $this->post('/auth/magic/'.LoginToken::issue($user))->assertRedirect(route('passkey.prompt'));

        $this->post('/welcome/passkey/skip')
            ->assertRedirect(url('/device').'?code=ABCD-EFGH')
            ->assertCookie(PasskeyPromptController::SKIP_COOKIE);

        $this->post('/logout');
        $this->withCookie(PasskeyPromptController::SKIP_COOKIE, 'skipped')
            ->post('/auth/magic/'.LoginToken::issue($user))
            ->assertRedirect(route('dashboard'));
    }
}
