<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function systemBlueprint(string $key): Blueprint
    {
        return Blueprint::whereNull('user_id')->where('key', $key)->firstOrFail();
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

    public function test_projects_are_the_home_page(): void
    {
        $user = $this->user();
        $user->projects()->create(['name' => 'Survey', 'slug' => 'survey']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/projects');

        $this->actingAs($user)->get('/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects')
                ->has('projects', 1)
                ->has('steps')
                ->where('recentProjects.total', 1)
                ->where('recentProjects.items.0.slug', 'survey'));
    }

    public function test_a_project_has_its_own_page(): void
    {
        $user = $this->user();
        $project = $user->projects()->create(['name' => 'Survey', 'slug' => 'survey', 'spec' => '{"spec_version":1}', 'spec_received_at' => now()]);
        $project->issuePullToken();

        $this->actingAs($user)->get('/projects/survey')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.name', 'Survey')
                ->has('project.tokens', 1)
                ->has('project.documents', 0)
                ->where('project.spec.json', "{\n    \"spec_version\": 1\n}"));

        $this->actingAs($this->user())->get('/projects/survey')->assertNotFound();
    }

    public function test_a_deleted_projects_name_gives_a_clear_error(): void
    {
        $user = $this->user();
        $user->projects()->create(['name' => 'Survey', 'slug' => 'survey'])->delete();

        $this->actingAs($user)
            ->post('/projects', ['name' => 'Survey', 'blueprint_id' => $this->systemBlueprint('bare')->id])
            ->assertSessionHasErrors(['name' => 'You used this name for a project you deleted. Choose a different name.']);
    }

    public function test_a_project_cannot_take_a_name_the_app_uses(): void
    {
        $this->actingAs($this->user())
            ->post('/projects', ['name' => 'Create', 'blueprint_id' => $this->systemBlueprint('bare')->id])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_create_a_project_and_issue_its_token(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/projects', ['name' => 'NHANES Study', 'blueprint_id' => $this->systemBlueprint('bare')->id])
            ->assertRedirect();

        $project = $user->projects()->first();
        $this->assertSame('nhanes-study', $project->slug);
        $this->assertSame('bare', $project->project_type);
        $this->assertSame($this->systemBlueprint('bare')->latestVersion->id, $project->blueprint_version_id);

        $this->actingAs($user)
            ->post("/projects/{$project->id}/token")
            ->assertRedirect()
            ->assertSessionHas('status', 'project-token')
            ->assertSessionHas('token');

        // One from creating it, one from asking again
        $this->assertSame(2, $project->tokens()->count());
    }

    public function test_duplicate_project_names_are_rejected(): void
    {
        $user = $this->user();
        $user->projects()->create(['name' => 'Thing', 'slug' => 'thing', 'project_type' => 'project']);

        $this->actingAs($user)
            ->from('/projects')
            ->post('/projects', ['name' => 'Thing', 'blueprint_id' => $this->systemBlueprint('bare')->id])
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

    public function test_a_project_can_change_its_blueprints_pieces(): void
    {
        $user = $this->user();
        $blueprint = $this->systemBlueprint('project');

        $this->actingAs($user)->post('/projects', [
            'name' => 'Clinic Visits', 'blueprint_id' => $blueprint->id, 'customized' => true,
            'structure' => 'framework/project', 'tools' => ['quarto' => true, 'renv' => true],
            'package_sets' => ['framework/tidyverse-analysis'], 'skill_sets' => ['framework/framework'], 'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
        ])->assertRedirect('/projects/clinic-visits');

        $project = $user->projects()->firstOrFail();
        $this->assertSame($blueprint->latestVersion->id, $project->blueprint_version_id);
        $keys = array_column(ProjectLayers::for($project)['layers'], 'key');
        $this->assertContains('framework/renv', $keys);
        $this->assertContains('framework/tidyverse-analysis', $keys);
        $this->assertNotContains('framework/ggplot2', $keys);

        // The same pieces as the blueprint pin it instead of recording them
        $this->actingAs($user)->post('/projects', [
            'name' => 'Plain', 'blueprint_id' => $blueprint->id, 'customized' => true,
            'structure' => 'framework/project', 'tools' => ['quarto' => true],
            'package_sets' => [], 'skill_sets' => ['framework/framework', 'framework/ggplot2'], 'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
        ])->assertSessionHasNoErrors();
        $this->assertNull($user->projects()->where('slug', 'plain')->first()->composition);
    }

    public function test_new_project_page_lists_blueprints_with_what_they_set_up(): void
    {
        $this->actingAs($this->user())
            ->get('/projects/create?blueprint='.$this->systemBlueprint('project')->id)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Create')
                ->has('blueprints', 5)
                ->where('blueprints.0.handle', 'framework/bare')
                ->where('blueprints.1.choices.structure', 'framework/project')
                ->where('blueprints.1.choices.tools', ['quarto' => true, 'uv' => true])
                ->has('catalog.structures', 5)
                ->where('selected', $this->systemBlueprint('project')->id));
    }

    public function test_a_project_set_up_locally_hands_over_its_token(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/projects', ['name' => 'Local Study', 'blueprint_id' => $this->systemBlueprint('bare')->id])
            ->assertRedirect('/projects/local-study')
            ->assertSessionHas('status', 'project-token')
            ->assertSessionHas('token');

        $this->assertSame(1, $user->projects()->first()->tokens()->count());
    }
}
