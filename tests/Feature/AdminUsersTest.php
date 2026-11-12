<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_last_activity_comes_from_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);
        $seen = now()->subHours(3)->startOfSecond();
        DB::table('sessions')->insert([
            ['id' => 'a', 'user_id' => $admin->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => $seen->copy()->subDay()->timestamp],
            ['id' => 'b', 'user_id' => $admin->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => $seen->timestamp],
        ]);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.0.last_active', fn ($value) => $value !== null && Carbon::parse($value)->timestamp >= $seen->timestamp));
    }

    public function test_other_people_cannot_see_the_list(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('nav', fn ($nav) => ! collect($nav)->contains('href', '/admin/users')));
    }
}
