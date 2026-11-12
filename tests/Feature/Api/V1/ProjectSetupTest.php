<?php

namespace Tests\Feature\Api\V1;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** pull_all() and push_all(): a project's packages and skills over the API. */
class ProjectSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlueprintSeeder::class);
    }

    private function project(string $language = 'r', ?User $user = null): Project
    {
        $user ??= User::factory()->create();
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();
        $this->actingAs($user)->post('/projects', ['name' => 'Survey Study', 'blueprint_id' => $blueprint->id, 'language' => $language])->assertRedirect();
        $this->app['auth']->forgetGuards();

        return $user->projects()->listed()->firstOrFail();
    }

    private function pulled(string $token): array
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/projects/setup')->assertOk()->json();
    }

    private function push(string $token, array $body): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/projects/setup/packages', $body);
    }

    /** @return array<string, array<string, mixed>> */
    private static function byName(array $packages): array
    {
        return collect($packages)->keyBy('name')->all();
    }

    public function test_get_returns_the_packages_skills_and_revision_for_a_project_token(): void
    {
        $project = $this->project();
        $setup = $this->pulled($project->issuePullToken());

        $this->assertSame(['revision', 'language', 'packages', 'skills'], array_keys($setup));
        $this->assertSame('r', $setup['language']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $setup['revision']);

        $packages = self::byName($setup['packages']);
        $this->assertSame(['name' => 'dplyr', 'source' => 'cran', 'version' => null, 'auto_attach' => true, 'repo' => null], $packages['dplyr']);
        // Removed by a layer: never listed
        $this->assertArrayNotHasKey('lme4', $packages);

        $this->assertNotEmpty($setup['skills']);
        foreach ($setup['skills'] as $skill) {
            $this->assertSame(['key', 'handle', 'version', 'package'], array_slice(array_keys($skill), 0, 4));
            if ($skill['package']) {
                $this->assertSame("framework/{$skill['key']}", $skill['handle']);
            } else {
                $this->assertArrayHasKey('content', $skill);
                $this->assertArrayHasKey('bundle', $skill);
            }
        }

        // The same revision until the setup changes
        $this->assertSame($setup['revision'], $this->pulled($project->issuePullToken())['revision']);
    }

    public function test_a_user_token_names_one_of_its_projects(): void
    {
        $project = $this->project();
        $token = $project->user->createToken('cli')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/projects/setup?project=survey-study')->assertOk()->assertJsonPath('language', 'r');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/projects/setup')->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $other = User::factory()->create()->createToken('cli')->plainTextToken;
        $this->withToken($other)->getJson('/api/v1/projects/setup?project=survey-study')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/projects/setup')->assertUnauthorized();

        $revision = $this->pulled($project->issuePullToken())['revision'];
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/projects/setup/packages?project=survey-study', [
            'base_revision' => $revision, 'packages' => [['name' => 'fixest', 'source' => 'cran', 'version' => null, 'auto_attach' => false, 'repo' => null]],
        ])->assertOk()->assertJsonPath('applied', true);
    }

    public function test_a_dry_run_reports_added_changed_and_kept_without_saving(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $setup = $this->pulled($token);
        $packages = self::byName($setup['packages']);
        $before = $project->fresh()->resolved;

        $desired = array_values(array_filter($setup['packages'], fn ($p) => $p['name'] !== 'tidyr'));
        $desired = array_map(fn ($p) => $p['name'] === 'dplyr' ? ['version' => '1.1.4', 'auto_attach' => false] + $p : $p, $desired);
        $desired[] = ['name' => 'fixest', 'source' => 'cran', 'version' => null, 'auto_attach' => true, 'repo' => null];

        $response = $this->push($token, ['base_revision' => $setup['revision'], 'packages' => $desired, 'dry_run' => true])->assertOk();

        $this->assertFalse($response->json('applied'));
        $this->assertSame($setup['revision'], $response->json('revision'));
        $this->assertSame([['name' => 'fixest', 'source' => 'cran', 'version' => null, 'auto_attach' => true, 'repo' => null]], $response->json('changes.added'));
        $this->assertSame([[
            'name' => 'dplyr',
            'from' => $packages['dplyr'],
            'to' => ['name' => 'dplyr', 'source' => 'cran', 'version' => '1.1.4', 'auto_attach' => false, 'repo' => null],
        ]], $response->json('changes.changed'));
        $this->assertSame([], $response->json('changes.removed'));
        $this->assertSame([$packages['tidyr']], $response->json('changes.kept'));

        $this->assertSame($before, $project->fresh()->resolved);
        $this->assertNull($project->fresh()->setup_updated_at);
    }

    public function test_push_applies_to_the_project_only_and_keeps_missing_packages_without_remove(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $setup = $this->pulled($token);
        $blueprints = BlueprintVersion::orderBy('id')->get()->toArray();
        $sets = FacetVersion::whereHas('facet', fn ($q) => $q->where('kind', 'package_set'))->orderBy('id')->get()->toArray();
        $facets = Facet::orderBy('id')->get()->toArray();

        $desired = array_values(array_filter($setup['packages'], fn ($p) => $p['name'] !== 'tidyr'));
        $desired = array_map(fn ($p) => $p['name'] === 'dplyr' ? ['version' => '1.1.4'] + $p : $p, $desired);
        $desired[] = ['name' => 'fixest', 'source' => 'cran', 'version' => '0.12.1', 'auto_attach' => true, 'repo' => null];
        $desired[] = ['name' => 'mypkg', 'source' => 'github', 'version' => null, 'auto_attach' => false, 'repo' => 'lab/mypkg'];

        $response = $this->push($token, ['base_revision' => $setup['revision'], 'packages' => $desired])->assertOk();

        $this->assertTrue($response->json('applied'));
        $this->assertSame(['fixest', 'mypkg'], array_column($response->json('changes.added'), 'name'));
        $this->assertSame(['dplyr'], array_column($response->json('changes.changed'), 'name'));
        $this->assertSame(['tidyr'], array_column($response->json('changes.kept'), 'name'));
        $this->assertSame([], $response->json('changes.removed'));

        $after = $this->pulled($token);
        $this->assertSame($response->json('revision'), $after['revision']);
        $this->assertNotSame($setup['revision'], $after['revision']);
        $packages = self::byName($after['packages']);
        $this->assertSame('1.1.4', $packages['dplyr']['version']);
        $this->assertSame(['name' => 'fixest', 'source' => 'cran', 'version' => '0.12.1', 'auto_attach' => true, 'repo' => null], $packages['fixest']);
        $this->assertSame('lab/mypkg', $packages['mypkg']['repo']);
        $this->assertArrayHasKey('tidyr', $packages);

        // Stored as the project's choices, with the blueprint and package sets as they were
        $project->refresh();
        $this->assertNotNull($project->setup_updated_at);
        $this->assertSame('0.12.1', $project->choices['package_definitions']['fixest']['version']);
        $this->assertSame($blueprints, BlueprintVersion::orderBy('id')->get()->toArray());
        $this->assertSame($sets, FacetVersion::whereHas('facet', fn ($q) => $q->where('kind', 'package_set'))->orderBy('id')->get()->toArray());
        $this->assertSame($facets, Facet::orderBy('id')->get()->toArray());

        // Pushing the same list again changes nothing
        $again = $this->push($token, ['base_revision' => $after['revision'], 'packages' => $desired])->assertOk();
        $this->assertFalse($again->json('applied'));
        $this->assertSame([], $again->json('changes.added'));
        $this->assertSame([], $again->json('changes.changed'));
    }

    public function test_remove_removes_missing_packages(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $setup = $this->pulled($token);
        $desired = array_values(array_filter($setup['packages'], fn ($p) => ! in_array($p['name'], ['tidyr', 'purrr'], true)));

        $response = $this->push($token, ['base_revision' => $setup['revision'], 'packages' => $desired, 'remove' => true])->assertOk();

        $this->assertTrue($response->json('applied'));
        $this->assertEqualsCanonicalizing(['tidyr', 'purrr'], array_column($response->json('changes.removed'), 'name'));
        $this->assertSame([], $response->json('changes.kept'));
        $names = array_column($this->pulled($token)['packages'], 'name');
        $this->assertNotContains('tidyr', $names);
        $this->assertNotContains('purrr', $names);
        $this->assertContains('dplyr', $names);
    }

    public function test_a_stale_revision_is_refused_unless_forced(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $setup = $this->pulled($token);
        $add = fn (string $name) => [...$setup['packages'], ['name' => $name, 'source' => 'cran', 'version' => null, 'auto_attach' => false, 'repo' => null]];

        $this->push($token, ['base_revision' => $setup['revision'], 'packages' => $add('fixest')])->assertOk();
        $current = $this->pulled($token)['revision'];

        $this->push($token, ['base_revision' => $setup['revision'], 'packages' => $add('broom')])
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'The project\'s packages changed on Framework Cloud since you last pulled. Pull first with pull_all() or fw pull, or push with force.',
                'revision' => $current,
            ]);
        $this->push($token, ['base_revision' => $setup['revision'], 'packages' => $add('broom'), 'dry_run' => true])->assertStatus(409);
        $this->push($token, ['packages' => $add('broom')])->assertStatus(409);
        $this->assertArrayNotHasKey('broom', self::byName($this->pulled($token)['packages']));

        $forced = $this->push($token, ['base_revision' => $setup['revision'], 'packages' => $add('broom'), 'force' => true])->assertOk();
        $this->assertTrue($forced->json('applied'));
        $this->assertSame(['broom'], array_column($forced->json('changes.added'), 'name'));
        // fixest, pushed in between, is kept, not removed
        $this->assertSame(['fixest'], array_column($forced->json('changes.kept'), 'name'));
        $this->assertArrayHasKey('broom', self::byName($this->pulled($token)['packages']));
    }

    public function test_package_entries_are_validated(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $revision = $this->pulled($token)['revision'];
        $push = fn (array $package) => $this->push($token, ['base_revision' => $revision, 'packages' => [$package + ['source' => 'cran', 'version' => null, 'auto_attach' => false, 'repo' => null]]]);

        $push(['name' => 'bad name'])->assertStatus(422);
        $push(['name' => 'dplyr', 'source' => 'conda'])->assertStatus(422);
        $push(['name' => 'dplyr', 'source' => 'pypi'])->assertStatus(422);
        $push(['name' => 'mypkg', 'source' => 'github'])->assertStatus(422);
        $push(['name' => 'mypkg', 'source' => 'github', 'repo' => 'not a repo'])->assertStatus(422);
        $push(['name' => 'dplyr', 'version' => '1 2'])->assertStatus(422);
        $this->push($token, ['base_revision' => $revision, 'packages' => [['name' => 'a'], ['name' => 'a']]])->assertStatus(422);
        $this->assertNull($project->fresh()->setup_updated_at);
    }

    public function test_python_projects_take_pypi_packages_and_refuse_auto_attach(): void
    {
        $project = $this->project('python');
        $token = $project->issuePullToken();
        $setup = $this->pulled($token);
        $this->assertSame('python', $setup['language']);

        $this->push($token, ['base_revision' => $setup['revision'], 'packages' => [...$setup['packages'], ['name' => 'polars', 'source' => 'pypi', 'version' => '>=1.0', 'auto_attach' => true, 'repo' => null]]])
            ->assertStatus(422)->assertJsonFragment(['Auto-attach is available for R only. Python and Stata do not auto-import packages.']);

        $this->push($token, ['base_revision' => $setup['revision'], 'packages' => [...$setup['packages'], ['name' => 'polars', 'source' => 'pypi', 'version' => '>=1.0', 'auto_attach' => false, 'repo' => null]]])
            ->assertOk()->assertJsonPath('applied', true);
        $this->assertSame(['name' => 'polars', 'source' => 'pypi', 'version' => '>=1.0', 'auto_attach' => false, 'repo' => null], self::byName($this->pulled($token)['packages'])['polars']);
    }

    public function test_stata_projects_have_no_package_list(): void
    {
        $project = $this->project('stata');
        $token = $project->issuePullToken();
        $setup = $this->pulled($token);

        $this->push($token, ['base_revision' => $setup['revision'], 'packages' => []])->assertStatus(422);
    }

    public function test_a_push_offers_update_on_github_and_survives_edit_project(): void
    {
        $user = User::factory()->create();
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();
        Http::fake(['api.github.com/user/installations*' => Http::response(['installations' => []])]);
        $project = $this->project('r', $user);
        $project->update(['github_repo' => 'lab/survey-study', 'scaffold_status' => 'done']);
        $token = $project->issuePullToken();

        $this->actingAs($user)->get("/projects/{$project->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.github.update_available', false));

        $setup = $this->pulled($token);
        $this->push($token, ['base_revision' => $setup['revision'], 'packages' => [...$setup['packages'], ['name' => 'fixest', 'source' => 'cran', 'version' => '0.12.1', 'auto_attach' => false, 'repo' => null]]])
            ->assertOk();

        $this->actingAs($user)->get("/projects/{$project->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.github.update_available', true));

        // The edit page opens on the pushed packages, and saving the form with them keeps them
        $this->actingAs($user)->get("/projects/{$project->slug}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('editing.choices.package_definitions.fixest.version', '0.12.1'));
        $this->actingAs($user)->put("/projects/{$project->id}", [
            'name' => 'Survey Study', 'blueprint_id' => Blueprint::whereNull('user_id')->where('key', 'project')->value('id'),
            'language' => 'r', 'workflow' => 'scripts', 'update_setup' => true,
            'package_definitions' => $project->fresh()->choices['package_definitions'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.12.1', self::byName($this->pulled($token)['packages'])['fixest']['version']);
        $this->assertTrue(collect(ProjectLayers::for($project->fresh())['layers'])->contains(fn ($l) => isset($l['layer']['packages']['default_packages']['fixest'])));
    }
}
