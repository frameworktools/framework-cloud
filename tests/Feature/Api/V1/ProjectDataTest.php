<?php

namespace Tests\Feature\Api\V1;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** pull_data_registry() and push_data_registry(): a project's data catalog over the API. */
class ProjectDataTest extends TestCase
{
    use RefreshDatabase;

    private const SURVEY = ['name' => 'inputs.raw.survey', 'path' => 'inputs/raw/survey.csv', 'type' => 'csv', 'delimiter' => null, 'locked' => true, 'private' => null, 'description' => null, 'stata_names' => null];

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

        return $this->withToken($token)->getJson('/api/v1/projects/data')->assertOk()->json();
    }

    private function push(string $token, array $body): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/projects/data', $body);
    }

    public function test_a_new_project_has_an_empty_registry_with_a_revision(): void
    {
        $project = $this->project();
        $registry = $this->pulled($project->issuePullToken());

        $this->assertSame(['revision', 'entries'], array_keys($registry));
        $this->assertSame([], $registry['entries']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $registry['revision']);
    }

    public function test_push_adds_entries_that_flow_into_the_layers_and_pull(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $revision = $this->pulled($token)['revision'];
        $visits = ['name' => 'inputs.raw.visits', 'path' => 'inputs/raw/visits.csv', 'stata_names' => ['reason_ecmoref_any_cardiac' => 'reason_cardiac']];

        $reply = $this->push($token, ['base_revision' => $revision, 'entries' => [self::SURVEY, $visits]])->assertOk()->json();

        $this->assertTrue($reply['applied']);
        $this->assertNotSame($revision, $reply['revision']);
        $this->assertSame(['inputs.raw.survey', 'inputs.raw.visits'], array_column($reply['changes']['added'], 'name'));

        $registry = $this->pulled($token);
        $this->assertSame($reply['revision'], $registry['revision']);
        $this->assertSame(self::SURVEY, $registry['entries'][0]);
        $this->assertSame(['reason_ecmoref_any_cardiac' => 'reason_cardiac'], $registry['entries'][1]['stata_names']);
        $this->assertFalse($registry['entries'][1]['locked']);

        // The project's own layer carries the entries by name, as clients create projects from them
        $layers = ProjectLayers::for($project->fresh())['layers'];
        $data = collect($layers)->pluck('layer.data')->filter()->last();
        $this->assertSame(['path' => 'inputs/raw/survey.csv', 'type' => 'csv', 'locked' => true], $data['inputs.raw.survey']);
        // Never a hash
        $this->assertStringNotContainsString('hash', json_encode($data));
    }

    public function test_a_dry_run_reports_changes_without_saving_and_missing_entries_are_kept(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $this->push($token, ['base_revision' => $this->pulled($token)['revision'], 'entries' => [self::SURVEY, ['name' => 'inputs.raw.visits', 'path' => 'inputs/raw/visits.csv']]])->assertOk();
        $registry = $this->pulled($token);
        $before = $project->fresh()->resolved;

        $changed = ['path' => 'inputs/raw/survey_v2.csv'] + self::SURVEY;
        $reply = $this->push($token, ['base_revision' => $registry['revision'], 'entries' => [$changed], 'dry_run' => true])->assertOk()->json();

        $this->assertFalse($reply['applied']);
        $this->assertSame($registry['revision'], $reply['revision']);
        $this->assertSame([], $reply['changes']['added']);
        $this->assertSame('inputs/raw/survey.csv', $reply['changes']['changed'][0]['from']['path']);
        $this->assertSame('inputs/raw/survey_v2.csv', $reply['changes']['changed'][0]['to']['path']);
        $this->assertSame(['inputs.raw.visits'], array_column($reply['changes']['kept'], 'name'));
        $this->assertSame([], $reply['changes']['removed']);
        $this->assertSame($before, $project->fresh()->resolved);

        // Applied: the missing entry stays
        $this->push($token, ['base_revision' => $registry['revision'], 'entries' => [$changed]])->assertOk()->assertJsonPath('applied', true);
        $this->assertSame(['inputs.raw.survey', 'inputs.raw.visits'], array_column($this->pulled($token)['entries'], 'name'));
    }

    public function test_remove_removes_missing_entries(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $this->push($token, ['base_revision' => $this->pulled($token)['revision'], 'entries' => [self::SURVEY, ['name' => 'inputs.raw.visits', 'path' => 'inputs/raw/visits.csv']]])->assertOk();

        $reply = $this->push($token, ['base_revision' => $this->pulled($token)['revision'], 'entries' => [self::SURVEY], 'remove' => true])->assertOk()->json();

        $this->assertSame(['inputs.raw.visits'], array_column($reply['changes']['removed'], 'name'));
        $this->assertSame(['inputs.raw.survey'], array_column($this->pulled($token)['entries'], 'name'));
    }

    public function test_a_stale_revision_is_refused_unless_forced(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $stale = $this->pulled($token)['revision'];
        $this->push($token, ['base_revision' => $stale, 'entries' => [self::SURVEY]])->assertOk();
        $current = $this->pulled($token)['revision'];

        $this->push($token, ['base_revision' => $stale, 'entries' => []])
            ->assertStatus(409)
            ->assertJsonPath('revision', $current)
            ->assertJsonPath('message', 'The project\'s data registry changed on Framework Cloud since you last pulled. Pull first with pull_all() or fw pull, or push with force.');

        $this->push($token, ['base_revision' => $stale, 'entries' => [], 'remove' => true, 'force' => true])->assertOk()->assertJsonPath('applied', true);
        $this->assertSame([], $this->pulled($token)['entries']);
    }

    public function test_entries_are_validated_as_the_spec_checks_them(): void
    {
        $project = $this->project();
        $token = $project->issuePullToken();
        $revision = $this->pulled($token)['revision'];
        $push = fn (array $entry) => $this->push($token, ['base_revision' => $revision, 'entries' => [$entry]]);

        $push(['name' => 'inputs..survey', 'path' => 'a.csv'])->assertUnprocessable()->assertJsonValidationErrors('entries.0.name');
        $push(['name' => 'inputs.raw.survey', 'path' => 'a.csv', 'type' => 'word'])->assertUnprocessable()->assertJsonValidationErrors('entries.0.type');
        $push(['name' => 'inputs.raw.survey', 'path' => 'a.csv', 'delimiter' => 'pipe'])->assertUnprocessable()->assertJsonValidationErrors('entries.0.delimiter');
        $push(['name' => 'inputs.raw.survey'])->assertUnprocessable()->assertJsonValidationErrors('entries.0.path');
        $push(['name' => 'inputs.raw.survey', 'path' => '../outside.csv'])->assertUnprocessable()
            ->assertJsonPath('message', '"../outside.csv" needs a relative path inside the project.');
        $push(['name' => 'inputs.raw.survey', 'path' => '/abs/survey.csv'])->assertUnprocessable()->assertJsonValidationErrors('entries');
        $this->push($token, ['base_revision' => $revision, 'entries' => [
            ['name' => 'inputs.raw', 'path' => 'inputs/raw.csv'], ['name' => 'inputs.raw.survey', 'path' => 'a.csv'],
        ]])->assertUnprocessable()->assertJsonPath('message', 'Data entry "inputs.raw" is an entry, so "inputs.raw.survey" can\'t be inside it.');

        $this->assertSame([], $this->pulled($token)['entries']);
    }

    public function test_a_user_token_names_one_of_its_projects_and_stata_projects_have_a_registry(): void
    {
        $project = $this->project('stata');
        $token = $project->user->createToken('cli')->plainTextToken;

        $revision = $this->withToken($token)->getJson('/api/v1/projects/data?project=survey-study')->assertOk()->json('revision');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/projects/data')->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/projects/data')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/projects/data?project=survey-study', ['base_revision' => $revision, 'entries' => [self::SURVEY]])
            ->assertOk()->assertJsonPath('applied', true);
    }

    public function test_the_registry_survives_edit_project_and_a_setup_push(): void
    {
        $user = User::factory()->create();
        $project = $this->project('r', $user);
        $token = $project->issuePullToken();
        $this->push($token, ['base_revision' => $this->pulled($token)['revision'], 'entries' => [self::SURVEY]])->assertOk();

        $this->actingAs($user)->put("/projects/{$project->id}", [
            'name' => 'Survey Study', 'blueprint_id' => Blueprint::whereNull('user_id')->where('key', 'project')->value('id'),
            'language' => 'r', 'workflow' => 'scripts', 'update_setup' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame([self::SURVEY], $this->pulled($token)['entries']);

        $this->app['auth']->forgetGuards();
        $setup = $this->withToken($token)->getJson('/api/v1/projects/setup')->json();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/projects/setup/packages', ['base_revision' => $setup['revision'], 'packages' => [...$setup['packages'], ['name' => 'fixest', 'source' => 'cran']]])->assertOk();
        $this->assertSame([self::SURVEY], $this->pulled($token)['entries']);

        // The Overview lists the registry
        $this->actingAs($user)->get("/projects/{$project->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.data_registry.0.name', 'inputs.raw.survey')->where('project.data_registry.0.locked', true));
    }
}
