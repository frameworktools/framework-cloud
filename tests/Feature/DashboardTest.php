<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('counts.projects', 0)->has('recentArtifacts', 0));
        $this->get('/')->assertSee(route('dashboard'), false)->assertSee('Open dashboard');
        $this->get('/projects')->assertOk();
    }
}
