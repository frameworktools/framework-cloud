<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_see_everyone_newest_first(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true, 'created_at' => now()->subMonth()]);
        $new = User::factory()->create(['email_verified_at' => null, 'created_at' => now()->subDay()]);

        $this->actingAs($admin)->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->has('users', 2)
            ->where('users.0.email', $new->email)
            ->where('users.0.verified', false)
            ->where('users.1.is_admin', true)
            ->where('summary.total', 2)
            ->where('summary.week', 1)
            ->where('summary.verified', 1));
        // The link is in the admin's sidebar
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('nav', fn ($nav) => collect($nav)->contains('href', '/admin/users')));
    }

    public function test_other_people_cannot_see_the_list(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('nav', fn ($nav) => ! collect($nav)->contains('href', '/admin/users')));
    }
}
