<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use App\Services\GitHub\Scaffolder;
use App\Support\Layer;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class GitHubTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = '28e0ca338ed329d9711fdb106348a666a68f7c6e';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.client_id' => 'Iv1.test',
            'services.github.client_secret' => 'secret',
            'services.github.app_slug' => 'framework-pub',
            'services.github.webhook_secret' => 'hook-secret',
            'services.github.framework_sha' => self::SHA,
        ]);

        $this->seed(BlueprintSeeder::class);
    }

    private function fakeSocialite(int $id, string $login, ?string $email): void
    {
        $github = (new SocialiteUser)->map(['id' => $id, 'nickname' => $login, 'name' => 'Ada Lovelace', 'email' => $email]);
        $github->setToken('ghu_access')->setRefreshToken('ghr_refresh')->setExpiresIn(28800);

        $provider = Mockery::mock(GithubProvider::class);
        $provider->shouldReceive('user')->andReturn($github);
        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
    }

    private function connected(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();

        return $user->fresh();
    }

    private function fakeGitHub(string $ownerType = 'User'): void
    {
        Http::fake([
            'api.github.com/user/installations*' => Http::response(['installations' => [
                ['id' => 1, 'account' => ['login' => $ownerType === 'User' ? 'ada' : 'lab', 'type' => $ownerType], 'repository_selection' => 'all'],
            ]]),
            'api.github.com/user/repos' => Http::response(['id' => 900, 'full_name' => 'ada/nhanes-study', 'private' => true, 'default_branch' => 'main'], 201),
            'api.github.com/orgs/lab/repos' => Http::response(['id' => 901, 'full_name' => 'lab/nhanes-study', 'private' => true, 'default_branch' => 'main'], 201),
            'api.github.com/repos/*/contents/*' => function (HttpRequest $request) {
                return $request->method() === 'GET'
                    ? Http::response(['message' => 'Not Found'], 404)
                    : Http::response(['content' => ['sha' => 'abc']], 201);
            },
        ]);
    }

    public function test_signing_in_with_github_creates_a_verified_account(): void
    {
        $this->fakeSocialite(42, 'ada', 'Ada@Example.org');

        $this->get('/auth/github/callback?code=x&state=y')->assertRedirect('/dashboard');

        $user = User::where('email', 'ada@example.org')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('ada', $user->githubAccount->login);
        $this->assertSame('ghu_access', $user->githubAccount->access_token);
        $this->assertNotSame('ghu_access', GitHubAccount::query()->toBase()->value('access_token'), 'tokens are encrypted at rest');
    }

    public function test_github_sign_in_matches_an_existing_account_by_email(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.org']);
        $this->fakeSocialite(42, 'ada', 'ada@example.org');

        $this->get('/auth/github/callback?code=x&state=y')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame(42, (int) $user->githubAccount->github_id);
    }

    public function test_github_sign_in_without_a_verified_email_is_refused(): void
    {
        $this->fakeSocialite(42, 'ada', null);

        $this->get('/auth/github/callback?code=x&state=y')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_a_github_account_connects_to_only_one_framework_account(): void
    {
        $this->connected(['email' => 'ada@example.org']);
        $other = User::factory()->create();
        $this->fakeSocialite(42, 'ada', 'ada@example.org');

        $this->actingAs($other)->get('/auth/github/callback?code=x&state=y')
            ->assertRedirect('/settings/github')->assertSessionHasErrors('github');
        $this->assertNull($other->fresh()->githubAccount);
    }

    public function test_the_layers_envelope_matches_the_pinned_schema(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.org']);
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();
        $project = $user->projects()->create([
            'name' => 'NHANES Study', 'slug' => 'nhanes-study', 'project_type' => 'project',
            'blueprint_version_id' => $blueprint->latestVersion->id,
        ]);

        $envelope = ProjectLayers::for($project);

        $this->assertSame([], Layer::envelopeProblems($envelope));
        $this->assertSame(['base', 'blueprint', 'project'], array_column($envelope['layers'], 'kind'));
        $this->assertSame('framework/project', $envelope['source']['blueprint']['key']);
        $this->assertSame(['name' => 'Ada Lovelace', 'email' => 'ada@example.org', 'affiliation' => ''], $envelope['author']);

        // Empty maps stay objects in the committed JSON
        $this->assertStringContainsString('"layer": {}', ProjectLayers::toJson($envelope));
    }

    public function test_sensitive_projects_leave_provenance_out(): void
    {
        $user = User::factory()->create();
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project_sensitive')->firstOrFail();
        $project = $user->projects()->create([
            'name' => 'Clinic', 'slug' => 'clinic', 'project_type' => 'project_sensitive',
            'blueprint_version_id' => $blueprint->latestVersion->id,
        ]);

        $this->assertArrayNotHasKey('source', ProjectLayers::for($project));
    }

    public function test_the_envelope_check_rejects_a_secret_in_any_layer(): void
    {
        $envelope = [
            'spec_version' => 1,
            'name' => 'X',
            'layers' => [['kind' => 'project', 'layer' => ['env' => ['API_TOKEN' => 'abc123']]]],
        ];

        $this->assertNotEmpty(Layer::envelopeProblems($envelope));
    }

    public function test_creating_a_project_on_github_commits_layers_then_the_workflow(): void
    {
        $user = $this->connected();
        $this->fakeGitHub();
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();

        $this->actingAs($user)->post('/projects', [
            'name' => 'NHANES Study',
            'blueprint_id' => $blueprint->id,
            'github' => ['enabled' => true, 'owner' => 'ada', 'name' => 'nhanes-study', 'private' => true],
        ])->assertRedirect('/projects')->assertSessionHasNoErrors();

        $project = Project::firstOrFail();
        $this->assertSame('ada/nhanes-study', $project->github_repo);
        $this->assertSame('running', $project->scaffold_status);

        $puts = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn ($r) => $r->method() === 'PUT')->values();
        $this->assertCount(2, $puts);
        $this->assertStringEndsWith('/contents/.framework/layers.json', $puts[0]->url());
        $this->assertStringEndsWith('/contents/.github/workflows/framework-scaffold.yml', $puts[1]->url());

        $workflow = base64_decode($puts[1]['content']);
        $this->assertStringContainsString('frameworktools/framework@'.self::SHA, $workflow);
        $this->assertStringContainsString('project_create_from_layers(".framework/layers.json"', $workflow);

        $layers = json_decode(base64_decode($puts[0]['content']), true);
        $this->assertSame('NHANES Study', $layers['name']);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.github.com/user/repos'
            && $r['private'] === true && $r['auto_init'] === true
            && $r->hasHeader('Authorization', 'Bearer ghu_access'));
    }

    public function test_organization_repositories_go_to_the_org_endpoint(): void
    {
        $user = $this->connected();
        $this->fakeGitHub('Organization');
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();

        $this->actingAs($user)->post('/projects', [
            'name' => 'NHANES Study',
            'blueprint_id' => $blueprint->id,
            'github' => ['enabled' => true, 'owner' => 'lab', 'name' => 'nhanes-study', 'private' => true],
        ])->assertSessionHasNoErrors();

        $this->assertSame('lab/nhanes-study', Project::firstOrFail()->github_repo);
    }

    public function test_sensitive_projects_cannot_get_public_repositories(): void
    {
        $user = $this->connected();
        $this->fakeGitHub();
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project_sensitive')->firstOrFail();

        $this->actingAs($user)->post('/projects', [
            'name' => 'Clinic',
            'blueprint_id' => $blueprint->id,
            'github' => ['enabled' => true, 'owner' => 'ada', 'name' => 'clinic', 'private' => false],
        ])->assertSessionHasErrors('github.private');

        $this->assertSame(0, Project::count());
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/user/repos'));
    }

    public function test_owners_must_have_the_app_installed(): void
    {
        $user = $this->connected();
        $this->fakeGitHub();
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();

        $this->actingAs($user)->post('/projects', [
            'name' => 'NHANES Study',
            'blueprint_id' => $blueprint->id,
            'github' => ['enabled' => true, 'owner' => 'someone-else', 'name' => 'x', 'private' => true],
        ])->assertSessionHasErrors('github.owner');
    }

    public function test_github_scaffolding_is_off_without_a_pinned_framework_commit(): void
    {
        config(['services.github.framework_sha' => null]);
        $user = $this->connected();
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();

        $this->assertFalse(Scaffolder::enabled());
        $this->actingAs($user)->post('/projects', [
            'name' => 'NHANES Study',
            'blueprint_id' => $blueprint->id,
            'github' => ['enabled' => true, 'owner' => 'ada', 'name' => 'x', 'private' => true],
        ])->assertSessionHasErrors('github.enabled');
    }

    public function test_the_webhook_records_success_and_removes_the_workflow(): void
    {
        $user = $this->connected();
        $project = $user->projects()->create([
            'name' => 'S', 'slug' => 's', 'github_repo' => 'ada/s', 'github_repo_id' => 900, 'scaffold_status' => 'running',
        ]);

        Http::fake([
            'api.github.com/repos/ada/s/contents/*' => function (HttpRequest $request) {
                return $request->method() === 'GET'
                    ? Http::response(['type' => 'file', 'sha' => 'wf-sha', 'size' => 10, 'content' => ''])
                    : Http::response([], 200);
            },
        ]);

        $payload = json_encode([
            'action' => 'completed',
            'workflow_run' => ['path' => Scaffolder::WORKFLOW_PATH, 'conclusion' => 'success', 'html_url' => 'https://github.com/ada/s/actions/runs/1'],
            'repository' => ['id' => 900],
        ]);

        // Unsigned deliveries are rejected
        $this->call('POST', '/api/github/webhook', [], [], [], ['HTTP_X_GITHUB_EVENT' => 'workflow_run', 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertUnauthorized();

        $this->call('POST', '/api/github/webhook', [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'workflow_run',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'hook-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertNoContent();

        $project->refresh();
        $this->assertSame('done', $project->scaffold_status);
        $this->assertSame('https://github.com/ada/s/actions/runs/1', $project->scaffold_run_url);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/contents/.github/workflows/framework-scaffold.yml') && $r['sha'] === 'wf-sha');
    }

    public function test_revoking_the_app_forgets_the_tokens(): void
    {
        $user = $this->connected();
        $payload = json_encode(['action' => 'revoked', 'sender' => ['id' => 42]]);

        $this->call('POST', '/api/github/webhook', [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'github_app_authorization',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'hook-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertNoContent();

        $this->assertNull($user->fresh()->githubAccount);
    }

    public function test_expired_tokens_are_refreshed_before_use(): void
    {
        $user = $this->connected();
        $user->githubAccount->update(['token_expires_at' => now()->subMinute()]);

        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'ghu_new', 'refresh_token' => 'ghr_new', 'expires_in' => 28800, 'refresh_token_expires_in' => 15897600,
            ]),
            'api.github.com/user/installations*' => Http::response(['installations' => []]),
        ]);

        $this->actingAs($user)->get('/settings/github')->assertOk();

        $this->assertSame('ghu_new', $user->githubAccount->fresh()->access_token);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/user/installations') && $r->hasHeader('Authorization', 'Bearer ghu_new'));
    }

    public function test_a_public_skill_folder_imports_without_a_connection(): void
    {
        $user = User::factory()->create();
        $skill = "---\nname: tidy-tables\ndescription: Use when cleaning tables\n---\n\n# Tidy tables\n";

        Http::fake([
            'api.github.com/repos/someone/skills/contents/skills/tidy-tables/SKILL.md*' => Http::response(['type' => 'file', 'sha' => 's', 'size' => strlen($skill), 'content' => base64_encode($skill)]),
            'api.github.com/repos/someone/skills/contents/skills/tidy-tables/notes.md*' => Http::response(['type' => 'file', 'sha' => 'n', 'size' => 5, 'content' => base64_encode('notes')]),
            'api.github.com/repos/someone/skills/contents/skills/tidy-tables/logo.png*' => Http::response(['type' => 'file', 'sha' => 'p', 'size' => 4, 'content' => base64_encode("\x89PNG\xff")]),
            'api.github.com/repos/someone/skills/contents/skills/tidy-tables*' => Http::response([
                ['name' => 'SKILL.md', 'path' => 'skills/tidy-tables/SKILL.md', 'type' => 'file', 'size' => strlen($skill)],
                ['name' => 'notes.md', 'path' => 'skills/tidy-tables/notes.md', 'type' => 'file', 'size' => 5],
                ['name' => 'logo.png', 'path' => 'skills/tidy-tables/logo.png', 'type' => 'file', 'size' => 4],
            ]),
        ]);

        $url = 'https://github.com/someone/skills/tree/main/skills/tidy-tables';
        $this->actingAs($user)->post('/skills/import', ['url' => $url])->assertSessionHasNoErrors()->assertRedirect();

        $imported = $user->hasMany(Skill::class)->firstOrFail();
        $this->assertSame('tidy-tables', $imported->key);
        $this->assertSame('private', $imported->visibility);
        $this->assertSame($url, $imported->source_url);
        $this->assertSame(['notes.md' => 'notes'], $imported->latestVersion->files);
        $this->assertStringContainsString('skipped logo.png', $imported->latestVersion->notes);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'ref=main') && ! $r->hasHeader('Authorization'));
    }

    public function test_an_imported_facet_goes_through_layer_validation(): void
    {
        $user = User::factory()->create();
        $bad = "framework_facet: 1\nname: Leaky\nlayer:\n  env:\n    API_TOKEN: abc123\n";
        $good = "framework_facet: 1\nname: Lab folders\ncategory: data\nlayer:\n  extra_directories:\n    lab:\n      path: lab\n";

        Http::fake([
            'api.github.com/repos/lab/library/contents/facets/leaky.facet.yml*' => Http::response(['type' => 'file', 'sha' => 'a', 'size' => 1, 'content' => base64_encode($bad)]),
            'api.github.com/repos/lab/library/contents/facets/lab.facet.yml*' => Http::response(['type' => 'file', 'sha' => 'b', 'size' => 1, 'content' => base64_encode($good)]),
        ]);

        $this->actingAs($user)->post('/facets/import', ['url' => 'https://github.com/lab/library/blob/main/facets/leaky.facet.yml'])
            ->assertSessionHasErrors('url');

        $this->actingAs($user)->post('/facets/import', ['url' => 'https://github.com/lab/library/blob/main/facets/lab.facet.yml'])
            ->assertSessionHasNoErrors();

        $facet = Facet::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Lab folders', $facet->name);
        $this->assertSame('data', $facet->category);
        $this->assertSame(['lab' => ['path' => 'lab']], $facet->latestVersion->layer['extra_directories']);
    }

    public function test_publishing_a_facet_writes_a_facet_file_that_imports_back(): void
    {
        $user = $this->connected();
        $facet = Facet::whereNull('user_id')->where('key', 'renv')->firstOrFail();
        $written = null;

        Http::fake([
            'api.github.com/repos/ada/library/contents/*' => function (HttpRequest $request) use (&$written) {
                if ($request->method() === 'PUT') {
                    $written = base64_decode($request['content']);

                    return Http::response(['content' => ['sha' => 'x']], 201);
                }

                return Http::response(['message' => 'Not Found'], 404);
            },
        ]);

        $this->actingAs($user)->post("/facets/{$facet->id}/publish", ['repo' => 'ada/library'])
            ->assertSessionHasNoErrors()->assertSessionHas('url', 'https://github.com/ada/library/blob/HEAD/facets/renv.facet.yml');

        $this->assertStringContainsString('framework_facet: 1', $written);
        $this->assertSame(['packages' => ['use_renv' => true]], Yaml::parse($written)['layer']);
    }

    public function test_publishing_requires_a_github_connection(): void
    {
        $user = User::factory()->create();
        $skill = Skill::whereNull('user_id')->firstOrFail();

        $this->actingAs($user)->post("/skills/{$skill->id}/publish", ['repo' => 'ada/library'])->assertSessionHasErrors('github');
    }
}
