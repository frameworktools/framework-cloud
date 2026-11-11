<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The New project page's steps: start from a blueprint, change its choices, review, and create. */
class ProjectCreationFlowTest extends TestCase
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

    /** @return array<string, mixed> */
    private function choices(): array
    {
        return [
            'blueprint_id' => $this->blueprint('project')->id, 'language' => 'r', 'workflow' => 'quarto', 'customized' => true,
            'structure' => 'framework/project', 'notebook_format' => 'rmarkdown', 'tools' => ['quarto' => true, 'renv' => true],
            'package_sets' => [], 'skill_sets' => [], 'skills' => [], 'excluded_skills' => [],
            'settings' => ['sensitive' => false, 'git' => true, 'hooks' => ['data_security'], 'ai' => ['enabled' => true, 'assistants' => ['claude' => true]]],
        ];
    }

    public function test_the_page_opens_on_the_step_in_the_link(): void
    {
        $user = User::factory()->create();
        $project = $this->blueprint('project')->id;

        $this->actingAs($user)->get('/projects/create')->assertInertia(fn (Assert $page) => $page->where('stage', 'start')->where('tab', null));
        // A blueprint link opens on the project's choices
        $this->actingAs($user)->get("/projects/create?blueprint={$project}")->assertInertia(fn (Assert $page) => $page->where('stage', 'project'));
        $this->actingAs($user)->get("/projects/create?blueprint={$project}&stage=start")->assertInertia(fn (Assert $page) => $page->where('stage', 'start'));
        $this->actingAs($user)->get('/projects/create?stage=project&tab=packages')->assertInertia(fn (Assert $page) => $page->where('stage', 'project')->where('tab', 'packages'));
        $this->actingAs($user)->get('/projects/create?stage=elsewhere')->assertInertia(fn (Assert $page) => $page->where('stage', 'start'));
    }

    public function test_a_changed_project_keeps_its_notebook_format_and_commit_hooks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/projects/preview', $this->choices())->assertOk();
        $this->actingAs($user)->post('/projects', $this->choices() + ['name' => 'Survey'])->assertSessionHasNoErrors();

        $layers = ProjectLayers::for($user->projects()->sole())['layers'];
        $choices = collect($layers)->firstWhere('kind', 'project')['layer'];
        $this->assertSame('rmarkdown', $choices['scaffold']['notebook_format']);
        $this->assertTrue($choices['git']['hooks']['data_security']);
        $this->assertFalse($choices['git']['hooks']['check_notebook_outputs']);
    }

    public function test_the_page_seeds_each_blueprints_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/projects/create')->assertInertia(fn (Assert $page) => $page
            ->where('blueprints.0.handle', 'framework/project')
            ->has('blueprints.0.choices.scaffold.seed')
            ->has('blueprints.0.choices.quarto.html.theme')
            ->has('blueprints.0.choices.quarto.revealjs.theme')
            ->has('blueprints.0.choices.ide')
            ->where('blueprints.0.choices.publish_destination', '')
            ->has('ggplotThemes')
            ->has('quartoChoices'));
    }

    public function test_a_changed_project_keeps_its_scaffold_quarto_editor_and_publishing_choices(): void
    {
        $user = User::factory()->create();
        $choices = $this->choices() + [
            'scaffold' => ['seed_on_scaffold' => true, 'seed' => '42', 'set_theme_on_scaffold' => true, 'ggplot_theme' => 'theme_bw', 'source_all_functions' => false],
            'quarto' => ['html' => ['theme' => 'flatly', 'toc' => false], 'revealjs' => ['theme' => 'moon']],
            'ide' => 'rstudio',
            'publish_destination' => 'github',
        ];

        $this->actingAs($user)->postJson('/projects/preview', $choices)->assertOk();
        $this->actingAs($user)->post('/projects', $choices + ['name' => 'Survey'])->assertSessionHasNoErrors();

        $layer = collect(ProjectLayers::for($user->projects()->sole())['layers'])->firstWhere('kind', 'project')['layer'];
        $this->assertSame('42', $layer['scaffold']['seed']);
        $this->assertSame('theme_bw', $layer['scaffold']['ggplot_theme']);
        $this->assertFalse($layer['scaffold']['source_all_functions']);
        $this->assertSame('rstudio', $layer['scaffold']['ide']);
        $this->assertSame('flatly', $layer['quarto']['html']['theme']);
        $this->assertFalse($layer['quarto']['html']['toc']);
        $this->assertSame('moon', $layer['quarto']['revealjs']['theme']);
        $this->assertSame('github', $layer['publish']['default']);
    }

    public function test_quarto_choices_are_checked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', $this->choices() + ['name' => 'Survey', 'quarto' => ['html' => ['theme' => 'not-a-theme']]])
            ->assertSessionHasErrors('quarto.html.theme');
        $this->actingAs($user)->post('/projects', $this->choices() + ['name' => 'Survey', 'ide' => 'emacs'])->assertSessionHasErrors('ide');
        $this->assertSame(0, $user->projects()->count());
    }

    public function test_saving_as_a_blueprint_creates_a_private_blueprint_and_the_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', $this->choices() + [
            'name' => 'Survey',
            'new_blueprint' => [
                'name' => 'Survey setup', 'description' => 'Survey work', 'visibility' => 'private', 'language' => 'r',
                'agents' => ['claude'], 'workflow' => 'quarto', 'notebook_format' => 'rmarkdown', 'structure' => 'framework/project',
                'tools' => ['quarto' => true, 'renv' => true, 'targets' => false], 'package_sets' => [], 'skill_sets' => [], 'skills' => [], 'excluded_skills' => [],
                'settings' => ['git' => true, 'hooks' => ['data_security'], 'ai' => true],
                'scaffold' => ['seed_on_scaffold' => true, 'seed' => '7', 'set_theme_on_scaffold' => false, 'ggplot_theme' => 'theme_minimal', 'source_all_functions' => true],
                'quarto' => ['html' => ['theme' => 'flatly'], 'revealjs' => ['theme' => 'moon']],
                'ide' => 'rstudio', 'publish_destination' => 'cloud',
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $user->projects()->count());
        $saved = Blueprint::where('user_id', $user->id)->sole();
        $this->assertSame('Survey setup', $saved->name);
        $this->assertSame('private', $saved->visibility);
        $this->assertSame('r', $saved->language);
        $this->assertSame(['claude'], $saved->latestVersion->agent_defaults);
        $this->assertSame('rmarkdown', $saved->latestVersion->layer['scaffold']['notebook_format']);
        $this->assertTrue($saved->latestVersion->layer['git']['hooks']['data_security']);
        $this->assertSame('7', $saved->latestVersion->layer['scaffold']['seed']);
        $this->assertSame('flatly', $saved->latestVersion->layer['quarto']['html']['theme']);
        $this->assertSame('cloud', $saved->latestVersion->layer['publish']['default']);
        $this->assertSame('rstudio', $saved->latestVersion->ide);
    }

    public function test_a_blueprint_that_cant_be_saved_stops_the_project_too(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', $this->choices() + [
            'name' => 'Survey',
            'new_blueprint' => ['name' => '', 'visibility' => 'private', 'language' => 'r', 'structure' => 'framework/project'],
        ])->assertSessionHasErrors('new_blueprint.name');

        $this->assertSame(0, $user->projects()->count());
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
    }
}
