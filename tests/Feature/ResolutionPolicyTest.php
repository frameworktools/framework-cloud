<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\ProjectDefaults;
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

    public function test_each_language_takes_a_default_blueprint_that_fits_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        // The project type saved before per-language defaults
        $setting = $user->globalSettings();
        $doc = $setting->document;
        $doc['defaults']['project_type'] = 'course';
        $setting->replaceDocument($doc);

        // Course has only the Quarto workflow
        $resolved = (new Resolver($user->fresh()))->resolve(['name' => 'Class', 'language' => 'python']);
        $this->assertSame('course', $resolved['structure']->facet->key);
        $this->assertSame('quarto', $resolved['workflow']);

        // Course has no Stata, so Stata projects use the standard project
        $stata = (new Resolver($user->fresh()))->resolve(['name' => 'Do', 'language' => 'stata']);
        $this->assertSame('project', $stata['structure']->facet->key);
        $this->assertSame('scripts', $stata['workflow']);
        $this->assertSame('framework/project', ProjectDefaults::for($user->fresh())['blueprints']['stata']);

        // An explicit blueprint that can't take the language is refused
        $this->expectException(ValidationException::class);
        (new Resolver($user->fresh()))->resolve(['name' => 'Do', 'language' => 'stata', 'blueprint' => 'framework/course']);
    }

    public function test_resolve_sends_the_defaults_revision_and_it_changes_with_the_default_blueprint(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/resolve', ['language' => 'stata'])->assertOk();
        $this->assertSame('Untitled project', $first->json('name'));
        $revision = $first->headers->get('Framework-Defaults-Revision');
        $this->assertNotEmpty($revision);
        $this->assertSame($revision, $this->getJson('/api/v1/defaults')->headers->get('Framework-Defaults-Revision'));
        $this->assertNull($first->headers->get('Framework-Notice'));

        ProjectDefaults::setBlueprint($user, 'r', Blueprint::whereNull('user_id')->where('key', 'course')->firstOrFail());
        $second = $this->postJson('/api/v1/resolve', ['language' => 'stata'])->assertOk();
        $this->assertNotSame($revision, $second->headers->get('Framework-Defaults-Revision'));

        // A new version of a default blueprint changes it too
        $standard = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail()->latestVersion;
        $standard->replicate()->fill(['version' => $standard->version + 1, 'layer' => ['git' => ['use_git' => false]]])->save();
        $this->assertNotSame($second->headers->get('Framework-Defaults-Revision'), $this->getJson('/api/v1/defaults')->headers->get('Framework-Defaults-Revision'));
    }

    public function test_a_requirement_wins_over_the_blueprints_own_values(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();
        $blueprint->latestVersion->update(['layer' => ['git' => ['use_git' => false]], 'requires' => ['git' => ['use_git' => true]]]);

        $resolved = (new Resolver($user->fresh()))->resolve(['name' => 'Survey']);
        $spec = DefaultsPreview::merge($resolved['envelope']['layers']);
        $this->assertTrue($spec['git']['use_git']);
        $this->assertFalse($resolved['layers']['blueprint']['git']['use_git']);
    }

    public function test_projects_keep_their_configuration_when_defaults_change(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/projects', ['name' => 'Survey', 'blueprint_id' => Blueprint::whereNull('user_id')->where('key', 'project')->value('id')])->assertSessionHasNoErrors();
        $project = $user->projects()->sole();
        $before = ProjectLayers::for($project);

        ProjectDefaults::setBlueprint($user, 'r', Blueprint::whereNull('user_id')->where('key', 'course')->firstOrFail());

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

    public function test_account_tools_and_sets_no_longer_join_a_blueprint(): void
    {
        $user = User::factory()->create(['project_defaults' => ['tools' => ['quarto_manuscript' => true, 'jupyter' => false], 'skill_sets' => ['framework/ggplot2']]]);
        $keys = fn (array $choices) => collect((new Resolver($user))->resolve(['name' => 'S'] + $choices)['pieces'])->pluck('facet.key')->all();

        // The blueprint decides, so the old account defaults don't come along
        $bare = $keys(['blueprint' => 'framework/bare', 'language' => 'r']);
        $this->assertNotContains('quarto_manuscript', $bare);
        $this->assertNotContains('ggplot2', $bare);

        // Choices for the project still add them
        $chosen = $keys(['blueprint' => 'framework/bare', 'language' => 'r', 'skill_sets' => ['framework/ggplot2'], 'tools' => ['quarto_manuscript' => true]]);
        $this->assertContains('ggplot2', $chosen);
        $this->assertContains('quarto_manuscript', $chosen);

        // Jupyter comes with the Jupyter workflow only
        $this->assertContains('jupyter', $keys(['language' => 'python', 'workflow' => 'jupyter']));
        foreach (['quarto', 'scripts'] as $workflow) {
            $this->assertNotContains('jupyter', $keys(['language' => 'python', 'workflow' => $workflow]));
        }
        $this->assertNotContains('jupyter', $keys(['language' => 'python', 'blueprint' => 'framework/course']));
    }

    public function test_your_own_blueprint_keeps_its_own_skill_and_package_sets(): void
    {
        $user = User::factory()->create(['project_defaults' => ['skill_sets' => ['framework/ggplot2'], 'package_sets' => ['framework/tidyverse-analysis']]]);
        $this->withToken($user->createToken('t')->plainTextToken)->postJson('/api/v1/blueprints', [
            'name' => 'Course exercise', 'language' => 'r', 'structure' => 'framework/project', 'skill_sets' => [], 'package_sets' => [],
        ])->assertCreated();
        $handle = Blueprint::where('user_id', $user->id)->firstOrFail()->handle;

        $resolved = (new Resolver($user->fresh()))->resolve(['name' => 'S', 'blueprint' => $handle, 'language' => 'r']);
        $keys = collect($resolved['pieces'])->pluck('facet.key')->all();
        $this->assertNotContains('ggplot2', $keys);
        $this->assertNotContains('tidyverse-analysis', $keys);

        // The packages' default Framework skills are removed too, since no chosen set brings them
        $skills = [];
        foreach ($resolved['envelope']['layers'] as $entry) {
            $skills = array_replace($skills, $entry['layer']['skills'] ?? []);
        }
        $this->assertArrayHasKey('framework-workflow', $skills);
        $this->assertNull($skills['framework-workflow']);
        $this->assertNull($skills['ggplot2-figures']);
    }
}
