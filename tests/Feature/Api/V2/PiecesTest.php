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
        $this->assertSame(['framework/quarto', 'framework/quarto_manuscript', 'framework/quarto_slides', 'framework/targets', 'framework/renv'], $tools->pluck('key')->all());
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
            ->assertJsonPath('blueprints.1.tools', ['quarto'])
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

        $this->withToken($token)->postJson('/api/v2/resolve', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['structure']);

        $this->withToken($token)->postJson('/api/v2/resolve', ['structure' => 'someone/else'])
            ->assertUnprocessable()->assertJsonValidationErrors(['structure' => 'There is no project structure someone/else.']);
    }

    public function test_a_project_token_cannot_list_or_resolve(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'S', 'slug' => 's']);

        $this->withToken($project->issuePullToken())->getJson('/api/v2/tools')->assertForbidden();
    }
}
