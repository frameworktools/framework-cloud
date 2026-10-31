<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SingleUserModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost:8080',
            'framework.single_user' => true,
            'framework.single_user_networks' => ['127.0.0.1/32', '::1/128'],
            'framework.admin_email' => null,
        ]);

        $this->admin = User::factory()->create(['email' => 'owner@example.test']);
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function local(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost:8080']);
    }

    public function test_a_local_request_is_signed_in_as_the_admin(): void
    {
        $this->local()->get('http://localhost:8080/projects')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('singleUser', true));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_the_sign_in_page_goes_straight_to_the_app(): void
    {
        $this->local()->get('http://localhost:8080/login')->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_fw_admin_email_picks_the_account(): void
    {
        $other = User::factory()->create(['email' => 'chosen@example.test']);
        config(['framework.admin_email' => 'Chosen@example.test']);

        $this->local()->get('http://localhost:8080/projects')->assertOk();
        $this->assertAuthenticatedAs($other);
    }

    public function test_off_by_default(): void
    {
        config(['framework.single_user' => false]);

        $this->local()->get('http://localhost:8080/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_requests_from_other_addresses_are_not_signed_in(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20', 'HTTP_HOST' => 'localhost:8080'])
            ->get('http://localhost:8080/dashboard')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_forwarded_requests_such_as_tunnels_are_not_signed_in(): void
    {
        $this->local()->withHeader('X-Forwarded-For', '203.0.113.9')
            ->get('http://localhost:8080/dashboard')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_a_non_localhost_host_name_is_not_signed_in(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'framework.example.edu'])
            ->get('http://framework.example.edu/dashboard')->assertRedirect();

        $this->assertGuest();
    }

    public function test_a_public_app_url_turns_it_off(): void
    {
        config(['app.url' => 'https://framework.example.edu']);

        $this->local()->get('http://localhost:8080/dashboard')->assertRedirect();
        $this->assertGuest();
    }

    public function test_extra_networks_can_be_trusted_for_docker(): void
    {
        config(['framework.single_user_networks' => ['127.0.0.1/32', '172.16.0.0/12']]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1', 'HTTP_HOST' => 'localhost:8080'])
            ->get('http://localhost:8080/projects')->assertOk();

        $this->assertAuthenticatedAs($this->admin);
    }
}
