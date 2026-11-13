<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\Resolver;
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

        $paths = fn (array $choices) => collect($this->actingAs($user)->postJson('/blueprints/preview', $choices)->assertOk()->json('tree'))->pluck('path');

        $quarto = $paths($this->choices(['workflow' => 'quarto']));
        $this->assertContains('_quarto.yml', $quarto);
        // The person's editor is theirs, so the blueprint's preview leaves it out
        $this->assertEmpty($quarto->filter(fn ($p) => str_ends_with($p, '.code-workspace')));

        $python = $paths($this->choices(['language' => 'python', 'workflow' => 'jupyter', 'tools' => []]));
        $this->assertContains('pyproject.toml', $python);

        $sensitive = $this->actingAs($user)->postJson('/blueprints/preview', $this->choices(['workflow' => 'scripts', 'structure' => 'framework/project_sensitive', 'settings' => ['sensitive' => false, 'ai' => true, 'git' => true]]))->assertOk();
        $this->assertTrue($sensitive->json('sensitive'));
    }

    public function test_the_sensitive_data_skill_follows_an_explicit_choice(): void
    {
        $user = User::factory()->create();
        $skills = function (array $choices) use ($user) {
            $resolved = (new Resolver($user))->resolve(['name' => 'Private analysis', 'language' => 'r', 'structure' => 'framework/project_sensitive'] + $choices);

            return DefaultsPreview::merge($resolved['envelope']['layers'])['skills'] ?? [];
        };

        // The choices editor selects it for a sensitive structure, with or without sets
        $this->assertNotEmpty($skills(['skill_sets' => [], 'skills' => ['framework/framework-sensitive-data']])['framework-sensitive-data'] ?? null);
        $this->assertNotEmpty($skills(['skill_sets' => ['framework/framework'], 'skills' => ['framework/framework-sensitive-data']])['framework-sensitive-data'] ?? null);
        // A removal holds even with Framework's skill sets
        $this->assertEmpty($skills(['skill_sets' => ['framework/framework'], 'skills' => [], 'excluded_skills' => ['framework/framework-sensitive-data']])['framework-sensitive-data'] ?? null);
        $this->assertEmpty($skills(['skill_sets' => [], 'skills' => [], 'excluded_skills' => ['framework/framework-sensitive-data']])['framework-sensitive-data'] ?? null);
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

        // Saving keeps one language and notebook format; requirements carry over.
        $blueprint->latestVersion->update(['requires_tools' => ['quarto']]);
        $this->actingAs($user)->put("/blueprints/{$blueprint->id}", $this->choices(['language' => 'python', 'tools' => [], 'workflow' => 'quarto']))->assertSessionHasNoErrors();
        $latest = $blueprint->fresh()->latestVersion;
        $this->assertSame(['python'], $latest->languages);
        $this->assertSame(['quarto'], $latest->workflows);
        $this->assertSame(['python' => 'quarto'], $latest->default_workflow);
        $this->assertSame(['quarto'], $latest->requires_tools);

        // The default language must be supported, and Stata needs Scripts only
        $this->actingAs($user)->post('/blueprints', $this->choices(['languages' => ['python']]))->assertSessionHasErrors('languages');
        $this->actingAs($user)->post('/blueprints', $this->choices(['languages' => ['r', 'stata']]))->assertSessionHasErrors('languages');
        $this->actingAs($user)->post('/blueprints', $this->choices(['workflows' => ['quarto', 'jupyter']]))->assertSessionHasErrors('workflows');
        $this->actingAs($user)->post('/blueprints', $this->choices(['language' => 'stata', 'workflow' => 'quarto']))->assertSessionHasErrors('workflow');
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
        // Only Framework's runtime files go: agent files and Quarto stay
        $this->assertContains('AGENTS.md', $paths);
        $this->assertContains('_quarto.yml', $paths);
    }

    public function test_stata_uses_scripts_automatically_and_rejects_another_language(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/blueprints', $this->choices(['language' => 'stata', 'tools' => []]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $this->assertSame(['stata'], $blueprint->latestVersion->languages);
        $this->assertSame(['scripts'], $blueprint->latestVersion->workflows);
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->handle, 'language' => 'stata']);
        $this->assertSame('scripts', $resolved['workflow']);
        $this->assertArrayNotHasKey('quarto', array_filter(DefaultsPreview::merge($resolved['envelope']['layers'])['tools'] ?? []));
        $this->postJson("/blueprints/{$blueprint->id}/preview", ['language' => 'r'])->assertOk()->assertJsonPath('available', false);
    }

    public function test_notebook_format_and_environment_settings_survive_save_and_preview(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'workflow' => 'quarto', 'notebook_format' => 'rmarkdown', 'tools' => ['renv' => true, 'targets' => true],
        ]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->handle]);
        $spec = DefaultsPreview::merge($resolved['envelope']['layers']);
        $this->assertSame('rmarkdown', $spec['scaffold']['notebook_format']);
        $this->assertTrue($spec['packages']['use_renv']);
        $this->assertNotContains('targets', collect($resolved['pieces'])->pluck('facet.key'));
        $this->get("/blueprints/{$blueprint->id}/edit?tab=notebooks")->assertInertia(fn ($page) => $page
            ->where('section', 'notebooks')->where('start.notebook_format', 'rmarkdown'));
        $this->put("/blueprints/{$blueprint->id}", $this->choices([
            'language' => 'python', 'workflow' => 'jupyter', 'notebook_format' => 'jupyter', 'tools' => ['uv' => true],
        ]))->assertSessionHasNoErrors();
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->fresh()->handle, 'language' => 'python']);
        $this->assertSame('jupyter', $resolved['workflow']);
        $this->assertContains('uv', collect($resolved['pieces'])->pluck('facet.key'));
    }

    public function test_builder_sections_have_direct_urls_and_a_language_aware_skill_catalog(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        foreach (['name', 'language', 'notebooks', 'structure', 'environment', 'packages', 'ai', 'skills', 'git'] as $section) {
            $this->actingAs($user)->get('/blueprints/create?tab='.$section)->assertInertia(fn ($page) => $page
                ->component('Blueprints/Builder')->where('section', $section));
        }
        $this->get('/blueprints/create?tab=unknown')->assertInertia(fn ($page) => $page->where('section', 'name'));
        $this->get('/blueprints/create?tab=ai')->assertInertia(fn ($page) => $page
            ->has('catalog.categories', 10)
            ->where('catalog.package_sets', fn ($sets) => collect($sets)->firstWhere('key', 'framework/regression-r')['category'] === 'modeling')
            ->where('catalog.skills', fn ($skills) => collect($skills)->firstWhere('key', 'framework/cleaning-data')['tags'][0]['key'] === 'data-cleaning')
            ->where('catalog.skill_sets', fn ($sets) => collect($sets)->firstWhere('key', 'framework/visualization')['members']['python'] === ['matplotlib-figures']));
    }

    public function test_individual_skills_are_saved_with_their_language_variant(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'language' => 'python', 'skills' => ['framework/cleaning-data', 'framework/model-results'],
        ]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $skills = array_filter($blueprint->latestVersion->layer['skills']);
        $this->assertSame(['cleaning-data', 'model-results'], array_keys($skills));
        foreach ($skills as $entry) {
            $this->assertSame('python', $entry['sync']['variant']);
        }
        $this->get("/blueprints/{$blueprint->id}/edit?tab=ai")->assertInertia(fn ($page) => $page
            ->where('section', 'ai')->where('start.skills', ['framework/cleaning-data', 'framework/model-results']));
    }

    public function test_skills_can_be_removed_individually_from_a_selected_set(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'skill_sets' => ['framework/framework'], 'excluded_skills' => ['framework/framework-data'],
        ]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $this->assertNull($blueprint->latestVersion->layer['skills']['framework-data']);
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->handle]);
        $skills = DefaultsPreview::merge($resolved['envelope']['layers'])['skills'];
        $this->assertArrayNotHasKey('framework-data', $skills);
        $this->assertArrayHasKey('framework-outputs', $skills);
        $this->get("/blueprints/{$blueprint->id}/edit?tab=ai")->assertInertia(fn ($page) => $page
            ->where('start.excluded_skills', fn ($keys) => collect($keys)->contains('framework/framework-data'))
            ->where('start.skills', fn ($keys) => ! collect($keys)->contains('framework/framework-data')));
    }

    public function test_context_skills_and_their_removals_survive_save_and_reload(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $skills = function (string $handle) use ($user) {
            $resolved = (new Resolver($user))->resolve(['name' => 'Survey', 'language' => 'r', 'blueprint' => $handle]);

            return array_keys(array_filter(DefaultsPreview::merge($resolved['envelope']['layers'])['skills'] ?? []));
        };

        // The editor sends the Quarto and Git skills it selected for the choices
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'workflow' => 'quarto', 'skills' => ['framework/quarto-documents', 'framework/git-workflow'],
        ]))->assertSessionHasNoErrors();
        $kept = Blueprint::where('user_id', $user->id)->sole();
        $this->assertContains('quarto-documents', $skills($kept->handle));
        $this->assertContains('git-workflow', $skills($kept->handle));
        $this->get("/blueprints/{$kept->id}/edit?tab=ai")->assertInertia(fn ($page) => $page
            ->where('start.skills', fn ($keys) => collect($keys)->contains('framework/quarto-documents') && collect($keys)->contains('framework/git-workflow')));

        // Removing them saves exclusions, which reload as removed
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'name' => 'No Git skill', 'workflow' => 'quarto', 'excluded_skills' => ['framework/git-workflow', 'framework/quarto-documents'],
        ]))->assertSessionHasNoErrors();
        $removed = Blueprint::where('user_id', $user->id)->where('name', 'No Git skill')->sole();
        $this->assertNotContains('git-workflow', $skills($removed->handle));
        $this->assertNotContains('quarto-documents', $skills($removed->handle));
        $this->get("/blueprints/{$removed->id}/edit?tab=ai")->assertInertia(fn ($page) => $page
            ->where('start.excluded_skills', fn ($keys) => collect($keys)->contains('framework/git-workflow') && collect($keys)->contains('framework/quarto-documents')));

        // A sensitive structure's skill comes back as a single skill, so the editor shows it
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'name' => 'Private', 'structure' => 'framework/project_sensitive', 'skills' => ['framework/framework-sensitive-data'],
        ]))->assertSessionHasNoErrors();
        $private = Blueprint::where('user_id', $user->id)->where('name', 'Private')->sole();
        $this->assertContains('framework-sensitive-data', $skills($private->handle));
        $this->get("/blueprints/{$private->id}/edit?tab=ai")->assertInertia(fn ($page) => $page
            ->where('start.skills', fn ($keys) => collect($keys)->contains('framework/framework-sensitive-data')));
    }

    public function test_individual_package_choices_override_overlapping_sets_and_survive_reload(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'package_sets' => ['framework/tidy-core', 'framework/tidyverse-analysis'],
            'package_overrides' => ['dplyr' => false, 'lme4' => true],
        ]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->handle]);
        $packages = DefaultsPreview::merge($resolved['envelope']['layers'])['packages']['default_packages'];
        $this->assertArrayNotHasKey('dplyr', $packages);
        $this->assertArrayHasKey('lme4', $packages);
        $this->assertArrayHasKey('tidyr', $packages);
        $this->get("/blueprints/{$blueprint->id}/edit?tab=packages")->assertInertia(fn ($page) => $page
            ->where('start.package_overrides.dplyr', false)->where('start.package_overrides.lme4', true));
        $this->postJson('/blueprints/preview', $this->choices([
            'workflow' => 'quarto', 'package_overrides' => ['not-a-catalog-package' => true],
        ]))->assertUnprocessable();
    }

    public function test_git_hooks_round_trip_and_are_removed_when_git_is_off(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/blueprints', $this->choices([
            'settings' => ['git' => true, 'ai' => true, 'hooks' => ['data_security', 'check_notebook_outputs']],
        ]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $this->get("/blueprints/{$blueprint->id}/edit?tab=git")->assertInertia(fn ($page) => $page
            ->where('start.settings.hooks', ['data_security', 'check_notebook_outputs']));
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->handle]);
        $this->assertTrue(DefaultsPreview::merge($resolved['envelope']['layers'])['git']['hooks']['data_security']);
        $this->put("/blueprints/{$blueprint->id}", $this->choices([
            'settings' => ['git' => false, 'ai' => true, 'hooks' => ['data_security']],
        ]))->assertSessionHasNoErrors();
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->handle]);
        $this->assertEmpty(array_filter(DefaultsPreview::merge($resolved['envelope']['layers'])['git']['hooks'] ?? []));
    }

    public function test_set_tags_are_controlled_and_skill_themes_are_shared_across_languages(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/blueprints/create?tab=packages')->assertInertia(fn ($page) => $page
            ->has('catalog.package_set_tags', 8)
            ->where('catalog.package_sets', function ($sets) {
                $sets = collect($sets);

                return in_array('tidyverse', $sets->firstWhere('key', 'framework/tidy-core')['tags'])
                    && in_array('statistical-modeling', $sets->firstWhere('key', 'framework/regression-r')['tags'])
                    && in_array('apps', $sets->firstWhere('key', 'framework/shiny-r')['tags'])
                    && in_array('notebooks', $sets->firstWhere('key', 'framework/tables-r')['tags'])
                    && ! in_array('notebooks', $sets->firstWhere('key', 'framework/shiny-r')['tags'])
                    && count($sets->firstWhere('key', 'framework/tidy-core')['package_members']['r']) === 3;
            })
            ->where('catalog.skill_sets', fn ($sets) => in_array('modeling', collect($sets)->firstWhere('key', 'framework/modeling')['tags'])));
    }

    public function test_agent_defaults_are_saved_separately_and_applied_without_changing_account_defaults(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $settings = $user->globalSettings();
        $document = $settings->document;
        $document['ai']['assistants'] = ['claude'];
        $settings->replaceDocument($document);
        $this->actingAs($user)->post('/blueprints', $this->choices(['agents' => ['codex']]))->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $this->assertSame(['codex'], $blueprint->latestVersion->agent_defaults);
        $this->assertArrayNotHasKey('assistants', $blueprint->latestVersion->layer['ai']);
        $spec = DefaultsPreview::merge((new Resolver($user))->resolve(['blueprint' => $blueprint->handle])['envelope']['layers']);
        $this->assertTrue($spec['ai']['assistants']['codex']);
        $this->assertFalse($spec['ai']['assistants']['claude']);
        $this->assertSame(['claude'], $user->fresh()->globalSettings()->document['ai']['assistants']);
        $this->get("/blueprints/{$blueprint->id}/edit?tab=ai")->assertInertia(fn ($page) => $page->where('start.agents', ['codex']));
        $this->put("/blueprints/{$blueprint->id}", $this->choices(['agents' => ['none']]))->assertSessionHasNoErrors();
        $spec = DefaultsPreview::merge((new Resolver($user))->resolve(['blueprint' => $blueprint->fresh()->handle])['envelope']['layers']);
        $this->assertFalse($spec['ai']['enabled']);
    }

    public function test_preview_uses_selected_agent_files_and_validates_the_agent_list(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $paths = fn ($agents) => collect($this->actingAs($user)->postJson('/blueprints/preview', $this->choices([
            'workflow' => 'quarto', 'agents' => $agents,
        ]))->assertOk()->json('tree'))->pluck('path');
        $this->assertContains('AGENTS.md', $paths(['codex']));
        $this->assertContains('CLAUDE.md', $paths(['claude']));
        $this->assertNotContains('CLAUDE.md', $paths(['codex']));
        $this->assertNotContains('AGENTS.md', $paths(['none']));
        $this->post('/blueprints', $this->choices(['agents' => ['unsupported']]))->assertSessionHasErrors('agents.0');
    }

    public function test_r_auto_attach_switches_override_sets_and_survive_reload(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->forceFill(['project_defaults' => ['packages' => ['r' => [['name' => 'dplyr', 'source' => 'cran', 'auto_attach' => true]]]]])->save();
        // Old package-set versions may still contain attachment preferences.
        $legacy = Facet::whereNull('user_id')->where('key', 'tidy-core')->firstOrFail()->latestVersion;
        $legacyLayer = $legacy->layer;
        foreach ($legacyLayer['packages']['default_packages'] as &$entry) {
            $entry['auto_attach'] = true;
        }
        unset($entry);
        $legacy->update(['layer' => $legacyLayer]);
        $choices = $this->choices([
            'package_sets' => ['framework/tidy-core'],
            'package_overrides' => ['lme4' => true],
            'package_auto_attach' => ['dplyr' => false, 'lme4' => true],
        ]);
        $this->actingAs($user)->post('/blueprints', $choices)->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();
        $resolved = (new Resolver($user))->resolve(['blueprint' => $blueprint->handle]);
        $packages = DefaultsPreview::merge($resolved['envelope']['layers'])['packages']['default_packages'];
        $this->assertFalse($packages['dplyr']['auto_attach']);
        $this->assertSame('cran', $packages['dplyr']['source']);
        $this->assertFalse($packages['tidyr']['auto_attach']);
        $this->assertTrue($packages['lme4']['auto_attach']);
        $this->get("/blueprints/{$blueprint->id}/edit?tab=packages")->assertInertia(fn ($page) => $page
            ->where('start.package_auto_attach.dplyr', false)->where('start.package_auto_attach.lme4', true)
            ->where('start.package_overrides.lme4', true)->where('start.package_overrides.tibble', true)
            ->missing('start.package_overrides.dplyr')->missing('start.package_overrides.tidyr'));
        $this->postJson('/projects/preview', ['blueprint_id' => $blueprint->id, 'language' => 'r'])->assertOk()
            ->assertJsonPath('package_auto_attach.dplyr', false)->assertJsonPath('package_auto_attach.lme4', true);

        // A remembered auto-attach switch must not restore a removed package.
        $choices['package_overrides']['dplyr'] = false;
        $choices['package_auto_attach']['dplyr'] = true;
        $this->put("/blueprints/{$blueprint->id}", $choices)->assertSessionHasNoErrors();
        $packages = DefaultsPreview::merge((new Resolver($user))->resolve(['blueprint' => $blueprint->handle])['envelope']['layers'])['packages']['default_packages'];
        $this->assertArrayNotHasKey('dplyr', $packages);
    }

    public function test_auto_attach_is_rejected_for_python_and_stata(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        foreach (['python' => 'jupyter', 'stata' => 'scripts'] as $language => $workflow) {
            $choices = $this->choices(['language' => $language, 'workflow' => $workflow, 'tools' => [], 'package_auto_attach' => ['example' => true]]);
            $this->actingAs($user)->post('/blueprints', $choices)->assertSessionHasErrors('package_auto_attach');
            $this->postJson('/blueprints/preview', $choices)->assertUnprocessable()->assertJsonPath('problem', 'Auto-attach is available for R only. Python and Stata do not auto-import packages.');
        }
    }
}
