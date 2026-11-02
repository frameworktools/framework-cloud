<?php

namespace Tests\Feature\Api\V2;

use App\Jobs\ScaffoldOnGitHub;
use App\Models\GitHubAccount;
use App\Models\User;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

/** What fw new needs to hand over a project as a download or on GitHub. */
class NewProjectDestinationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.client_id' => 'Iv1.test',
            'services.github.app_slug' => 'framework-pub',
            'services.github.framework_sha' => str_repeat('a', 40),
            'services.github.framework_py_sha' => str_repeat('b', 40),
            'services.scaffolder.url' => 'http://scaffolder:8000',
        ]);
        $this->seed(BlueprintSeeder::class);
    }

    private function connected(): User
    {
        $user = User::factory()->create();
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();

        return $user->fresh();
    }

    private function fakeInstallations(): void
    {
        Http::fake([
            'api.github.com/user/installations*' => Http::response(['installations' => [
                ['id' => 1, 'account' => ['login' => 'ada', 'type' => 'User'], 'repository_selection' => 'all'],
                ['id' => 2, 'account' => ['login' => 'lab', 'type' => 'Organization'], 'repository_selection' => 'all'],
            ]]),
        ]);
    }

    public function test_destinations_say_what_this_server_and_account_can_do(): void
    {
        $this->fakeInstallations();
        $token = $this->connected()->createToken('fw')->plainTextToken;

        $this->withToken($token)->getJson('/api/v2/destinations')->assertOk()
            ->assertJsonPath('download.available', true)
            ->assertJsonPath('github.available', true)
            ->assertJsonPath('github.login', 'ada')
            ->assertJsonPath('github.owners', [['login' => 'ada', 'type' => 'User'], ['login' => 'lab', 'type' => 'Organization']])
            ->assertJsonPath('github.unavailable', []);

        config(['services.scaffolder.url' => null, 'services.github.framework_py_sha' => null]);
        $this->withToken($token)->getJson('/api/v2/destinations')->assertOk()
            ->assertJsonPath('download.available', false)
            ->assertJsonPath('github.unavailable.python', 'This server is not set up to scaffold Python projects on GitHub yet.');
    }

    public function test_destinations_without_github_connected(): void
    {
        $token = User::factory()->create()->createToken('fw')->plainTextToken;

        $this->withToken($token)->getJson('/api/v2/destinations')->assertOk()
            ->assertJsonPath('github.login', null)
            ->assertJsonPath('github.owners', []);
    }

    public function test_a_project_created_with_github_options_is_scaffolded_there(): void
    {
        Queue::fake();
        $this->fakeInstallations();
        $user = $this->connected();

        $this->withToken($user->createToken('fw')->plainTextToken)->postJson('/api/v2/projects', [
            'name' => 'Clinic Visits', 'blueprint' => 'framework/project', 'language' => 'python',
            'github' => ['owner' => 'lab'],
        ])->assertCreated()
            ->assertJsonPath('github.repository', 'lab/clinic-visits')
            ->assertJsonPath('github.scaffold_status', 'pending');

        $this->assertSame('pending', $user->projects()->firstOrFail()->scaffold_status);
        Queue::assertPushed(ScaffoldOnGitHub::class, fn (ScaffoldOnGitHub $job) => $job->org === 'lab'
            && $job->repoName === 'clinic-visits' && $job->private === true);
    }

    public function test_github_options_are_checked_before_anything_is_created(): void
    {
        Queue::fake();
        $this->fakeInstallations();
        $user = $this->connected();
        $token = $user->createToken('fw')->plainTextToken;

        $this->withToken($token)->postJson('/api/v2/projects', [
            'name' => 'Clinic Visits', 'blueprint' => 'framework/project', 'github' => ['owner' => 'someone-else'],
        ])->assertUnprocessable()->assertJsonValidationErrors('github.owner');

        $this->withToken($token)->postJson('/api/v2/projects', [
            'name' => 'Clinic Visits', 'blueprint' => 'framework/project_sensitive', 'github' => ['owner' => 'ada', 'private' => false],
        ])->assertUnprocessable()->assertJsonValidationErrors('github.private');

        config(['services.github.fw_version' => null]);
        $this->withToken($token)->postJson('/api/v2/projects', [
            'name' => 'Clinic Visits', 'blueprint' => 'framework/project', 'language' => 'stata', 'github' => ['owner' => 'ada'],
        ])->assertUnprocessable()->assertJsonValidationErrors('github');

        $this->assertSame(0, $user->projects()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_project_downloads_as_a_zip(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('fw')->plainTextToken;
        $this->withToken($token)->postJson('/api/v2/projects', ['name' => 'Clinic Visits', 'blueprint' => 'framework/project'])->assertCreated();

        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('clinic-visits/settings.yml', "default:\n");
        $zip->close();
        Http::fake(['scaffolder:8000/build' => Http::sequence()
            ->push(file_get_contents($path), 200, ['Content-Type' => 'application/zip'])
            ->push(['error' => 'The project could not be built.'], 422)]);
        unlink($path);

        $this->withToken($token)->get('/api/v2/projects/clinic-visits/download')
            ->assertOk()->assertDownload('clinic-visits.zip');

        $this->withToken($token)->getJson('/api/v2/projects/clinic-visits/download')
            ->assertStatus(502)->assertJsonPath('message', 'The project could not be built. The details are in the server log.');

        config(['services.scaffolder.url' => null]);
        $this->withToken($token)->getJson('/api/v2/projects/clinic-visits/download')->assertNotFound();
        $this->withToken($token)->getJson('/api/v2/projects/someone-elses/download')->assertNotFound();
    }

    public function test_project_tokens_cannot_download(): void
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('fw')->plainTextToken)->postJson('/api/v2/projects', ['name' => 'Clinic Visits', 'blueprint' => 'framework/project'])->assertCreated();
        $pull = $user->projects()->firstOrFail()->issuePullToken();
        $this->app['auth']->forgetGuards();

        $this->withToken($pull)->getJson('/api/v2/projects/clinic-visits/download')->assertForbidden();
    }
}
