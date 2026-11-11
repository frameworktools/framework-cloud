<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\BlueprintInput;
use App\Support\Catalog;
use App\Support\ProjectDefaults;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blueprint editor's settings tabs: Scaffold, Quarto, Jupyter, Editor
 * support, and Publishing. Scaffold, Quarto, and publishing are layer keys;
 * the editor is versioned metadata beside agent_defaults.
 */
class BlueprintTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function choices(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Survey analysis',
            'visibility' => 'private',
            'language' => 'r',
            'workflow' => 'quarto',
            'notebook_format' => 'quarto',
            'structure' => 'framework/project',
            'tools' => ['quarto' => true],
            'package_sets' => [],
            'skill_sets' => [],
            'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
            'scaffold' => [
                'seed_on_scaffold' => true,
                'seed' => '2024',
                'set_theme_on_scaffold' => true,
                'ggplot_theme' => 'theme_bw',
                'source_all_functions' => false,
            ],
            'quarto' => [
                'html' => ['theme' => 'cosmo', 'toc' => true, 'toc_depth' => 2, 'code_fold' => true, 'code_tools' => false, 'embed_resources' => false, 'highlight_style' => 'tango'],
                'revealjs' => ['theme' => 'moon', 'incremental' => true, 'slide_number' => false, 'controls' => true, 'progress' => true, 'center' => false, 'transition' => 'fade', 'background_transition' => 'zoom', 'highlight_style' => 'github'],
            ],
            'ide' => 'both',
            'publish_destination' => 'github',
        ];
    }

    public function test_settings_tabs_save_to_the_layer_and_metadata_and_reopen(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/blueprints', $this->choices())->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->sole()->latestVersion;

        $this->assertSame('both', $version->ide);
        $this->assertSame([
            'seed_on_scaffold' => true, 'seed' => '2024', 'set_theme_on_scaffold' => true, 'ggplot_theme' => 'theme_bw', 'source_all_functions' => false,
        ], array_intersect_key($version->layer['scaffold'], array_flip(['seed_on_scaffold', 'seed', 'set_theme_on_scaffold', 'ggplot_theme', 'source_all_functions'])));
        $this->assertSame('cosmo', $version->layer['quarto']['html']['theme']);
        $this->assertSame(2, $version->layer['quarto']['html']['toc_depth']);
        $this->assertSame('moon', $version->layer['quarto']['revealjs']['theme']);
        $this->assertSame(['default' => 'github', 'destinations' => ['github' => ['driver' => 'github']]], $version->layer['publish']);
        $this->assertArrayNotHasKey('ide', $version->layer['scaffold']);

        $this->get("/blueprints/{$version->blueprint_id}/edit?tab=quarto")->assertOk()->assertInertia(fn ($page) => $page
            ->where('section', 'quarto')
            ->where('start.ide', 'both')
            ->where('start.publish_destination', 'github')
            ->where('start.scaffold.seed', '2024')
            ->where('start.scaffold.ggplot_theme', 'theme_bw')
            ->where('start.scaffold.source_all_functions', false)
            ->where('start.quarto.html.theme', 'cosmo')
            ->where('start.quarto.revealjs.transition', 'fade')
            // The tabs hold these, so the extra settings YAML doesn't repeat them
            ->where('start.extra_yaml', '')
            ->has('ggplotThemes')->has('quartoChoices'));
    }

    public function test_saving_the_same_values_keeps_the_version_and_a_change_makes_one(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/blueprints', $this->choices())->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();

        $this->put("/blueprints/{$blueprint->id}", $this->choices())->assertSessionHasNoErrors();
        $this->assertSame(1, $blueprint->fresh()->latestVersion->version);

        $this->put("/blueprints/{$blueprint->id}", $this->choices(['ide' => 'rstudio', 'publish_destination' => '']))->assertSessionHasNoErrors();
        $latest = $blueprint->fresh()->latestVersion;
        $this->assertSame(2, $latest->version);
        $this->assertSame('rstudio', $latest->ide);
        $this->assertArrayNotHasKey('publish', $latest->layer);
    }

    public function test_clients_that_send_no_settings_keep_the_editor_choice_and_yaml_settings(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/blueprints', $this->choices())->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $choices = Catalog::choices($blueprint->latestVersion, $user);

        // An API client sends choices as Catalog::choices gives them: the settings ride in extra_yaml
        $this->put("/blueprints/{$blueprint->id}", collect($this->choices())->except(['scaffold', 'quarto', 'ide', 'publish_destination'])->all() + ['extra_yaml' => $choices['extra_yaml']])->assertSessionHasNoErrors();
        $latest = $blueprint->fresh()->latestVersion;
        $this->assertSame('both', $latest->ide);
        $this->assertSame('theme_bw', $latest->layer['scaffold']['ggplot_theme']);
        $this->assertSame('cosmo', $latest->layer['quarto']['html']['theme']);
        $this->assertSame('github', $latest->layer['publish']['default']);
    }

    public function test_each_language_keeps_only_the_settings_it_reads(): void
    {
        $user = $this->user();
        $python = $this->choices(['name' => 'Python study', 'language' => 'python', 'workflow' => 'jupyter', 'notebook_format' => 'jupyter', 'tools' => [], 'ide' => 'positron']);
        $this->actingAs($user)->post('/blueprints', $python)->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->where('name', 'Python study')->sole()->latestVersion;
        $this->assertSame(['seed_on_scaffold', 'seed', 'source_all_functions'], array_values(array_diff(array_keys($version->layer['scaffold']), ['notebook_format', 'workflow'])));
        $this->assertSame('vscode', $version->ide);
        $this->assertSame('cosmo', $version->layer['quarto']['html']['theme']);

        $stata = $this->choices(['name' => 'Stata study', 'language' => 'stata', 'workflow' => 'scripts', 'notebook_format' => null, 'tools' => []]);
        $this->post('/blueprints', $stata)->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->where('name', 'Stata study')->sole()->latestVersion;
        $this->assertNull($version->ide);
        $this->assertNull($version->layer['quarto']['html']['theme'] ?? null);
        $this->assertEmpty(array_intersect(['seed', 'seed_on_scaffold', 'ggplot_theme'], array_keys($version->layer['scaffold'] ?? [])));
        $this->assertSame('github', $version->layer['publish']['default']);
    }

    public function test_invalid_settings_are_refused(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/blueprints', $this->choices(['ide' => 'emacs']))->assertSessionHasErrors('ide');
        $this->post('/blueprints', $this->choices(['publish_destination' => 's3']))->assertSessionHasErrors('publish_destination');
        $this->post('/blueprints', $this->choices(['scaffold' => ['seed' => 'abc']]))->assertSessionHasErrors('scaffold.seed');
        $this->post('/blueprints', $this->choices(['scaffold' => ['ggplot_theme' => 'theme_fancy']]))->assertSessionHasErrors('scaffold.ggplot_theme');
        $this->post('/blueprints', $this->choices(['quarto' => ['html' => ['theme' => 'neon']]]))->assertSessionHasErrors('quarto.html.theme');
        $this->post('/blueprints', $this->choices(['quarto' => ['html' => ['toc_depth' => 9]]]))->assertSessionHasErrors('quarto.html.toc_depth');
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
    }

    public function test_a_copy_keeps_the_settings(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/blueprints', $this->choices())->assertSessionHasNoErrors();
        $source = Blueprint::where('user_id', $user->id)->sole();

        $this->post("/blueprints/{$source->id}/fork", ['language' => 'r', 'workflow' => 'quarto'])->assertRedirect();
        $copy = Blueprint::where('user_id', $user->id)->where('id', '!=', $source->id)->sole();
        $this->assertSame('both', $copy->latestVersion->ide);
        $this->assertSame($source->latestVersion->layer, $copy->latestVersion->layer);

        $this->get("/blueprints/create?from={$source->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('start.ide', 'both')->where('start.scaffold.seed', '2024')->where('start.publish_destination', 'github'));
    }

    public function test_a_new_blueprint_starts_from_the_persons_defaults(): void
    {
        $user = $this->user();
        $defaults = ProjectDefaults::for($user);

        $this->actingAs($user)->get('/blueprints/create?tab=scaffold')->assertOk()->assertInertia(fn ($page) => $page
            ->where('section', 'scaffold')
            ->where('start.ide', BlueprintInput::ide($defaults['settings']['ide']))
            ->where('start.scaffold', $defaults['settings']['scaffold'])
            ->where('start.quarto', $defaults['settings']['quarto'])
            ->where('start.publish_destination', (string) $defaults['publish_destination']));
    }

    public function test_the_preview_shows_the_blueprints_editor_files(): void
    {
        $user = $this->user();
        $paths = fn (string $ide) => collect($this->actingAs($user)->postJson('/blueprints/preview', $this->choices(['ide' => $ide]))->assertOk()->json('tree'))->pluck('path');

        $this->assertNotEmpty($paths('vscode')->filter(fn ($p) => str_ends_with($p, '.code-workspace')));
        $this->assertEmpty($paths('rstudio')->filter(fn ($p) => str_ends_with($p, '.code-workspace')));
        $this->assertNotEmpty($paths('rstudio')->filter(fn ($p) => str_ends_with($p, '.Rproj')));
    }

    public function test_jupyter_format_saves_with_the_jupyter_switch_off(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/blueprints', $this->choices(['workflow' => 'jupyter', 'notebook_format' => 'jupyter', 'tools' => ['jupyter' => true]]))->assertSessionHasNoErrors();
        $this->post('/blueprints', $this->choices(['name' => 'Quarto with Jupyter', 'tools' => ['quarto' => true, 'jupyter' => true]]))->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->where('name', 'Quarto with Jupyter')->sole()->latestVersion;
        $this->assertTrue((bool) (Catalog::choices($version, $user)['tools']['jupyter'] ?? false));
    }
}
