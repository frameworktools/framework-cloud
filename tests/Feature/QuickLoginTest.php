<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_login_is_off_unless_enabled_in_local(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->post('/login/quick/admin')->assertNotFound();
        $this->assertGuest();

        config(['framework.quick_login' => true]);
        $this->post('/login/quick/admin')->assertNotFound(); // the test environment isn't local
        $this->assertGuest();
    }

    public function test_quick_login_signs_in_as_the_test_user_or_the_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class); // only skipped automatically while testing
        config(['framework.quick_login' => true]);

        $this->post('/login/quick/admin')->assertRedirect('/projects');
        $this->assertAuthenticatedAs($admin);

        $this->post('/logout');
        $this->post('/login/quick/user')->assertRedirect('/projects');
        $this->assertAuthenticatedAs(User::where('email', 'user@example.com')->first());
        $this->assertFalse((bool) auth()->user()->is_admin);
    }
}
