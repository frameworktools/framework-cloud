<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BlueprintSeeder::class);
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_app_pages_require_auth(): void
    {
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/tokens')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_renders_with_counts(): void
    {
        $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('counts.projects')
                ->has('counts.settings_revision'));
    }

    public function test_user_can_create_a_project_and_issue_its_token(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/projects', ['name' => 'NHANES Study', 'project_type' => 'bare'])
            ->assertRedirect();

        $project = $user->projects()->first();
        $this->assertSame('nhanes-study', $project->slug);
        $this->assertSame('bare', $project->project_type);

        $this->actingAs($user)
            ->post("/projects/{$project->id}/token")
            ->assertRedirect()
            ->assertSessionHas('status', 'project-token')
            ->assertSessionHas('token');

        $this->assertSame(1, $project->tokens()->count());
    }

    public function test_duplicate_project_names_are_rejected(): void
    {
        $user = $this->user();
        $user->projects()->create(['name' => 'Thing', 'slug' => 'thing', 'project_type' => 'project']);

        $this->actingAs($user)
            ->from('/projects')
            ->post('/projects', ['name' => 'Thing', 'project_type' => 'bare'])
            ->assertSessionHasErrors('name');
    }

    public function test_users_cannot_touch_each_others_projects(): void
    {
        $other = User::factory()->create();
        $project = $other->projects()->create(['name' => 'X', 'slug' => 'x', 'project_type' => 'bare']);

        $this->actingAs($this->user())
            ->post("/projects/{$project->id}/token")
            ->assertNotFound();
    }

    public function test_user_can_create_and_revoke_api_tokens(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/tokens', ['name' => 'work-laptop'])
            ->assertRedirect()
            ->assertSessionHas('status', 'user-token')
            ->assertSessionHas('token');

        $this->assertSame(1, $user->tokens()->count());

        $tokenId = $user->tokens()->first()->id;
        $this->actingAs($user)->delete("/tokens/{$tokenId}")->assertRedirect();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_projects_page_lists_blueprints_for_the_select(): void
    {
        $this->actingAs($this->user())
            ->get('/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects')
                ->has('blueprints', 5)
                ->where('blueprints.0.key', 'bare'));
    }
}
