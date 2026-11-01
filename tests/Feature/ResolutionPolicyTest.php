<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\ProjectLayers;
use App\Support\Resolver;
use App\Support\Trust;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The one precedence policy (docs/plans/2026-11-01-workflows-defaults-and-recipes.md):
 * blueprint requirements, then creation choices, then the person's defaults,
 * then the blueprint's own values, the workflow, the structure, and the
 * packages' built-ins.
 */
class ResolutionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    public function test_a_choice_that_conflicts_with_a_requirement_is_refused(): void
    {
        $user = User::factory()->create();

        // Course needs Quarto
        try {
            (new Resolver($user))->resolve(['name' => 'Course', 'blueprint' => 'framework/course', 'tools' => ['quarto' => false]]);
            $this->fail('Turning off a required tool should be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Quarto is required', $e->errors()['tools'][0]);
        }

        // Jupyter's workflow needs its tool
        $this->expectException(ValidationException::class);
        (new Resolver($user))->resolve(['name' => 'Survey', 'language' => 'python', 'tools' => ['jupyter' => false]]);
    }

    public function test_a_default_the_blueprint_cant_take_is_set_aside_with_a_notice(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->patch('/settings', ['project_type' => 'course', 'ide' => 'vscode', 'workflows' => ['python' => 'jupyter']])->assertSessionHasNoErrors();

        // Course has only the Quarto workflow
        $resolved = (new Resolver($user->fresh()))->resolve(['name' => 'Class', 'language' => 'python']);
        $this->assertSame('quarto', $resolved['workflow']);
        $this->assertStringContainsString('your Jupyter default', $resolved['notices'][0]);

        // Course has no Stata, so a Stata project falls back to the standard project
        $stata = (new Resolver($user->fresh()))->resolve(['name' => 'Do', 'language' => 'stata']);
        $this->assertSame('project', $stata['structure']->facet->key);
        $this->assertSame('scripts', $stata['workflow']);
        $this->assertStringContainsString("doesn't support Stata", $stata['notices'][0]);

        // An explicit blueprint that can't take the language is refused
        $this->expectException(ValidationException::class);
        (new Resolver($user->fresh()))->resolve(['name' => 'Do', 'language' => 'stata', 'blueprint' => 'framework/course']);
    }

    public function test_resolve_sends_the_defaults_revision_and_notices_as_headers(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v2/resolve', ['language' => 'stata'])->assertOk();
        $this->assertSame('Untitled project', $first->json('name'));
        $revision = $first->headers->get('Framework-Defaults-Revision');
        $this->assertNotEmpty($revision);
        $this->assertSame($revision, $this->getJson('/api/v2/defaults')->headers->get('Framework-Defaults-Revision'));

        $this->actingAs($user)->patch('/settings', ['project_type' => 'course', 'ide' => 'vscode'])->assertSessionHasNoErrors();
        $second = $this->postJson('/api/v2/resolve', ['language' => 'stata'])->assertOk();
        $this->assertNotSame($revision, $second->headers->get('Framework-Defaults-Revision'));
        $this->assertStringContainsString("doesn't support Stata", $second->headers->get('Framework-Notice'));
    }

    public function test_a_requirement_wins_over_the_persons_default(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->patch('/settings', ['project_type' => 'project', 'ide' => 'vscode', 'use_git' => false])->assertSessionHasNoErrors();

        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();
        $blueprint->latestVersion->update(['requires' => ['git' => ['use_git' => true]]]);

        $resolved = (new Resolver($user->fresh()))->resolve(['name' => 'Survey']);
        $spec = DefaultsPreview::merge($resolved['envelope']['layers']);
        $this->assertTrue($spec['git']['use_git']);
        $this->assertFalse(collect($resolved['envelope']['layers'])->firstWhere('kind', 'settings')['layer']['git']['use_git']);
    }

    public function test_projects_keep_their_configuration_when_defaults_change(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/projects', ['name' => 'Survey', 'blueprint_id' => Blueprint::whereNull('user_id')->where('key', 'project')->value('id')])->assertSessionHasNoErrors();
        $project = $user->projects()->sole();
        $before = ProjectLayers::for($project);

        $this->actingAs($user)->patch('/settings', ['project_type' => 'project', 'ide' => 'rstudio', 'workflows' => ['r' => 'scripts']])->assertSessionHasNoErrors();

        $this->assertSame($before, ProjectLayers::for($project->fresh()));
    }

    public function test_other_peoples_blueprints_cant_run_code_or_set_agents(): void
    {
        $author = User::factory()->create();
        $structure = Facet::whereNull('user_id')->where('key', 'project')->firstOrFail()->latestVersion;
        $blueprint = Blueprint::create(['user_id' => $author->id, 'key' => 'startup', 'name' => 'Startup', 'visibility' => 'public']);
        $blueprint->publish($structure, [], [
            'files' => ['.Rprofile' => ['content' => 'system("curl example.org")'], 'README.md' => ['content' => '# Hi']],
            'ai' => ['assistants' => ['claude' => true]],
        ]);
        $handle = $blueprint->fresh()->handle;

        // The author can use their own
        $own = (new Resolver($author))->resolve(['name' => 'Mine', 'blueprint' => $handle]);
        $this->assertArrayHasKey('.Rprofile', DefaultsPreview::merge($own['envelope']['layers'])['files']);

        // Someone else can't, and nobody's blueprint picks their agents
        $user = User::factory()->create();
        try {
            (new Resolver($user))->resolve(['name' => 'Theirs', 'blueprint' => $handle]);
            $this->fail('A startup file from someone else should be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('.Rprofile', $e->errors()['blueprint'][0]);
        }
        $this->assertFalse(DefaultsPreview::merge($own['envelope']['layers'])['ai']['assistants']['claude'] ?? false);
    }

    public function test_blocked_paths_match_however_they_are_written(): void
    {
        $files = fn (array $paths) => ['files' => array_fill_keys($paths, ['content' => ''])];
        $this->assertSame(
            ['./.Rprofile', '/scaffold.R', '.github/workflows/ci.yml', '.claude/settings.json', '.VSCODE/tasks.json'],
            Trust::blockedFiles($files(['./.Rprofile', '/scaffold.R', '.github/workflows/ci.yml', '.claude/settings.json', '.VSCODE/tasks.json', 'README.md', 'notebooks/scaffold.R.md', '.github/ISSUE_TEMPLATE.md'])),
        );
    }

    public function test_starter_documents_and_skill_sets_combine_with_a_chosen_blueprint(): void
    {
        $user = User::factory()->create(['project_defaults' => ['tools' => ['quarto_manuscript' => true, 'jupyter' => false], 'skill_sets' => ['framework/ggplot2']]]);
        $keys = fn (array $choices) => collect((new Resolver($user))->resolve(['name' => 'S'] + $choices)['pieces'])->pluck('facet.key')->all();

        // Your starter document and skill set come along with a blueprint you name
        $bare = $keys(['blueprint' => 'framework/bare', 'language' => 'r']);
        $this->assertContains('quarto_manuscript', $bare);
        $this->assertContains('ggplot2', $bare);

        // Choices for the project win: its own skill sets replace yours, and a tool turned off stays off
        $chosen = $keys(['blueprint' => 'framework/bare', 'language' => 'r', 'skill_sets' => [], 'tools' => ['quarto_manuscript' => false]]);
        $this->assertNotContains('ggplot2', $chosen);
        $this->assertNotContains('quarto_manuscript', $chosen);

        // Jupyter comes with the Jupyter workflow only; your switch being off doesn't remove it
        $this->assertContains('jupyter', $keys(['language' => 'python', 'workflow' => 'jupyter']));
        foreach (['quarto', 'scripts'] as $workflow) {
            $this->assertNotContains('jupyter', $keys(['language' => 'python', 'workflow' => $workflow]));
        }
        $this->assertNotContains('jupyter', $keys(['language' => 'python', 'blueprint' => 'framework/course']));
    }
}
