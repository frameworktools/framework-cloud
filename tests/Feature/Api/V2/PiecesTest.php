<?php

namespace Tests\Feature\Api\V2;

use App\Models\User;
use App\Support\Layer;
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

        $this->withToken($token)->getJson('/api/v2/structures')->assertOk()
            ->assertJsonCount(5, 'structures')
            ->assertJsonPath('structures.0.key', 'framework/bare')
            ->assertJsonPath('structures.0.type', 'bare');

        $tools = collect($this->withToken($token)->getJson('/api/v2/tools')->assertOk()->json('tools'));
        $this->assertSame(['framework/quarto', 'framework/quarto_manuscript', 'framework/quarto_slides', 'framework/jupyter', 'framework/targets', 'framework/renv', 'framework/uv'], $tools->pluck('key')->all());
        $this->assertSame(['quarto'], $tools->firstWhere('key', 'framework/quarto_slides')['requires']);

        // Python sees the Quarto tools, not targets or renv
        $python = collect($this->withToken($token)->getJson('/api/v2/tools?language=python')->json('tools'))->pluck('key');
        $this->assertNotContains('framework/renv', $python);
        $this->assertContains('framework/quarto', $python);

        $this->withToken($token)->getJson('/api/v2/skill-sets')->assertOk()
            ->assertJsonPath('skill_sets.1.key', 'framework/ggplot2')
            ->assertJsonPath('skill_sets.1.skills', ['ggplot2-exploring', 'ggplot2-publishing'])
            ->assertJsonPath('skill_sets.1.packages', ['ggplot2']);
        $this->withToken($token)->getJson('/api/v2/package-sets')->assertOk()->assertJsonPath('package_sets.0.key', 'framework/tidyverse-analysis');
        $this->withToken($token)->getJson('/api/v2/skills')->assertOk()->assertJsonCount(7, 'skills');

        $this->withToken($token)->getJson('/api/v2/blueprints')->assertOk()
            ->assertJsonPath('blueprints.1.structure', 'framework/project')
            ->assertJsonPath('blueprints.1.tools', ['quarto', 'uv'])
            ->assertJsonPath('blueprints.1.skill_sets', ['framework/framework', 'framework/ggplot2']);
    }

    public function test_resolving_a_blueprint_unchanged_matches_a_project_made_from_it(): void
    {
        $user = User::factory()->create();

        $envelope = $this->withToken($this->token($user))->postJson('/api/v2/resolve', ['name' => 'Survey', 'blueprint' => 'framework/project'])
            ->assertOk()->json();

        $this->assertSame([], Layer::envelopeProblems($envelope));
        $this->assertSame(['framework/project', 'framework/quarto', 'framework/framework', 'framework/ggplot2', 'framework/project', 'survey'], array_column($envelope['layers'], 'key'));
        $this->assertSame(['key' => 'framework/project', 'version' => 1], $envelope['source']['blueprint']);
    }

    public function test_choices_change_the_blueprint(): void
    {
        $envelope = $this->withToken($this->token())->postJson('/api/v2/resolve', [
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

    public function test_ai_off_drops_every_skill(): void
    {
        $envelope = $this->withToken($this->token())->postJson('/api/v2/resolve', [
            'blueprint' => 'framework/project_sensitive', 'settings' => ['ai' => false],
        ])->assertOk()->json();

        $keys = array_column($envelope['layers'], 'key');
        $this->assertNotContains('framework/framework', $keys);
        $this->assertArrayNotHasKey('skills', $envelope['layers'][count($keys) - 2]['layer']);
    }

    public function test_tools_must_fit_the_language_and_their_requirements(): void
    {
        $token = $this->token();

        $this->withToken($token)->postJson('/api/v2/resolve', ['structure' => 'framework/project', 'tools' => ['quarto_slides' => true]])
            ->assertUnprocessable()->assertJsonValidationErrors(['tools' => 'Quarto slides needs the quarto tool.']);

        $this->withToken($token)->postJson('/api/v2/resolve', ['structure' => 'framework/project', 'language' => 'python', 'tools' => ['renv' => true]])
            ->assertUnprocessable()->assertJsonValidationErrors(['tools' => 'renv is not available for Python projects.']);

        $this->withToken($token)->postJson('/api/v2/resolve', ['structure' => 'framework/project', 'language' => 'stata', 'tools' => ['quarto' => true]])
            ->assertUnprocessable()->assertJsonValidationErrors(['tools' => 'Stata projects have no tools.']);

        $this->withToken($token)->postJson('/api/v2/resolve', ['structure' => 'someone/else'])
            ->assertUnprocessable()->assertJsonValidationErrors(['structure' => 'There is no project structure someone/else.']);
    }

    public function test_creating_a_project_from_choices_records_its_pieces(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);

        $response = $this->withToken($token)->postJson('/api/v2/projects', [
            'name' => 'Clinic Visits', 'blueprint' => 'framework/project', 'tools' => ['renv' => true],
        ])->assertCreated()->assertJsonPath('slug', 'clinic-visits');
        $this->assertNotEmpty($response->json('token'));

        $project = $user->projects()->firstOrFail();
        $this->assertSame('project', $project->project_type);
        $this->assertNotNull($project->composition);

        // The project's layers are the resolved choices
        $layers = $this->withToken($token)->getJson('/api/v2/projects/clinic-visits/layers')->assertOk()->json('layers');
        $this->assertContains('framework/renv', array_column($layers, 'key'));

        // An unchanged blueprint is pinned by version instead
        $this->withToken($token)->postJson('/api/v2/projects', ['name' => 'Plain', 'blueprint' => 'framework/project'])->assertCreated();
        $this->assertNull($user->projects()->where('slug', 'plain')->first()->composition);

        $this->withToken($token)->postJson('/api/v2/projects', ['name' => 'Clinic Visits', 'blueprint' => 'framework/project'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_without_a_blueprint_the_users_defaults_apply(): void
    {
        $user = User::factory()->create(['project_defaults' => [
            'package_sets' => ['framework/tidyverse-analysis'],
            'tools' => ['targets' => true],
            'sensitive' => true,
        ]]);
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/v2/defaults')->assertOk()
            ->assertJsonPath('blueprint', 'framework/bare')
            ->assertJsonPath('package_sets', ['framework/tidyverse-analysis'])
            ->assertJsonPath('settings.sensitive', true);

        $envelope = $this->withToken($token)->postJson('/api/v2/resolve', ['name' => 'Survey'])->assertOk()->json();
        $keys = array_column($envelope['layers'], 'key');
        $this->assertSame('framework/bare', $keys[0]);
        $this->assertContains('framework/tidyverse-analysis', $keys);
        $this->assertContains('framework/targets', $keys);
        $this->assertTrue($envelope['layers'][count($keys) - 2]['layer']['security']['sensitive']);

        // A chosen blueprint replaces the preferred pieces, but default settings still apply
        $keys = array_column($this->withToken($token)->postJson('/api/v2/resolve', ['blueprint' => 'framework/course'])->json('layers'), 'key');
        $this->assertNotContains('framework/tidyverse-analysis', $keys);
        $this->assertContains('framework/sensitive_data', $keys);

        // Defaults equal to Framework's own leave a blueprint unchanged
        $plain = User::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->assertArrayHasKey('source', $this->withToken($this->token($plain))->postJson('/api/v2/resolve', ['name' => 'X'])->json());
    }

    public function test_with_the_spec_tools_key_on_layers_name_their_tools_and_layout(): void
    {
        config(['framework.spec_tools' => true]);

        $envelope = $this->withToken($this->token())->postJson('/api/v2/resolve', [
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
        $stata = $this->withToken($this->token())->postJson('/api/v2/resolve', ['name' => 'S', 'blueprint' => 'framework/bare', 'language' => 'stata'])->json('layers');
        $this->assertArrayNotHasKey('tools', last($stata)['layer']);
        $this->assertFalse($stata[0]['layer']['core_files']);

        // Off, nothing new goes out, for clients older than the schema
        config(['framework.spec_tools' => false]);
        $plain = $this->withToken($this->token())->postJson('/api/v2/resolve', ['name' => 'Survey', 'blueprint' => 'framework/project'])->json('layers');
        $this->assertArrayNotHasKey('settings_layout', $plain[0]['layer']);
    }

    public function test_framework_blueprints_give_python_projects_uv_and_r_projects_none(): void
    {
        $token = $this->token();

        $r = $this->withToken($token)->postJson('/api/v2/resolve', ['name' => 'S', 'blueprint' => 'framework/project'])->json();
        $this->assertNotContains('framework/uv', array_column($r['layers'], 'key'));
        $this->assertArrayHasKey('source', $r, 'An R project from an unchanged blueprint still pins it');

        $python = $this->withToken($token)->postJson('/api/v2/resolve', ['name' => 'S', 'blueprint' => 'framework/project', 'language' => 'python'])->json('layers');
        $keys = array_column($python, 'key');
        $this->assertContains('framework/uv', $keys);
        $this->assertContains('framework/quarto', $keys);
        $this->assertContains('framework/framework', $keys, 'Each package ships its own Framework skills');
        $this->assertNotContains('framework/ggplot2', $keys, 'ggplot2 is for R');

        $bare = array_column($this->withToken($token)->postJson('/api/v2/resolve', ['name' => 'S', 'blueprint' => 'framework/bare', 'language' => 'python'])->json('layers'), 'key');
        $this->assertSame(['framework/bare', 'framework/uv', 'framework/bare', 's'], $bare);
    }

    public function test_jupyter_brings_the_kernel_for_the_projects_language(): void
    {
        $token = $this->token();

        $r = collect($this->withToken($token)->postJson('/api/v2/resolve', ['structure' => 'framework/project', 'tools' => ['jupyter' => true]])->json('layers'))->keyBy('key');
        $this->assertArrayHasKey('IRkernel', $r['framework/jupyter-r']['layer']['packages']['default_packages']);

        $python = collect($this->withToken($token)->postJson('/api/v2/resolve', ['structure' => 'framework/project', 'language' => 'python', 'tools' => ['jupyter' => true, 'uv' => true]])->json('layers'))->keyBy('key');
        $this->assertSame('pypi', $python['framework/jupyter-python']['layer']['packages']['default_packages']['ipykernel']['source']);
        $this->assertArrayHasKey('framework/uv', $python);
    }

    public function test_a_project_token_cannot_list_or_resolve(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'S', 'slug' => 's']);

        $this->withToken($project->issuePullToken())->getJson('/api/v2/tools')->assertForbidden();
    }
}
