<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
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

    public function test_the_preview_combines_a_named_blueprint_with_your_defaults_and_marks_required_tools(): void
    {
        $user = User::factory()->create(['project_defaults' => ['skill_sets' => ['framework/ggplot2'], 'tools' => ['quarto_manuscript' => true]]]);

        $preview = $this->actingAs($user)->postJson('/projects/preview', ['blueprint_id' => $this->blueprint('bare')->id, 'language' => 'r'])->assertOk();

        $this->assertSame('quarto', $preview->json('workflow.key'));
        $this->assertSame(['framework/ggplot2'], array_column($preview->json('skill_sets'), 'key'));
        $this->assertSame('yours', $preview->json('skill_sets.0.from'));
        $this->assertTrue($preview->json('tools.quarto_manuscript.on'));
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

        // Leaving the lists out is different: your defaults come along
        $this->actingAs($user)->post('/projects', ['name' => 'Plain', 'blueprint_id' => $this->blueprint('project')->id, 'language' => 'r'])->assertSessionHasNoErrors();
        $plain = array_column(ProjectLayers::for($user->projects()->where('slug', 'plain')->sole())['layers'], 'key');
        $this->assertContains('framework/ggplot2', $plain);
        $this->assertContains('framework/tidyverse-analysis', $plain);
    }

    public function test_a_required_tool_cant_be_turned_off_for_a_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/projects/preview', [
            'blueprint_id' => $this->blueprint('course')->id, 'language' => 'r', 'customized' => true, 'structure' => 'framework/course', 'tools' => ['quarto' => false],
        ])->assertStatus(422)->assertJsonPath('problem', 'Quarto is required by the Quarto workflow.');
    }
}
