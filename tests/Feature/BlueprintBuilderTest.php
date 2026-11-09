<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function choices(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Survey analysis',
            'visibility' => 'private',
            'language' => 'r',
            'structure' => 'framework/project',
            'tools' => ['quarto' => true],
            'package_sets' => [],
            'skill_sets' => [],
            'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
        ];
    }

    public function test_the_preview_shows_the_tree_from_unsaved_choices_without_anyones_defaults(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->patch('/settings', ['project_type' => 'project', 'ide' => 'vscode'])->assertSessionHasNoErrors();

        $paths = fn (array $choices) => collect($this->actingAs($user)->postJson('/blueprints/preview', $choices)->assertOk()->json('tree'))->pluck('path');

        $quarto = $paths($this->choices(['workflow' => 'quarto']));
        $this->assertContains('_quarto.yml', $quarto);
        // The person's editor is theirs, so the blueprint's preview leaves it out
        $this->assertEmpty($quarto->filter(fn ($p) => str_ends_with($p, '.code-workspace')));

        $python = $paths($this->choices(['language' => 'python', 'workflow' => 'jupyter', 'tools' => []]));
        $this->assertContains('pyproject.toml', $python);

        $sensitive = $this->actingAs($user)->postJson('/blueprints/preview', $this->choices(['workflow' => 'scripts', 'settings' => ['sensitive' => true, 'ai' => true, 'git' => true]]))->assertOk();
        $this->assertTrue($sensitive->json('sensitive'));
    }

    public function test_what_a_blueprint_supports_is_saved_checked_and_kept(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Python with Jupyter only
        $this->actingAs($user)->post('/blueprints', $this->choices(['language' => 'python', 'tools' => [], 'languages' => ['python'], 'workflows' => ['jupyter']]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $this->assertSame(['python'], $blueprint->latestVersion->languages);
        $this->assertSame(['jupyter'], $blueprint->latestVersion->workflows);

        // The builder starts from what it supports
        $this->actingAs($user)->get("/blueprints/{$blueprint->id}/edit")->assertInertia(fn ($page) => $page
            ->where('start.languages', ['python'])->where('start.workflows', ['jupyter']));

        // Everything is stored as no restriction, and requirements carry over a save
        $blueprint->latestVersion->update(['requires_tools' => ['quarto']]);
        $this->actingAs($user)->put("/blueprints/{$blueprint->id}", $this->choices(['language' => 'python', 'tools' => [], 'languages' => ['r', 'python', 'stata'], 'workflows' => ['quarto', 'jupyter', 'scripts']]))->assertSessionHasNoErrors();
        $latest = $blueprint->fresh()->latestVersion;
        $this->assertNull($latest->languages);
        $this->assertNull($latest->workflows);
        $this->assertSame(['quarto'], $latest->requires_tools);

        // The default language must be supported, and Stata needs Scripts only
        $this->actingAs($user)->post('/blueprints', $this->choices(['languages' => ['python']]))->assertSessionHasErrors('languages');
        $this->actingAs($user)->post('/blueprints', $this->choices(['languages' => ['r', 'stata'], 'workflows' => ['quarto']]))->assertSessionHasErrors('workflows');
    }

    public function test_a_blueprint_can_leave_out_the_core_files_and_split_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $paths = collect($this->actingAs($user)->postJson('/blueprints/preview', $this->choices([
            'workflow' => 'quarto', 'extra_yaml' => "core_files: false\nsettings_layout: flat\n",
        ]))->assertOk()->json('tree'))->pluck('path');

        $this->assertContains('settings.yml', $paths);
        $this->assertNotContains('scaffold.R', $paths);
        $this->assertNotContains('.env', $paths);
        $this->assertEmpty($paths->filter(fn ($p) => str_starts_with($p, 'settings/')));
    }
}
