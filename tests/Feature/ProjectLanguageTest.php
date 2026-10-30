<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use App\Support\Layer;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ProjectLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlueprintSeeder::class);
    }

    private function blueprint(string $key = 'project'): Blueprint
    {
        return Blueprint::whereNull('user_id')->where('key', $key)->firstOrFail();
    }

    public function test_projects_take_the_blueprints_language_by_default(): void
    {
        $user = User::factory()->create();
        $blueprint = $this->blueprint();
        $blueprint->update(['language' => 'stata']);

        $this->actingAs($user)->post('/projects', ['name' => 'Survey', 'blueprint_id' => $blueprint->id])
            ->assertRedirect('/projects')
            ->assertSessionHas('token_language', 'stata');

        $this->assertSame('stata', Project::sole()->language);
    }

    public function test_a_project_can_choose_another_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', ['name' => 'Survey', 'blueprint_id' => $this->blueprint()->id, 'language' => 'stata'])
            ->assertSessionHasNoErrors();
        $this->assertSame('stata', Project::sole()->language);

        $this->actingAs($user)->post('/projects', ['name' => 'Other', 'blueprint_id' => $this->blueprint()->id, 'language' => 'sas'])
            ->assertSessionHasErrors('language');
    }

    public function test_the_v1_api_accepts_and_reports_a_language(): void
    {
        $token = User::factory()->create()->createToken('r')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'Survey', 'project_type' => 'project', 'language' => 'stata'])
            ->assertCreated()->assertJsonPath('project.language', 'stata');
        $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'Analysis', 'project_type' => 'project'])
            ->assertCreated()->assertJsonPath('project.language', 'r');

        $this->withToken($token)->getJson('/api/v1/projects')
            ->assertOk()->assertJsonPath('projects.0.language', 'r');
    }

    public function test_a_blueprint_saves_its_language(): void
    {
        $user = User::factory()->create();
        $base = $this->blueprint()->latestVersion->base;

        $this->actingAs($user)->post('/blueprints', [
            'name' => 'Stata survey', 'visibility' => 'private', 'language' => 'stata',
            'base_version_id' => $base->id, 'facet_version_ids' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame('stata', Blueprint::where('user_id', $user->id)->sole()->language);
    }

    public function test_project_tokens_are_listed_and_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Survey', 'slug' => 'survey', 'project_type' => 'project']);
        $project->issuePullToken('laptop');
        $project->issuePullToken('server');

        $this->actingAs($user)->get('/projects')->assertInertia(fn ($page) => $page
            ->has('projects.0.tokens', 2)
            ->where('projects.0.tokens.0.name', 'server'));

        $id = PersonalAccessToken::where('name', 'laptop')->value('id');
        $this->actingAs($user)->delete("/projects/{$project->id}/tokens/{$id}")->assertRedirect();
        $this->assertSame(['server'], $project->tokens()->pluck('name')->all());

        // Another user's project is not found
        $other = User::factory()->create();
        $this->actingAs($other)->delete("/projects/{$project->id}/tokens/{$id}")->assertNotFound();
    }

    public function test_stata_is_refused_for_types_fw_cannot_scaffold(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('r')->plainTextToken;

        $this->actingAs($user)->post('/projects', ['name' => 'Methods', 'blueprint_id' => $this->blueprint('course')->id, 'language' => 'stata'])
            ->assertSessionHasErrors('language');
        $this->actingAs($user)->post('/blueprints', [
            'name' => 'Stata course', 'visibility' => 'private', 'language' => 'stata',
            'base_version_id' => $this->blueprint('presentation')->latestVersion->base->id, 'facet_version_ids' => [],
        ])->assertSessionHasErrors('language');
        $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'Talk', 'project_type' => 'presentation', 'language' => 'stata'])
            ->assertUnprocessable();

        $this->actingAs($user)->post('/projects', ['name' => 'Bare', 'blueprint_id' => $this->blueprint('bare')->id, 'language' => 'stata'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Project::where('project_type', '!=', 'bare')->count());
    }

    public function test_stata_layers_remove_the_r_only_folders(): void
    {
        $user = User::factory()->create();
        $version = $this->blueprint('project_sensitive')->latestVersion;
        $project = $user->projects()->create(['name' => 'Survey', 'slug' => 'survey', 'project_type' => 'project_sensitive', 'language' => 'stata', 'blueprint_version_id' => $version->id]);

        $envelope = ProjectLayers::for($project);
        $top = end($envelope['layers'])['layer'];

        $this->assertSame('stata', $top['language']);
        $this->assertSame(['functions' => null, 'notebooks' => null, 'scripts' => null, 'outputs_private_models' => null, 'outputs_public_models' => null], $top['directories']);
        $this->assertSame(['notebooks' => null], $top['render_dirs']);
        $this->assertSame([], Layer::envelopeProblems($envelope));

        // R projects keep them
        $project->update(['language' => 'r']);
        $this->assertSame(['language' => 'r'], end(ProjectLayers::for($project->fresh())['layers'])['layer']);
    }
}
