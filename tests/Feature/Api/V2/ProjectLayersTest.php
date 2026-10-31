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

    public function test_show_gives_the_github_pages_site(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'r')->update(['github_repo' => 'ada/nhanes-study', 'scaffold_status' => 'done', 'pages_url' => 'https://ada.github.io/nhanes-study/']);

        $this->withToken($user->createToken('fw')->plainTextToken)->getJson('/api/v2/projects/nhanes-study')
            ->assertOk()
            ->assertJsonPath('github.repository', 'ada/nhanes-study')
            ->assertJsonPath('github.pages_url', 'https://ada.github.io/nhanes-study/');
    }

    public function test_show_describes_one_project_for_its_owner(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'python');
        $token = $user->createToken('fw')->plainTextToken;

        $this->withToken($token)->getJson('/api/v2/projects/nhanes-study')
            ->assertOk()
            ->assertJson(['slug' => 'nhanes-study', 'name' => 'NHANES Study', 'language' => 'python', 'type' => 'project', 'github' => null])
            ->assertJsonPath('blueprint.key', 'framework/project')
            ->assertJsonMissingPath('token');

        $this->withToken($token)->getJson('/api/v2/projects/other')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken(User::factory()->create()->createToken('fw')->plainTextToken)
            ->getJson('/api/v2/projects/nhanes-study')->assertNotFound();
    }

    public function test_blueprints_lists_what_the_user_can_build_from(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $base = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail()->latestVersion->base;
        foreach ([[$user, 'Mine', 'private'], [$other, 'Theirs', 'private']] as [$owner, $name, $visibility]) {
            $this->actingAs($owner)->post('/blueprints', [
                'name' => $name, 'visibility' => $visibility, 'language' => 'r',
                'base_version_id' => $base->id, 'facet_version_ids' => [],
            ])->assertSessionHasNoErrors();
        }

        $this->app['auth']->forgetGuards();
        $blueprints = collect($this->withToken($user->createToken('fw')->plainTextToken)
            ->getJson('/api/v2/blueprints')->assertOk()->json('blueprints'));

        $this->assertContains('Mine', $blueprints->pluck('name'));
        $this->assertNotContains('Theirs', $blueprints->pluck('name'));
        $this->assertSame('project', $blueprints->firstWhere('name', 'Mine')['type']);
        $this->assertTrue($blueprints->firstWhere('key', 'framework/project')['system']);
    }

    /* tests/fixtures/spec.v1.json: a spec the framework R package (c74051f) wrote for a sensitive project */

    /** Sent as raw JSON, as fw does, so empty maps stay {} */
    private function sendSpec(string $token, mixed $spec)
    {
        $body = '{"spec":'.json_encode($spec, JSON_UNESCAPED_SLASHES).'}';

        return $this->call('PUT', '/api/v2/project/spec', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer {$token}"], $body);
    }

    private function rawSpec(): object
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/spec.v1.json')));
    }

    public function test_a_project_token_can_send_its_spec(): void
    {
        $project = $this->project(User::factory()->create());
        $token = $project->issuePullToken();

        $this->sendSpec($token, $this->rawSpec())->assertOk()->assertJson(['spec_version' => 1, 'changed' => true]);
        $this->sendSpec($token, $this->rawSpec())->assertOk()->assertJson(['changed' => false]);

        $project->refresh();
        $this->assertNotNull($project->spec_received_at);
        // Empty maps stay maps
        $this->assertStringContainsString('"storage_buckets":{}', $project->spec);
    }

    public function test_a_spec_with_secrets_or_the_wrong_shape_is_refused(): void
    {
        $project = $this->project(User::factory()->create());
        $token = $project->issuePullToken();

        $spec = $this->rawSpec();
        $spec->env->POSTGRES_PASSWORD = 'hunter2';
        $this->sendSpec($token, $spec)
            ->assertUnprocessable()->assertJsonFragment(['env.POSTGRES_PASSWORD looks like a secret. Leave it empty here and fill it in the project\'s .env.']);

        $spec = $this->rawSpec();
        $spec->connections->databases->framework->password = 'hunter2';
        $this->sendSpec($token, $spec)->assertUnprocessable();

        $spec = $this->rawSpec();
        unset($spec->language);
        $this->sendSpec($token, $spec)->assertUnprocessable();

        $this->sendSpec($token, 'nope')->assertUnprocessable();
        $this->assertNull($project->refresh()->spec);

        // A user token is not a project token
        $this->app['auth']->forgetGuards();
        $this->withToken($project->user->createToken('fw')->plainTextToken)
            ->putJson('/api/v2/project/spec', ['spec' => []])->assertForbidden();
    }
}
