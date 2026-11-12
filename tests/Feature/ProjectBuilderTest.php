<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The New project page previews exactly what it creates. */
class ProjectBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function blueprint(string $key): Blueprint
    {
        return Blueprint::whereNull('user_id')->where('key', $key)->firstOrFail();
    }

    public function test_preview_warns_about_a_render_directory_not_created_during_setup(): void
    {
        $this->actingAs(User::factory()->create());
        $choices = ['blueprint_id' => $this->blueprint('project')->id, 'language' => 'r', 'customized' => true,
            'structure' => 'framework/project', 'tools' => ['quarto' => true]];
        $this->postJson('/projects/preview', $choices + ['extra_yaml' => "quarto:\n  render_dir: results/rendered"])
            ->assertOk()->assertJsonPath('missing_render_directory', 'results/rendered');
        $this->postJson('/projects/preview', $choices + ['extra_yaml' => "quarto:\n  render_dir: ."])
            ->assertOk()->assertJsonPath('missing_render_directory', null);
        $this->postJson('/projects/preview', $choices + ['extra_yaml' => "quarto:\n  render_dir: results/rendered\nextra_directories:\n  renders:\n    label: Rendered documents\n    path: results/rendered"])
            ->assertOk()->assertJsonPath('missing_render_directory', null);
        $this->postJson('/projects/preview', $choices + ['extra_yaml' => "quarto:\n  render_dir: results\nextra_directories:\n  renders:\n    label: Rendered documents\n    path: results/rendered"])
            ->assertOk()->assertJsonPath('missing_render_directory', null);
        $this->postJson('/projects/preview', array_replace($choices, ['workflow' => 'scripts', 'tools' => ['quarto' => false], 'extra_yaml' => "quarto:\n  render_dir: results/rendered"]))
            ->assertOk()->assertJsonPath('missing_render_directory', null);
    }

    public function test_the_preview_shows_a_named_blueprint_alone_and_marks_required_tools(): void
    {
        // Account defaults from before blueprints held everything don't come along
        $user = User::factory()->create(['project_defaults' => ['skill_sets' => ['framework/ggplot2'], 'tools' => ['quarto_manuscript' => true]]]);

        $preview = $this->actingAs($user)->postJson('/projects/preview', ['blueprint_id' => $this->blueprint('bare')->id, 'language' => 'r'])->assertOk();

        $this->assertSame('quarto', $preview->json('workflow.key'));
        $this->assertSame(['framework/git'], array_column($preview->json('skill_sets'), 'key'));
        $this->assertSame('blueprint', $preview->json('skill_sets.0.from'));
        $this->assertFalse($preview->json('tools.quarto_manuscript.on'));
        $this->assertSame('Required by the Quarto workflow', $preview->json('tools.quarto.required'));
        $this->assertNull($preview->json('tools.targets.required'));
    }

    public function test_a_customized_project_gets_exactly_the_selections_shown_even_when_all_are_unticked(): void
    {
        $user = User::factory()->create(['project_defaults' => ['skill_sets' => ['framework/ggplot2'], 'package_sets' => ['framework/tidyverse-analysis']]]);
        $choices = [
            'blueprint_id' => $this->blueprint('project')->id, 'language' => 'r', 'customized' => true,
            'structure' => 'framework/project', 'tools' => ['quarto' => true, 'targets' => false],
            'package_sets' => [], 'skill_sets' => [], 'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
        ];

        $preview = $this->actingAs($user)->postJson('/projects/preview', $choices)->assertOk();
        $this->assertSame([], $preview->json('package_sets'));
        $this->assertSame([], $preview->json('skill_sets'));

        $this->actingAs($user)->post('/projects', $choices + ['name' => 'Survey'])->assertSessionHasNoErrors();
        $keys = array_column(ProjectLayers::for($user->projects()->sole())['layers'], 'key');
        $this->assertNotContains('framework/ggplot2', $keys);
        $this->assertNotContains('framework/tidyverse-analysis', $keys);

        // Leaving the lists out gives the blueprint's own; old account defaults don't come along
        $this->actingAs($user)->post('/projects', ['name' => 'Plain', 'blueprint_id' => $this->blueprint('project')->id, 'language' => 'r'])->assertSessionHasNoErrors();
        $plain = array_column(ProjectLayers::for($user->projects()->where('slug', 'plain')->sole())['layers'], 'key');
        $this->assertNotContains('framework/ggplot2', $plain);
        $this->assertNotContains('framework/tidyverse-analysis', $plain);
        $this->assertContains('framework/git', $plain);
    }

    public function test_a_required_tool_cant_be_turned_off_for_a_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/projects/preview', [
            'blueprint_id' => $this->blueprint('course')->id, 'language' => 'r', 'customized' => true, 'structure' => 'framework/course', 'tools' => ['quarto' => false],
        ])->assertStatus(422)->assertJsonPath('problem', 'Quarto is required by the Quarto workflow.');
    }

    public function test_the_preview_shows_the_files_and_agents_and_a_project_can_choose_its_own_agents(): void
    {
        $user = User::factory()->create();
        $doc = $user->globalSettings()->document;
        $doc['ai']['assistants'] = ['claude'];
        $user->globalSettings()->replaceDocument($doc);
        $before = $user->fresh()->globalSettings()->document;

        $preview = $this->actingAs($user)->postJson('/projects/preview', ['blueprint_id' => $this->blueprint('project')->id, 'language' => 'r', 'name' => 'Clinic Visits'])->assertOk();
        $paths = array_column($preview->json('tree'), 'path');
        $this->assertContains('clinic-visits.code-workspace', $paths);
        $this->assertContains(['from' => 'notebooks', 'to' => 'outputs/notebooks'], $preview->json('render_dirs'));
        // Agents come from the blueprint, so the account's old agent setting doesn't apply
        $this->assertSame(['enabled' => true, 'named' => [], 'other' => true], $preview->json('agents'));
        $this->actingAs($user)->post("/blueprints/{$this->blueprint('project')->id}/fork", ['language' => 'r', 'workflow' => 'quarto']);
        $mine = Blueprint::where('user_id', $user->id)->sole();
        $mine->latestVersion->update(['agent_defaults' => ['claude']]);
        $this->assertSame(['enabled' => true, 'named' => ['claude'], 'other' => false], $this->actingAs($user)->postJson('/projects/preview', ['blueprint_id' => $mine->id, 'language' => 'r'])->json('agents'));
        $mine->delete();

        // Codex for this project only
        $choices = [
            'name' => 'Clinic Visits', 'blueprint_id' => $this->blueprint('project')->id, 'language' => 'r', 'customized' => true,
            'structure' => 'framework/project', 'tools' => ['quarto' => true],
            'settings' => ['sensitive' => false, 'git' => true, 'ai' => ['enabled' => true, 'assistants' => ['claude' => false, 'codex' => true, 'copilot' => false]]],
        ];
        $this->actingAs($user)->post('/projects', $choices)->assertSessionHasNoErrors();
        $spec = DefaultsPreview::merge(ProjectLayers::for($user->projects()->sole())['layers']);
        $this->assertSame(['claude' => false, 'codex' => true, 'copilot' => false], $spec['ai']['assistants']);
        $this->assertSame($before, $user->fresh()->globalSettings()->document);
    }

    public function test_setup_commands_name_the_configured_client_address_not_the_request_host(): void
    {
        config(['framework.client_url' => 'https://cloud.example.org']);
        $user = User::factory()->create();

        $this->actingAs($user)->withHeaders(['Host' => 'attacker.example.com', 'X-Forwarded-Host' => 'attacker.example.com'])
            ->get('/projects')->assertInertia(fn ($page) => $page->where('clientUrl', 'https://cloud.example.org'));
    }
}
