<?php

namespace Tests\Feature\Api\V1;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\User;
use App\Support\Layer;
use App\Support\ProjectDefaults;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiecesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function token(?User $user = null): string
    {
        return ($user ?? User::factory()->create())->createToken('fw')->plainTextToken;
    }

    public function test_lists_each_kind_of_piece(): void
    {
        $token = $this->token();

        $this->withToken($token)->getJson('/api/v1/structures')->assertOk()
            ->assertJsonCount(9, 'structures')
            ->assertJsonPath('structures.0.key', 'framework/project')
            ->assertJsonPath('structures.8.key', 'framework/bare')
            ->assertJsonPath('structures.8.type', 'bare');

        $tools = collect($this->withToken($token)->getJson('/api/v1/tools')->assertOk()->json('tools'));
        $this->assertSame(['framework/quarto', 'framework/quarto_manuscript', 'framework/quarto_slides', 'framework/jupyter', 'framework/targets', 'framework/renv', 'framework/uv'], $tools->pluck('key')->all());
        $this->assertSame(['quarto'], $tools->firstWhere('key', 'framework/quarto_slides')['requires']);

        // Python sees the Quarto tools, not targets or renv
        $python = collect($this->withToken($token)->getJson('/api/v1/tools?language=python')->json('tools'))->pluck('key');
        $this->assertNotContains('framework/renv', $python);
        $this->assertContains('framework/quarto', $python);

        $this->withToken($token)->getJson('/api/v1/skill-sets')->assertOk()
            ->assertJsonPath('skill_sets.1.key', 'framework/ggplot2')
            ->assertJsonPath('skill_sets.1.skills', ['ggplot2-exploring', 'ggplot2-publishing'])
            ->assertJsonPath('skill_sets.1.packages', ['ggplot2']);
        $this->withToken($token)->getJson('/api/v1/package-sets')->assertOk()->assertJsonPath('package_sets.0.key', 'framework/tidy-core')->assertJsonPath('package_sets.1.key', 'framework/tidyverse-analysis')->assertJsonPath('package_sets.3.key', 'framework/python-data-analysis');
        $this->withToken($token)->getJson('/api/v1/skills')->assertOk()->assertJsonCount(26, 'skills');

        $this->withToken($token)->getJson('/api/v1/blueprints')->assertOk()
            ->assertJsonPath('blueprints.0.structure', 'framework/project')
            ->assertJsonPath('blueprints.0.tools', ['uv'])
            ->assertJsonPath('blueprints.0.workflows', ['quarto', 'jupyter', 'scripts'])
            ->assertJsonPath('blueprints.2.workflows', ['quarto'])
            ->assertJsonPath('blueprints.0.skill_sets', ['framework/framework', 'framework/visualization', 'framework/writing']);
    }

    public function test_resolving_a_blueprint_unchanged_matches_a_project_made_from_it(): void
    {
        $user = User::factory()->create();

        $envelope = $this->withToken($this->token($user))->postJson('/api/v1/resolve', ['name' => 'Survey', 'blueprint' => 'framework/project'])
            ->assertOk()->json();

        $this->assertSame([], Layer::envelopeProblems($envelope));
        // Structure, pieces, the workflow and its tool, Framework's settings, the blueprint, and the project
        $this->assertSame(['framework/project', 'framework package defaults', 'framework/framework', 'framework/framework-r', 'framework/visualization', 'framework/visualization-r', 'framework/writing', 'framework/writing-r', 'framework/workflow-quarto', 'framework/quarto', 'framework/git', 'framework/git-r', 'framework/quarto-skills', 'framework/quarto-skills-r', 'framework defaults', 'framework/project', 'survey choices', 'survey'], array_column($envelope['layers'], 'key'));
        $this->assertSame(['key' => 'framework/project', 'version' => 1], $envelope['source']['blueprint']);
    }

    public function test_app_blueprints_bring_their_starter_files_and_package_set(): void
    {
        $token = $this->token();
        $apps = [
            'framework/shiny' => ['r', 'framework/shiny-r', 'app.R', 'shiny'],
            'framework/plumber' => ['r', 'framework/plumber-r', 'plumber.R', 'plumber'],
            'framework/shiny_python' => ['python', 'framework/shiny-python', 'app.py', 'shiny'],
            'framework/fastapi' => ['python', 'framework/fastapi-python', 'app/main.py', 'fastapi'],
        ];

        foreach ($apps as $blueprint => [$language, $set, $file, $package]) {
            $envelope = $this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'Demo', 'blueprint' => $blueprint, 'language' => $language, 'workflow' => 'scripts'])
                ->assertOk()->json();

            $this->assertSame([], Layer::envelopeProblems($envelope), $blueprint);
            $this->assertContains($set, array_column($envelope['layers'], 'key'), $blueprint);
            $structure = $envelope['layers'][0]['layer'];
            $this->assertArrayHasKey($file, $structure['files'], $blueprint);
            $set = collect($envelope['layers'])->firstWhere('key', $set)['layer'];
            $this->assertArrayHasKey($package, $set['packages']['default_packages'], $blueprint);
        }

        // An R app can't be made as a Python project
        $this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'Demo', 'blueprint' => 'framework/shiny', 'language' => 'python'])
            ->assertStatus(422);
    }

    public function test_a_project_can_be_created_at_a_chosen_slug_and_marked_adopted(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))->postJson('/api/v1/projects', ['name' => 'Survey Work', 'slug' => 'survey-2026', 'adopted' => true])
            ->assertCreated()->assertJsonPath('slug', 'survey-2026')->assertJsonPath('adopted', true);
        $this->assertNotNull($user->projects()->where('slug', 'survey-2026')->value('adopted_at'));

        // Taken, and badly formed, slugs are refused
        $this->withToken($this->token($user))->postJson('/api/v1/projects', ['name' => 'Other', 'slug' => 'survey-2026'])->assertJsonValidationErrors('slug');
        $this->withToken($this->token($user))->postJson('/api/v1/projects', ['name' => 'Other', 'slug' => 'Not A Slug'])->assertJsonValidationErrors('slug');
    }

    public function test_a_project_created_from_a_spec_has_exactly_that_spec_as_its_layers(): void
    {
        $user = User::factory()->create();
        // Sent as fw sends it: JSON whose empty maps stay {}
        $spec = json_decode(file_get_contents(database_path('seeders/masters/r/conformance/create/r/bare-scripts.json')), false)->spec;
        $spec->name = 'Old Analysis';
        $body = json_encode(['spec' => $spec, 'slug' => 'old-analysis', 'adopted' => true], JSON_UNESCAPED_SLASHES);
        $token = $this->token($user);
        $send = fn (string $json) => $this->call('POST', '/api/v1/projects', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => "Bearer {$token}"], $json);

        $created = $send($body)
            ->assertCreated()
            ->assertJsonPath('slug', 'old-analysis')
            ->assertJsonPath('name', 'Old Analysis')
            ->assertJsonPath('type', 'bare')
            ->assertJsonPath('adopted', true);
        $this->assertNotEmpty($created->json('token'));

        $project = $user->projects()->where('slug', 'old-analysis')->sole();
        $envelope = json_decode($project->resolved, true);
        // One layer, the project's own, with nothing from the person's defaults
        $this->assertSame(['project'], array_column($envelope['layers'], 'kind'));
        $this->assertEquals(json_decode(json_encode($spec->directories), true), $envelope['layers'][0]['layer']['directories']);
        $this->assertSame([], Layer::envelopeProblems($envelope));
        $this->assertSame('Old Analysis', json_decode($project->spec, true)['name']);
        $this->assertNotNull($project->adopted_at);

        $this->withToken($this->token($user))->getJson('/api/v1/projects/old-analysis')->assertJsonPath('adopted', true)->assertJsonPath('type', 'bare');

        // A spec the schema refuses is reported
        $this->withToken($this->token($user))->postJson('/api/v1/projects', ['spec' => ['spec_version' => 99]])->assertJsonValidationErrors('spec');
    }

    public function test_an_unknown_tool_switched_off_is_ignored_and_one_switched_on_names_the_tools(): void
    {
        $token = $this->token();

        $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'Tools Off', 'tools' => ['not_a_tool' => false]])->assertCreated();
        $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'Tools On', 'tools' => ['not_a_tool' => true]])
            ->assertJsonValidationErrors('tools')
            ->assertJsonFragment(['tools' => ['There is no tool framework/not_a_tool. Tools: quarto, quarto_manuscript, quarto_slides, jupyter, targets, renv, uv.']]);
    }

    public function test_an_imported_structure_keeps_the_projects_type(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))->postJson('/api/v1/structures', ['name' => 'My bare layout', 'directories' => [], 'type' => 'bare'])->assertCreated();
        $this->assertSame('bare', Facet::where('user_id', $user->id)->sole()->latestVersion->layer['type']);

        $this->withToken($this->token($user))->postJson('/api/v1/structures', ['name' => 'Odd', 'directories' => [], 'type' => 'spaceship'])->assertJsonValidationErrors('type');
    }

    public function test_choices_change_the_blueprint(): void
    {
        $envelope = $this->withToken($this->token())->postJson('/api/v1/resolve', [
            'name' => 'Survey',
            'blueprint' => 'framework/project',
            'tools' => ['quarto_manuscript' => true, 'renv' => true],
            'package_sets' => ['framework/tidyverse-analysis'],
            'settings' => ['sensitive' => true, 'git' => false, 'ai' => ['enabled' => true, 'assistants' => ['claude' => true]]],
        ])->assertOk()->json();

        $keys = array_column($envelope['layers'], 'key');
        $this->assertContains('framework/quarto_manuscript', $keys);
        $this->assertContains('framework/renv', $keys);
        $this->assertContains('framework/tidyverse-analysis', $keys);
        // Sensitive data on a structure without private folders adds them
        $this->assertContains('framework/sensitive_data', $keys);

        $settings = $envelope['layers'][count($keys) - 2]['layer'];
        $this->assertTrue($settings['security']['sensitive']);
        $this->assertFalse($settings['git']['use_git']);
        $this->assertSame(['enabled' => true, 'assistants' => ['claude' => true]], $settings['ai']);
        $this->assertSame(['package' => true], $settings['skills']['framework-sensitive-data']);

        // Changed choices are no longer the blueprint, and sensitive projects carry no provenance
        $this->assertArrayNotHasKey('source', $envelope);
    }

    public function test_a_sensitive_project_without_skill_sets_gets_no_framework_skill_unasked(): void
    {
        $envelope = $this->withToken($this->token())->postJson('/api/v1/resolve', [
            'name' => 'Course',
            'structure' => 'framework/project',
            'skill_sets' => [],
            'settings' => ['sensitive' => true],
        ])->assertOk()->json();

        foreach ($envelope['layers'] as $entry) {
            $skills = $entry['layer']['skills'] ?? [];
            $merged = array_key_exists('framework-sensitive-data', $skills) ? $skills['framework-sensitive-data'] : ($merged ?? null);
        }
        // Layers merge in order, so the last mention wins: null removes the skill
        $this->assertNull($merged);
    }

    public function test_ai_off_drops_every_skill(): void
    {
        $envelope = $this->withToken($this->token())->postJson('/api/v1/resolve', [
            'blueprint' => 'framework/project_sensitive', 'settings' => ['ai' => false],
        ])->assertOk()->json();

        $keys = array_column($envelope['layers'], 'key');
        $this->assertNotContains('framework/framework', $keys);
        $this->assertArrayNotHasKey('skills', $envelope['layers'][count($keys) - 2]['layer']);
    }

    public function test_tools_must_fit_the_language_and_their_requirements(): void
    {
        $token = $this->token();

        $this->withToken($token)->postJson('/api/v1/resolve', ['structure' => 'framework/project', 'workflow' => 'scripts', 'tools' => ['quarto_slides' => true]])
            ->assertUnprocessable()->assertJsonValidationErrors(['tools' => 'Quarto slides needs the quarto tool.']);

        $this->withToken($token)->postJson('/api/v1/resolve', ['structure' => 'framework/project', 'language' => 'python', 'tools' => ['renv' => true]])
            ->assertUnprocessable()->assertJsonValidationErrors(['tools' => 'renv is not available for Python projects.']);

        $this->withToken($token)->postJson('/api/v1/resolve', ['structure' => 'framework/project', 'language' => 'stata', 'tools' => ['quarto' => true]])
            ->assertUnprocessable()->assertJsonValidationErrors(['tools' => 'Stata projects have no tools.']);

        $this->withToken($token)->postJson('/api/v1/resolve', ['structure' => 'someone/else'])
            ->assertUnprocessable()->assertJsonValidationErrors(['structure' => 'There is no project structure someone/else.']);
    }

    public function test_creating_a_project_from_choices_records_its_pieces(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);

        $response = $this->withToken($token)->postJson('/api/v1/projects', [
            'name' => 'Clinic Visits', 'blueprint' => 'framework/project', 'tools' => ['renv' => true],
        ])->assertCreated()->assertJsonPath('slug', 'clinic-visits');
        $this->assertNotEmpty($response->json('token'));

        $project = $user->projects()->firstOrFail();
        $this->assertSame('project', $project->project_type);
        $this->assertNotNull($project->composition);

        // The project's layers are the resolved choices
        $layers = $this->withToken($token)->getJson('/api/v1/projects/clinic-visits/layers')->assertOk()->json('layers');
        $this->assertContains('framework/renv', array_column($layers, 'key'));

        // An unchanged blueprint is pinned by version instead
        $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'Plain', 'blueprint' => 'framework/project'])->assertCreated();
        $this->assertNull($user->projects()->where('slug', 'plain')->first()->composition);

        $this->withToken($token)->postJson('/api/v1/projects', ['name' => 'Clinic Visits', 'blueprint' => 'framework/project'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_without_a_blueprint_the_default_blueprint_applies(): void
    {
        // Account defaults from before blueprints held everything no longer apply
        $user = User::factory()->create(['project_defaults' => [
            'package_sets' => ['framework/tidyverse-analysis'],
            'tools' => ['targets' => true],
            'hooks' => ['data_security' => true],
        ]]);
        $token = $this->token($user);

        // The same shape as always, read from the default blueprints
        $defaults = $this->withToken($token)->getJson('/api/v1/defaults')->assertOk()
            ->assertJsonPath('blueprint', 'framework/project')
            ->assertJsonPath('blueprints', ['r' => 'framework/project', 'python' => 'framework/project', 'stata' => 'framework/project'])
            ->assertJsonPath('package_sets', [])
            ->assertJsonPath('settings.hooks', [])
            ->assertJsonPath('workflows', ['r' => 'quarto', 'python' => 'jupyter', 'stata' => 'scripts'])
            ->json();
        $this->assertSame(['publish_destination', 'blueprint', 'blueprints', 'language', 'workflows', 'rmarkdown', 'package_sets', 'skill_sets', 'packages', 'tools', 'settings'], array_keys($defaults));
        $this->assertSame(['git', 'ai', 'hooks', 'ide', 'scaffold', 'agents', 'quarto'], array_keys($defaults['settings']));
        $this->assertContains('dplyr', array_column($defaults['packages']['r'], 'name'));

        $envelope = $this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'Survey'])->assertOk()->json();
        $keys = array_column($envelope['layers'], 'key');
        $this->assertSame('framework/project', $keys[0]);
        $this->assertNotContains('framework/tidyverse-analysis', $keys);
        $this->assertNotContains('framework/targets', $keys);
        $this->assertArrayHasKey('source', $envelope);

        // A personal default blueprint brings its own sets, and the defaults describe it
        $this->withToken($token)->postJson('/api/v1/blueprints', [
            'name' => 'Survey work', 'language' => 'r', 'structure' => 'framework/project', 'package_sets' => ['framework/tidyverse-analysis'], 'settings' => ['hooks' => ['data_security']],
        ])->assertCreated();
        ProjectDefaults::setBlueprint($user, 'r', Blueprint::where('user_id', $user->id)->sole());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/defaults')
            ->assertJsonPath('blueprint', Blueprint::where('user_id', $user->id)->sole()->handle)
            ->assertJsonPath('package_sets', ['framework/tidyverse-analysis'])
            ->assertJsonPath('settings.hooks', ['data_security']);
        $layers = $this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'Survey'])->json('layers');
        $this->assertContains('framework/tidyverse-analysis', array_column($layers, 'key'));
        $this->assertTrue(collect($layers)->firstWhere('kind', 'blueprint')['layer']['git']['hooks']['data_security']);
    }

    public function test_with_the_spec_tools_key_on_layers_name_their_tools_and_layout(): void
    {
        config(['framework.spec_tools' => true]);

        $envelope = $this->withToken($this->token())->postJson('/api/v1/resolve', [
            'name' => 'Survey', 'blueprint' => 'framework/project', 'tools' => ['renv' => true],
        ])->assertOk()->json();

        $this->assertSame([], Layer::envelopeProblems($envelope));
        $layers = collect($envelope['layers'])->keyBy('key');
        $this->assertSame('split', $envelope['layers'][0]['layer']['settings_layout']);
        $this->assertSame(['renv' => true], $layers['framework/renv']['layer']['tools']);

        // The project layer names every R tool, on or off
        $this->assertSame(
            ['quarto' => true, 'quarto_manuscript' => false, 'quarto_slides' => false, 'jupyter' => false, 'targets' => false, 'renv' => true],
            $layers['survey']['layer']['tools'],
        );

        // Stata gets no tools map; bare has no core files
        $stata = $this->withToken($this->token())->postJson('/api/v1/resolve', ['name' => 'S', 'blueprint' => 'framework/bare', 'language' => 'stata'])->json('layers');
        $this->assertArrayNotHasKey('tools', last($stata)['layer']);
        $this->assertFalse($stata[0]['layer']['core_files']);

        // Off, nothing new goes out, for clients older than the schema
        config(['framework.spec_tools' => false]);
        $plain = $this->withToken($this->token())->postJson('/api/v1/resolve', ['name' => 'Survey', 'blueprint' => 'framework/project'])->json('layers');
        $this->assertArrayNotHasKey('settings_layout', $plain[0]['layer']);
    }

    public function test_framework_blueprints_give_python_projects_uv_and_r_projects_none(): void
    {
        $token = $this->token();

        $r = $this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'S', 'blueprint' => 'framework/project'])->json();
        $this->assertNotContains('framework/uv', array_column($r['layers'], 'key'));
        $this->assertArrayHasKey('source', $r, 'An R project from an unchanged blueprint still pins it');

        $python = $this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'S', 'blueprint' => 'framework/project', 'language' => 'python'])->json('layers');
        $keys = array_column($python, 'key');
        $this->assertContains('framework/uv', $keys);
        $this->assertContains('framework/quarto', $keys);
        $this->assertContains('framework/framework', $keys, 'Each package ships its own Framework skills');
        $this->assertNotContains('framework/ggplot2', $keys, 'ggplot2 is for R');

        $bare = array_column($this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'S', 'blueprint' => 'framework/bare', 'language' => 'python'])->json('layers'), 'key');
        // Python's workflow is Jupyter, which brings its kernel and Quarto for rendering
        $this->assertSame(['framework/bare', 'framework/uv', 'framework/workflow-jupyter', 'framework/jupyter', 'framework/jupyter-python', 'framework/quarto', 'framework/git', 'framework/git-python', 'framework defaults', 'framework/bare', 's'], $bare);
    }

    public function test_sets_for_another_language_are_left_out(): void
    {
        $keys = array_column($this->withToken($this->token())->postJson('/api/v1/resolve', [
            'name' => 'S', 'structure' => 'framework/project', 'language' => 'python',
            'skill_sets' => ['framework/framework', 'framework/ggplot2'], 'package_sets' => ['framework/tidyverse-analysis'],
        ])->assertOk()->json('layers'), 'key');

        $this->assertContains('framework/framework', $keys);
        $this->assertNotContains('framework/ggplot2', $keys);
        $this->assertNotContains('framework/tidyverse-analysis', $keys);
    }

    public function test_empty_maps_in_resolved_layers_are_objects(): void
    {
        $json = $this->withToken($this->token())->postJson('/api/v1/resolve', ['name' => 'S', 'structure' => 'framework/bare'])
            ->assertOk()->getContent();

        $layers = json_decode($json)->layers;
        $this->assertIsObject($layers[1]->layer);
        foreach ($layers as $entry) {
            foreach (['directories', 'render_dirs'] as $section) {
                if (isset($entry->layer->{$section})) {
                    $this->assertIsObject($entry->layer->{$section}, "{$entry->key} {$section}");
                }
            }
        }
    }

    public function test_jupyter_brings_the_kernel_for_the_projects_language(): void
    {
        $token = $this->token();

        $r = collect($this->withToken($token)->postJson('/api/v1/resolve', ['structure' => 'framework/project', 'tools' => ['jupyter' => true]])->json('layers'))->keyBy('key');
        $this->assertArrayHasKey('IRkernel', $r['framework/jupyter-r']['layer']['packages']['default_packages']);

        $python = collect($this->withToken($token)->postJson('/api/v1/resolve', ['structure' => 'framework/project', 'language' => 'python', 'tools' => ['jupyter' => true, 'uv' => true]])->json('layers'))->keyBy('key');
        $this->assertSame('pypi', $python['framework/jupyter-python']['layer']['packages']['default_packages']['ipykernel']['source']);
        $this->assertArrayHasKey('framework/uv', $python);
    }

    public function test_a_project_token_cannot_list_or_resolve(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'S', 'slug' => 's']);

        $this->withToken($project->issuePullToken())->getJson('/api/v1/tools')->assertForbidden();
    }

    public function test_git_hooks_come_from_the_blueprint_and_the_project_choices(): void
    {
        $user = User::factory()->create(['project_defaults' => ['hooks' => ['data_security' => true, 'check_sensitive_dirs' => false]]]);
        $token = $this->token($user);

        // The old account hooks don't apply
        $spec = fn (array $choices) => collect($this->withToken($token)->postJson('/api/v1/resolve', ['name' => 'S', 'blueprint' => 'framework/project'] + $choices)->assertOk()->json('layers'))
            ->reduce(fn (array $hooks, array $entry) => array_replace($hooks, $entry['layer']['git']['hooks'] ?? []), []);
        $this->assertSame([], array_filter($spec([])));
        $this->assertSame(['data_security' => true], array_filter($spec(['settings' => ['hooks' => ['data_security']]])));
        $this->assertSame([], array_filter($spec(['settings' => ['git' => false, 'hooks' => ['data_security']]])));
    }

    public function test_skills_filter_by_language(): void
    {
        $token = $this->token();
        $all = collect($this->withToken($token)->getJson('/api/v1/skills')->json('skills'));
        $python = collect($this->withToken($token)->getJson('/api/v1/skills?language=python')->assertOk()->json('skills'));

        $this->assertNotEmpty($python);
        $this->assertLessThan($all->count(), $python->count());
        foreach ($python as $skill) {
            $this->assertTrue(! $skill['language'] || in_array('python', $skill['language'], true), $skill['key']);
        }
        $this->assertNotContains('framework/framework-stata', $python->pluck('key'));
    }
}
