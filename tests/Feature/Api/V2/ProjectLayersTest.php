<?php

namespace Tests\Feature\Api\V2;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use App\Support\Layer;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ProjectLayersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlueprintSeeder::class);
    }

    private function project(User $user, string $language = 'r'): Project
    {
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();

        return $user->projects()->create([
            'name' => 'NHANES Study', 'slug' => 'nhanes-study', 'project_type' => 'project',
            'language' => $language, 'blueprint_version_id' => $blueprint->latestVersion->id,
        ]);
    }

    public function test_pull_returns_the_layers_envelope_for_a_project_token(): void
    {
        $project = $this->project(User::factory()->create(), 'stata');
        $token = $project->issuePullToken();

        $response = $this->withToken($token)->getJson('/api/v2/projects/pull')->assertOk();

        $this->assertSame(1, $response->json('spec_version'));
        $this->assertSame('NHANES Study', $response->json('name'));
        $this->assertSame(['base', 'blueprint', 'project'], array_column($response->json('layers'), 'kind'));
        $this->assertSame('stata', $response->json('layers.2.layer.language'));
        $this->assertSame([], Layer::envelopeProblems($response->json()));

        // Empty layers stay JSON objects
        $this->assertStringContainsString('"layer":{}', $response->getContent());
    }

    public function test_pull_refuses_a_user_token(): void
    {
        $user = User::factory()->create();
        $this->project($user);

        $this->withToken($user->createToken('cli')->plainTextToken)
            ->getJson('/api/v2/projects/pull')->assertForbidden();
    }

    public function test_a_user_token_reads_a_project_by_slug(): void
    {
        $user = User::factory()->create();
        $this->project($user);
        $token = $user->createToken('cli')->plainTextToken;

        $this->withToken($token)->getJson('/api/v2/projects/nhanes-study/layers')
            ->assertOk()
            ->assertJsonPath('name', 'NHANES Study')
            ->assertJsonPath('layers.2.layer.language', 'r');

        // Reading issues no token
        $this->assertSame(0, PersonalAccessToken::where('tokenable_type', Project::class)->count());
    }

    public function test_slugs_are_scoped_to_the_signed_in_user(): void
    {
        $this->project(User::factory()->create());
        $other = User::factory()->create()->createToken('cli')->plainTextToken;

        $this->withToken($other)->getJson('/api/v2/projects/nhanes-study/layers')
            ->assertNotFound()
            ->assertJsonPath('message', "You have no project named 'nhanes-study'.");
        $this->withToken($other)->postJson('/api/v2/projects/nhanes-study/pull-tokens')->assertNotFound();
    }

    public function test_a_project_token_cannot_read_by_slug(): void
    {
        $project = $this->project(User::factory()->create());

        $this->withToken($project->issuePullToken())
            ->getJson('/api/v2/projects/nhanes-study/layers')->assertForbidden();
    }

    public function test_issuing_a_pull_token_returns_a_working_token_and_the_server_url(): void
    {
        config(['app.url' => 'https://cloud.example.edu']);
        $user = User::factory()->create();
        $this->project($user);

        $response = $this->withToken($user->createToken('cli')->plainTextToken)
            ->postJson('/api/v2/projects/nhanes-study/pull-tokens', ['name' => 'fw on lab-mac'])
            ->assertCreated()
            ->assertJsonPath('url', 'https://cloud.example.edu');

        $this->assertSame('fw on lab-mac', PersonalAccessToken::where('tokenable_type', Project::class)->sole()->name);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))->getJson('/api/v2/projects/pull')
            ->assertOk()->assertJsonPath('name', 'NHANES Study');
    }

    public function test_unauthenticated_requests_get_401(): void
    {
        $this->getJson('/api/v2/projects/pull')->assertUnauthorized();
        $this->getJson('/api/v2/projects/nhanes-study/layers')->assertUnauthorized();
    }
}
