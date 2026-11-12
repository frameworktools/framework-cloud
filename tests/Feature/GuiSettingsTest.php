<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Settings the retired local GUI had, now in the blueprint and project
 * editors: notebook, script, and slide templates (stubs/), other .env
 * variables, and packages from no package set.
 */
class GuiSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function blueprintChoices(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Survey study', 'visibility' => 'private', 'language' => 'r', 'workflow' => 'quarto', 'notebook_format' => 'quarto',
            'structure' => 'framework/project', 'tools' => ['quarto' => true], 'package_sets' => [], 'skill_sets' => [], 'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
        ];
    }

    private function projectChoices(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Survey',
            'blueprint_id' => Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail()->id,
            'language' => 'r', 'workflow' => 'quarto', 'notebook_format' => 'quarto', 'customized' => true,
            'structure' => 'framework/project', 'tools' => ['quarto' => true],
            'package_sets' => [], 'skill_sets' => [], 'skills' => [], 'excluded_skills' => [],
            'settings' => ['sensitive' => false, 'git' => true, 'hooks' => [], 'ai' => ['enabled' => true, 'assistants' => ['claude' => true]]],
        ];
    }

    private function projectLayer(User $user): array
    {
        return collect(ProjectLayers::for($user->projects()->sole()->fresh())['layers'])->firstWhere('kind', 'project')['layer'];
    }

    public function test_a_blueprints_templates_are_stub_files_and_reopen_in_the_templates_tab(): void
    {
        $user = User::factory()->create();
        $templates = ['notebook' => "---\ntitle: \"{{ title }}\"\n---\n\nMy notebook\n", 'script' => "# {{ title }}\nlibrary(framework)\nscaffold()\n", 'slides' => ''];
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['templates' => $templates]))->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->sole()->latestVersion;

        // Placeholders stay for make_notebook() to fill; an empty template writes nothing
        $this->assertSame([
            'stubs/notebook-default.qmd' => ['content' => $templates['notebook']],
            'stubs/script-default.R' => ['content' => $templates['script']],
        ], $version->layer['files']);

        $this->get("/blueprints/{$version->blueprint_id}/edit?tab=templates")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('section', 'templates')
            ->where('start.templates.notebook', $templates['notebook'])
            ->where('start.templates.script', $templates['script'])
            ->where('start.templates.slides', '')
            ->where('start.extra_yaml', fn ($yaml) => ! str_contains((string) $yaml, 'stubs/'))
            ->where('catalog.templates.r.notebook.quarto', fn ($stub) => str_contains($stub, '{{ title }}')));

        // Saving the reopened choices again keeps the same files
        $this->put("/blueprints/{$version->blueprint_id}", $this->blueprintChoices(['templates' => $templates, 'notes' => 'Again']))->assertSessionHasNoErrors();
        $this->assertSame($version->layer['files'], Blueprint::where('user_id', $user->id)->sole()->latestVersion->layer['files']);
    }

    public function test_the_notebook_template_follows_the_format_and_language(): void
    {
        $user = User::factory()->create();
        $templates = ['notebook' => "Notebook\n", 'script' => "Script\n", 'slides' => "Slides\n"];

        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['name' => 'Rmd', 'notebook_format' => 'rmarkdown', 'templates' => $templates]))->assertSessionHasNoErrors();
        $this->assertSame(['stubs/notebook-default.Rmd', 'stubs/script-default.R', 'stubs/notebook-revealjs.qmd'], array_keys(Blueprint::where('name', 'Rmd')->sole()->latestVersion->layer['files']));

        // Jupyter and scripts have no Quarto notebook or slides; Python scripts are .py
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['name' => 'Py', 'language' => 'python', 'workflow' => 'jupyter', 'notebook_format' => 'jupyter', 'tools' => ['jupyter' => true, 'quarto' => true], 'templates' => $templates]))->assertSessionHasNoErrors();
        $this->assertSame(['stubs/script-default.py'], array_keys(Blueprint::where('name', 'Py')->sole()->latestVersion->layer['files']));

        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['name' => 'Stata', 'language' => 'stata', 'workflow' => 'scripts', 'notebook_format' => null, 'tools' => [], 'templates' => $templates]))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('files', Blueprint::where('name', 'Stata')->sole()->latestVersion->layer);
    }

    public function test_new_project_and_edit_project_carry_templates(): void
    {
        $user = User::factory()->create();
        $choices = $this->projectChoices(['templates' => ['notebook' => "Mine {{ title }}\n", 'script' => '', 'slides' => '']]);
        $this->actingAs($user)->postJson('/projects/preview', $choices)->assertOk()
            ->assertJsonPath('tree', fn ($tree) => collect($tree)->contains(fn ($entry) => str_starts_with($entry['path'], 'stubs')));
        $this->actingAs($user)->post('/projects', $choices)->assertSessionHasNoErrors();

        $this->assertSame(['content' => "Mine {{ title }}\n"], $this->projectLayer($user)['files']['stubs/notebook-default.qmd']);
        $project = $user->projects()->sole();
        $this->get("/projects/{$project->slug}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('editing.choices.templates.notebook', fn ($template) => trim($template) === 'Mine {{ title }}'));

        $choices['templates'] = ['notebook' => '', 'script' => "Script {{ date }}\n", 'slides' => ''];
        $this->put("/projects/{$project->id}", $choices + ['update_setup' => true])->assertSessionHasNoErrors();
        $this->assertSame(['stubs/script-default.R' => ['content' => "Script {{ date }}\n"]], $this->projectLayer($user)['files']);
    }

    public function test_other_env_variables_go_into_env_and_reopen_in_the_connections_tab(): void
    {
        $user = User::factory()->create();
        $connections = ['databases' => [], 'storage_buckets' => [], 'variables' => [
            ['name' => 'STUDY_YEAR', 'value' => '2024'], ['name' => 'API_TOKEN', 'value' => ''], ['name' => '', 'value' => ''],
        ]];
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['connections' => $connections]))->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->sole()->latestVersion;

        $this->assertSame(['API_TOKEN' => '', 'STUDY_YEAR' => '2024'], $version->layer['env']);
        $this->assertArrayNotHasKey('connections', $version->layer);
        $this->get("/blueprints/{$version->blueprint_id}/edit?tab=connections")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('start.connections.variables', [['name' => 'API_TOKEN', 'value' => ''], ['name' => 'STUDY_YEAR', 'value' => '2024']])
            ->where('start.extra_yaml', fn ($yaml) => ! str_contains((string) $yaml, 'STUDY_YEAR')));
    }

    public function test_env_variables_sit_beside_a_connections_own_and_secrets_stay_empty(): void
    {
        $user = User::factory()->create();
        $connections = [
            'databases' => [['name' => 'study_db', 'driver' => 'sqlite', 'database' => 'env("STUDY_DB_FILE", "data/study.sqlite")']],
            'storage_buckets' => [],
            'variables' => [['name' => 'STUDY_YEAR', 'value' => '2024']],
        ];
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['connections' => $connections]))->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->sole()->latestVersion;
        $this->assertSame(['STUDY_DB_FILE' => 'data/study.sqlite', 'STUDY_YEAR' => '2024'], $version->layer['env']);
        // The connection's own variable comes back with the connection, not as another variable
        $this->get("/blueprints/{$version->blueprint_id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('start.connections.variables', [['name' => 'STUDY_YEAR', 'value' => '2024']]));

        $bad = ['databases' => [], 'storage_buckets' => [], 'variables' => [
            ['name' => 'API_TOKEN', 'value' => 'abc123'], ['name' => '2BAD', 'value' => 'x'], ['name' => 'YEAR', 'value' => '1'], ['name' => 'YEAR', 'value' => '2'],
        ]];
        $this->post('/blueprints', $this->blueprintChoices(['name' => 'Bad', 'connections' => $bad]))
            ->assertSessionHasErrors(['connections.variables.0.value', 'connections.variables.1.name', 'connections.variables.3.name']);
    }

    public function test_stata_projects_take_env_variables(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/projects', array_replace($this->projectChoices(), [
            'language' => 'stata', 'workflow' => 'scripts', 'notebook_format' => null, 'tools' => [],
            'connections' => ['databases' => [], 'storage_buckets' => [], 'variables' => [['name' => 'STUDY_YEAR', 'value' => '2024']]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['STUDY_YEAR' => '2024'], $this->projectLayer($user)['env']);
    }

    public function test_a_blueprint_holds_packages_from_no_package_set(): void
    {
        $user = User::factory()->create();
        $definitions = [
            'fixest' => ['source' => 'cran', 'auto_attach' => true],
            'mytools' => ['source' => 'github', 'repo' => 'example-lab/mytools', 'auto_attach' => false],
        ];
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['package_definitions' => $definitions]))->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->sole()->latestVersion;

        $this->assertSame($definitions['fixest'], $version->layer['packages']['default_packages']['fixest']);
        $this->assertSame($definitions['mytools'], $version->layer['packages']['default_packages']['mytools']);

        // They reopen as the blueprint's own packages, not as catalog choices
        $this->get("/blueprints/{$version->blueprint_id}/edit?tab=packages")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('start.package_definitions.fixest', $definitions['fixest'])
            ->where('start.package_definitions.mytools', $definitions['mytools'])
            ->where('start.package_overrides', fn ($overrides) => ! isset($overrides['fixest']) && ! isset($overrides['mytools']))
            ->where('start.package_auto_attach', fn ($attach) => ! isset($attach['fixest'])));

        // Saving the reopened choices keeps them
        $this->put("/blueprints/{$version->blueprint_id}", $this->blueprintChoices(['package_definitions' => $definitions, 'notes' => 'Again']))->assertSessionHasNoErrors();
        $this->assertSame($definitions['mytools'], Blueprint::where('user_id', $user->id)->sole()->latestVersion->layer['packages']['default_packages']['mytools']);

        // A bad entry is refused
        $this->post('/blueprints', $this->blueprintChoices(['name' => 'Bad', 'package_definitions' => ['mytools' => ['source' => 'github', 'auto_attach' => false]]]))
            ->assertSessionHasErrors(['package_definitions' => 'mytools: GitHub packages need a repo such as owner/name.']);
    }

    public function test_a_new_project_adds_packages_from_no_package_set(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/projects', $this->projectChoices(['package_definitions' => ['fixest' => ['source' => 'cran', 'auto_attach' => false]]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['source' => 'cran', 'auto_attach' => false], $this->projectLayer($user)['packages']['default_packages']['fixest']);
    }
}
