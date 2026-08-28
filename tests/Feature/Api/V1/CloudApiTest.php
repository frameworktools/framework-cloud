<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudApiTest extends TestCase
{
    use RefreshDatabase;

    private function userToken(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
        $this->getJson('/api/v1/project')->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_identifies_a_user_token(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->userToken($user))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJson(['kind' => 'user', 'email' => $user->email]);
    }

    public function test_me_identifies_a_project_token(): void
    {
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJson(['kind' => 'project', 'project' => $project->slug]);
    }

    public function test_settings_are_seeded_with_defaults_on_first_read(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->userToken($user))
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('revision', 0)
            ->assertJsonPath('schema_version', '2')
            ->assertJsonPath('document.ai.canonical_file', 'AGENTS.md')
            ->assertJsonPath('document.project_types.bare.label', 'Bare (no blueprint)')
            ->assertJsonPath('document.defaults.project_type', 'bare');
    }

    public function test_settings_update_bumps_revision_and_records_history(): void
    {
        $user = User::factory()->create();
        $token = $this->userToken($user);

        $document = $this->withToken($token)->getJson('/api/v1/settings')->json('document');
        $document['defaults']['ide'] = 'rstudio';

        $this->withToken($token)
            ->putJson('/api/v1/settings', [
                'document' => $document,
                'base_revision' => 0,
                'client' => ['device' => 'test-mbp', 'app' => 'r', 'version' => '1.1.0'],
            ])
            ->assertOk()
            ->assertJsonPath('revision', 1);

        $setting = $user->globalSettings();
        $this->assertSame('rstudio', $setting->document['defaults']['ide']);
        $this->assertSame(1, $setting->revisions()->count());
        $this->assertSame('test-mbp', $setting->revisions()->first()->client['device']);
    }

    public function test_settings_update_with_stale_revision_conflicts(): void
    {
        $user = User::factory()->create();
        $token = $this->userToken($user);

        $setting = $user->globalSettings();
        $setting->replaceDocument(['meta' => ['version' => '2'], 'defaults' => ['ide' => 'vscode']]);

        $this->withToken($token)
            ->putJson('/api/v1/settings', [
                'document' => ['meta' => ['version' => '2']],
                'base_revision' => 0,
            ])
            ->assertStatus(409)
            ->assertJsonPath('revision', 1)
            ->assertJsonPath('document.defaults.ide', 'vscode');

        $this->assertSame(1, $user->globalSettings()->revision);
    }

    public function test_project_token_pulls_project_spec_and_owner_settings(): void
    {
        $this->seed(\Database\Seeders\BlueprintSeeder::class);
        $project = Project::factory()->bare()->create(['payload' => ['note' => 'hi']]);

        $this->withToken($project->issuePullToken())
            ->getJson('/api/v1/project')
            ->assertOk()
            ->assertJsonPath('project.slug', $project->slug)
            ->assertJsonPath('project.project_type', 'bare')
            ->assertJsonPath('project.payload.note', 'hi')
            ->assertJsonPath('settings.schema_version', '2')
            ->assertJsonPath('blueprint.key', 'bare');
    }

    public function test_project_token_cannot_read_or_write_settings(): void
    {
        $project = Project::factory()->create();
        $token = $project->issuePullToken();

        $this->withToken($token)->getJson('/api/v1/settings')->assertForbidden();
        $this->withToken($token)
            ->putJson('/api/v1/settings', ['document' => ['a' => 1], 'base_revision' => 0])
            ->assertForbidden();
    }

    public function test_user_token_cannot_pull_a_project(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->userToken($user))
            ->getJson('/api/v1/project')
            ->assertForbidden();
    }

    public function test_blueprints_are_listed_with_masters(): void
    {
        $this->seed(\Database\Seeders\BlueprintSeeder::class);
        $user = User::factory()->create();

        $response = $this->withToken($this->userToken($user))
            ->getJson('/api/v1/blueprints')
            ->assertOk();

        $keys = collect($response->json('blueprints'))->pluck('key');
        $this->assertTrue($keys->contains('bare'));
        $this->assertTrue($keys->contains('project_sensitive'));

        $sensitive = collect($response->json('blueprints'))->firstWhere('key', 'project_sensitive');
        $this->assertArrayHasKey('framework-sensitive-data', $sensitive['skills']);
        $this->assertNotEmpty($sensitive['agents_md']);
        $this->assertNotEmpty($sensitive['structure']['directories']);

        // Narrowing by key
        $this->withToken($this->userToken($user))
            ->getJson('/api/v1/blueprints?key=bare')
            ->assertOk()
            ->assertJsonCount(1, 'blueprints');
    }

    public function test_project_tokens_cannot_list_blueprints(): void
    {
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())
            ->getJson('/api/v1/blueprints')
            ->assertForbidden();
    }

    public function test_r_can_register_a_project_and_receive_its_key(): void
    {
        $this->seed(\Database\Seeders\BlueprintSeeder::class);
        $user = User::factory()->create();
        $token = $this->userToken($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/projects', ['name' => 'NHANES Study', 'project_type' => 'project'])
            ->assertCreated()
            ->assertJsonPath('project.slug', 'nhanes-study');

        // The returned pull token resolves the project (forget the cached
        // guard so the new bearer token is actually re-resolved)
        auth()->forgetGuards();
        $this->withToken($response->json('pull_token'))
            ->getJson('/api/v1/project')
            ->assertOk()
            ->assertJsonPath('project.slug', 'nhanes-study');

        auth()->forgetGuards();

        // Duplicate names are rejected cleanly
        $this->withToken($token)
            ->postJson('/api/v1/projects', ['name' => 'NHANES Study', 'project_type' => 'project'])
            ->assertStatus(422);

        // Listing works
        $this->withToken($token)
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'projects');
    }

    public function test_project_tokens_cannot_register_projects(): void
    {
        $this->seed(\Database\Seeders\BlueprintSeeder::class);
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())
            ->postJson('/api/v1/projects', ['name' => 'X', 'project_type' => 'bare'])
            ->assertForbidden();
    }

    public function test_tokens_carry_the_fw_prefix(): void
    {
        config(['sanctum.token_prefix' => 'fw_']);

        $user = User::factory()->create();
        $project = Project::factory()->create();

        // Sanctum formats plain tokens as "{id}|{prefix}{random}"
        $this->assertMatchesRegularExpression('/^\d+\|fw_/', $user->createToken('d')->plainTextToken);
        $this->assertMatchesRegularExpression('/^\d+\|fw_/', $project->issuePullToken());
    }
}
