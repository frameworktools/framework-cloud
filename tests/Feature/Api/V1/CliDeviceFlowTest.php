<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Auth\MagicLinkController;
use App\Mail\MagicLinkMail;
use App\Models\CliDeviceCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CliDeviceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function start(array $body = []): array
    {
        return $this->postJson('/api/v1/cli/device', $body)->assertOk()->json();
    }

    private function poll(string $deviceCode)
    {
        return $this->postJson('/api/v1/cli/device/token', ['device_code' => $deviceCode]);
    }

    public function test_full_flow_issues_a_working_token_once(): void
    {
        $user = $this->user();

        $start = $this->start(['client_name' => 'fw on erik-mbp']);

        $this->assertGreaterThanOrEqual(40, strlen($start['device_code']));
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/', $start['user_code']);
        $this->assertSame(url('/device'), $start['verification_url']);
        $this->assertSame(url('/device').'?code='.$start['user_code'], $start['verification_url_complete']);
        $this->assertSame(5, $start['interval']);
        $this->assertSame(CliDeviceCode::LIFETIME_SECONDS, $start['expires_in']);

        $row = CliDeviceCode::sole();
        $this->assertSame(hash('sha256', $start['device_code']), $row->device_code_hash);
        $this->assertNotSame($start['device_code'], $row->device_code_hash);

        $this->poll($start['device_code'])->assertStatus(428)->assertExactJson(['error' => 'authorization_pending']);

        // The page accepts the code in any case and with or without the dash
        $typed = strtolower(str_replace('-', '', $start['user_code']));
        $this->actingAs($user)
            ->get('/device?code='.$typed)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Device')
                ->where('code', $start['user_code'])
                ->where('request.client_name', 'fw on erik-mbp')
                ->where('error', null));

        $this->actingAs($user)
            ->post('/device/approve', ['code' => $start['user_code']])
            ->assertRedirect('/device')
            ->assertSessionHas('status', 'device-approved');

        $this->travel(6)->seconds();

        $token = $this->poll($start['device_code'])
            ->assertOk()
            ->assertJsonPath('url', config('app.url'))
            ->json('token');

        $this->assertSame('fw on erik-mbp', $user->tokens()->sole()->name);
        $this->assertSame(CliDeviceCode::CONSUMED, $row->fresh()->status);

        // Fresh app state so the token is the only credential in play
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJson(['kind' => 'user', 'email' => $user->email]);

        $this->travel(6)->seconds();
        $this->poll($start['device_code'])->assertStatus(410)->assertExactJson(['error' => 'expired_token']);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_token_is_named_fw_cli_without_a_client_name(): void
    {
        $user = $this->user();
        $start = $this->start();

        $this->actingAs($user)->post('/device/approve', ['code' => $start['user_code']]);
        $this->poll($start['device_code'])->assertOk();

        $this->assertSame('fw CLI', $user->tokens()->sole()->name);
    }

    public function test_client_name_is_limited_to_100_characters(): void
    {
        $this->postJson('/api/v1/cli/device', ['client_name' => str_repeat('a', 101)])
            ->assertUnprocessable();
    }

    public function test_denied_request_never_issues_a_token(): void
    {
        $user = $this->user();
        $start = $this->start();

        $this->actingAs($user)
            ->post('/device/deny', ['code' => $start['user_code']])
            ->assertRedirect('/device')
            ->assertSessionHas('status', 'device-denied');

        $this->poll($start['device_code'])->assertForbidden()->assertExactJson(['error' => 'access_denied']);

        // A denied code can't be approved afterward
        $this->actingAs($user)
            ->post('/device/approve', ['code' => $start['user_code']])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_expired_request_cannot_be_approved_or_polled(): void
    {
        $user = $this->user();
        $start = $this->start();

        $this->travel(CliDeviceCode::LIFETIME_SECONDS + 1)->seconds();

        $this->actingAs($user)
            ->get('/device?code='.$start['user_code'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('request', null)
                ->where('error', 'That code has expired. Start the sign-in again on your computer to get a new one.'));

        $this->actingAs($user)
            ->post('/device/approve', ['code' => $start['user_code']])
            ->assertSessionHasErrors('code');

        $this->poll($start['device_code'])->assertStatus(410)->assertExactJson(['error' => 'expired_token']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_expired_requests_are_pruned_when_a_new_one_starts(): void
    {
        $old = $this->start();
        $this->travel(CliDeviceCode::LIFETIME_SECONDS + 1)->seconds();

        $this->start();

        $this->assertSame(1, CliDeviceCode::count());
        $this->poll($old['device_code'])->assertStatus(410);
    }

    public function test_polling_faster_than_the_interval_asks_the_cli_to_slow_down(): void
    {
        $start = $this->start();

        $this->poll($start['device_code'])->assertStatus(428);
        $this->poll($start['device_code'])->assertStatus(429)->assertExactJson(['error' => 'slow_down']);

        $this->travel(6)->seconds();
        $this->poll($start['device_code'])->assertStatus(428);
    }

    public function test_polling_does_not_use_up_the_limit_on_starting_a_login(): void
    {
        $start = $this->start();

        // fw polls about every five seconds, twelve times a minute
        foreach (range(1, 12) as $i) {
            $this->poll($start['device_code']);
        }

        $this->start();
    }

    public function test_unknown_device_code_is_reported_as_expired(): void
    {
        $this->poll('not-a-real-device-code')->assertStatus(410)->assertExactJson(['error' => 'expired_token']);
        $this->postJson('/api/v1/cli/device/token', [])->assertUnprocessable();
    }

    public function test_wrong_user_code_shows_an_error(): void
    {
        $user = $this->user();
        $this->start();

        $this->actingAs($user)
            ->get('/device?code=ZZZZ-ZZZZ')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Device')
                ->where('request', null)
                ->where('error', 'That code doesn\'t match a sign-in request. Check the code on your computer and try again.'));

        $this->actingAs($user)
            ->post('/device/approve', ['code' => 'ZZZZ-ZZZZ'])
            ->assertSessionHasErrors('code');

        $this->assertSame(CliDeviceCode::PENDING, CliDeviceCode::sole()->status);
    }

    public function test_used_code_cannot_be_approved_again(): void
    {
        $start = $this->start();

        $this->actingAs($this->user())->post('/device/approve', ['code' => $start['user_code']]);

        $other = $this->user();
        $this->actingAs($other)
            ->get('/device?code='.$start['user_code'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('error', 'That code has already been used. Start the sign-in again on your computer to get a new one.'));

        $this->actingAs($other)
            ->post('/device/approve', ['code' => $start['user_code']])
            ->assertSessionHasErrors('code');
    }

    public function test_guests_see_the_request_and_ways_to_sign_in(): void
    {
        $start = $this->start(['client_name' => 'Stata on lab-laptop']);

        $this->get('/device?code='.$start['user_code'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Device')
                ->where('code', $start['user_code'])
                ->where('clientName', 'Stata on lab-laptop')
                ->where('error', null)
                ->where('registrationOpen', true));
        $this->assertSame(url('/device').'?code='.$start['user_code'], session('url.intended'));

        // Approving still needs an account.
        $this->post('/device/approve', ['code' => $start['user_code']])->assertRedirect('/login');
        $this->assertSame(CliDeviceCode::PENDING, CliDeviceCode::sole()->status);
    }

    public function test_guests_see_expired_codes_and_closed_registration(): void
    {
        config(['framework.registration' => 'closed']);
        $this->get('/device?code=WXYZ-1234')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Device')
                ->where('registrationOpen', false)
                ->where('error', 'That code doesn\'t match a sign-in request. Check the code on your computer and try again.'));
    }

    public function test_codes_last_long_enough_to_create_an_account(): void
    {
        $this->assertSame(1200, $this->start()['expires_in']);
    }

    public function test_a_new_account_returns_to_the_request_before_the_passkey_prompt(): void
    {
        Mail::fake();
        $start = $this->start();
        $this->get('/device?code='.$start['user_code']);

        $this->post('/auth/magic-link', ['email' => 'new@example.org', 'name' => 'New Person']);
        $sent = null;
        Mail::assertSent(MagicLinkMail::class, function ($mail) use (&$sent) {
            $sent = $mail->url;

            return true;
        });
        $this->assertStringContainsString('next=%2Fdevice%3Fcode%3D'.$start['user_code'], $sent);

        // The email opens in another browser: no session, no cookie.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $path = parse_url($sent, PHP_URL_PATH).'?'.parse_url($sent, PHP_URL_QUERY);
        $this->get($path)->assertOk();
        $token = basename(parse_url($sent, PHP_URL_PATH));
        $this->post('/auth/magic/'.$token)
            ->assertRedirect(url('/device').'?code='.$start['user_code']);
    }

    public function test_email_links_only_carry_device_paths(): void
    {
        $this->assertSame('/device?code=ABCD-EFGH', MagicLinkController::devicePath('https://framework.pub/device?code=ABCD-EFGH'));
        $this->assertSame('/device', MagicLinkController::devicePath('/device?code=<script>'));
        $this->assertNull(MagicLinkController::devicePath('https://evil.example/elsewhere'));
        $this->assertNull(MagicLinkController::devicePath('/dashboard'));
    }
}
