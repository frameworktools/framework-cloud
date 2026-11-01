<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\Layer;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
use App\Support\Resolver;
use Database\Seeders\BlueprintSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsAndAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    public function test_defaults_page_saves_into_settings_document_with_revision_bump(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/settings/blueprint')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Defaults')
                ->where('revision', 0)
                // Standard first and the default for an account that never chose; Bare last
                ->where('values.project_type', 'project')
                ->where('blueprints.0.key', 'project')
                ->where('blueprints.4.key', 'bare')
                ->where('blueprints.2.languages', ['r', 'python']));

        $this->actingAs($user)
            ->patch('/settings', [
                'project_type' => 'bare',
                'notebook_format' => 'quarto',
                'ide' => 'rstudio',
                'use_git' => true,
                'use_renv' => true,
                'seed_on_scaffold' => true,
                'ai_enabled' => true,
                'ai_canonical_file' => 'AGENTS.md',
                'ai_skills' => true,
                'package_sets' => ['framework/tidyverse-analysis'],
                'packages' => [
                    'r' => [['name' => 'dplyr', 'source' => 'cran', 'auto_attach' => true], ['name' => 'fw-helpers', 'source' => 'github', 'repo' => 'example/fw-helpers', 'auto_attach' => false]],
                    'python' => [['name' => 'pandas', 'source' => 'pypi', 'version' => '>=2', 'auto_attach' => false]],
                ],
                'skill_sets' => ['framework/ggplot2'],
                'tools' => ['targets' => true, 'renv' => true],
                'hooks' => ['data_security' => true, 'not_a_hook' => true],
            ])
            ->assertRedirect();

        // Preferred pieces stay in the cloud, out of the synced document; renv stays synced
        $this->assertSame([
            'language' => 'r',
            // Sets only load packages into the lists now, so the lists are what's kept
            'package_sets' => [],
            'packages' => [
                'r' => [
                    ['name' => 'dplyr', 'source' => 'cran', 'auto_attach' => true, 'version' => null, 'repo' => null],
                    ['name' => 'fw-helpers', 'source' => 'github', 'auto_attach' => false, 'version' => null, 'repo' => 'example/fw-helpers'],
                ],
                'python' => [['name' => 'pandas', 'source' => 'pypi', 'auto_attach' => false, 'version' => '>=2', 'repo' => null]],
            ],
            'skill_sets' => ['framework/ggplot2'],
            'tools' => ['targets' => true],
            'hooks' => ['data_security' => true],
            'ai_other' => false,
        ], $user->fresh()->project_defaults);
        $this->assertArrayNotHasKey('package_sets', $user->globalSettings()->document['defaults']);

        $setting = $user->globalSettings();
        $this->assertSame(1, $setting->revision);
        $this->assertSame('rstudio', $setting->document['defaults']['ide']);
        $this->assertSame('bare', $setting->document['defaults']['project_type']);
        $this->assertTrue($setting->document['defaults']['use_renv']);
    }

    public function test_profile_saves_a_quarto_author_block(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Ada Lovelace']);

        $this->actingAs($user)->get('/account/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Profile')
                ->where('author.name', 'Ada Lovelace')
                ->where('author.affiliations', []));

        $this->actingAs($user)
            ->patch('/account/profile', [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.edu',
                'orcid' => '0000-0002-1825-0097',
                'url' => 'https://ada.example.edu',
                'affiliations' => [
                    ['name' => 'Analytical Engine Lab', 'department' => 'Mathematics', 'city' => '', 'country' => 'UK'],
                    ['name' => 'Royal Society', 'department' => '', 'city' => '', 'country' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();

        $author = $user->globalSettings()->document['author'];
        $this->assertSame('Analytical Engine Lab', $author['affiliation']);
        $this->assertSame('0000-0002-1825-0097', $author['orcid']);
        $this->assertSame(['name' => 'Analytical Engine Lab', 'department' => 'Mathematics', 'country' => 'UK'], $author['affiliations'][0]);
        $this->assertSame(['name' => 'Royal Society'], $author['affiliations'][1]);

        // The project envelope carries the full author block and passes the pinned schema
        $envelope = ProjectLayers::for(
            $user->projects()->create(['name' => 'P', 'slug' => 'p', 'project_type' => 'bare'])
        );
        $this->assertSame('Analytical Engine Lab', $envelope['author']['affiliation']);
        $this->assertSame('0000-0002-1825-0097', $envelope['author']['orcid']);
        $this->assertCount(2, $envelope['author']['affiliations']);
        $this->assertSame([], Layer::envelopeProblems($envelope));
    }

    public function test_profile_rejects_a_malformed_orcid(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->patch('/account/profile', ['name' => 'A', 'orcid' => '1234'])
            ->assertSessionHasErrors('orcid');
    }

    public function test_production_seeder_creates_admin(): void
    {
        putenv('FW_ADMIN_EMAIL=admin@example.com');

        $this->seed(ProductionSeeder::class);
        putenv('FW_ADMIN_EMAIL');

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertSame(5, Blueprint::count());
    }

    public function test_reseeding_on_every_start_leaves_the_admin_as_edited(): void
    {
        // Docker runs the seeder on every start, with FW_ADMIN_NAME often blank
        putenv('FW_ADMIN_EMAIL=ada@example.com');
        putenv('FW_ADMIN_NAME=');

        $this->seed(ProductionSeeder::class);
        $user = User::where('email', 'ada@example.com')->first();
        $this->assertSame('Ada', $user->name);

        $user->update(['name' => 'Ada Lovelace']);
        $this->seed(ProductionSeeder::class);

        putenv('FW_ADMIN_EMAIL');
        putenv('FW_ADMIN_NAME');

        $this->assertSame('Ada Lovelace', $user->fresh()->name);
        $this->assertSame(1, User::count());
    }

    public function test_a_saved_bare_default_stays_and_an_unsaved_one_means_the_standard_project(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->assertSame('framework/project', ProjectDefaults::for($user)['blueprint']);

        $setting = $user->globalSettings();
        $doc = $setting->document;
        $doc['defaults']['project_type'] = 'bare';
        $setting->replaceDocument($doc);

        $this->assertSame('framework/bare', ProjectDefaults::for($user->fresh())['blueprint']);
        $this->actingAs($user)->get('/settings/blueprint')
            ->assertInertia(fn (Assert $page) => $page->where('values.project_type', 'bare'));
    }

    public function test_the_blueprint_preview_resolves_with_unsaved_defaults_and_saves_nothing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $revision = $user->globalSettings()->revision;

        $preview = $this->actingAs($user)->postJson('/settings/blueprint/preview', [
            'blueprint' => 'project_sensitive', 'language' => 'r',
            'values' => ['package_sets' => ['framework/tidyverse-analysis'], 'use_git' => false],
        ])->assertOk();

        $paths = collect($preview->json('tree'))->pluck('path');
        $this->assertTrue($paths->contains('inputs/private/raw'));
        $this->assertTrue($paths->contains('scaffold.R'));
        $this->assertFalse($paths->contains('.git'));
        $this->assertSame('defaults', $preview->json('package_sets.0.from'));
        $this->assertTrue($preview->json('settings.sensitive.on'));

        // Stata has no presentation structure; previewing changes nothing saved
        $this->actingAs($user)->postJson('/settings/blueprint/preview', ['blueprint' => 'presentation', 'language' => 'stata'])
            ->assertOk()->assertJsonPath('available', false);
        $this->assertSame($revision, $user->globalSettings()->revision);
        $this->assertNull($user->fresh()->project_defaults);
    }

    public function test_the_default_language_starts_as_r_and_saves_with_the_other_defaults(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/settings/language')
            ->assertInertia(fn (Assert $page) => $page->where('section', 'language')->where('values.language', 'r'));

        $this->actingAs($user)->patch('/settings', [
            'language' => 'stata', 'project_type' => 'project', 'ide' => 'positron', 'ai_canonical_file' => 'AGENTS.md',
        ])->assertSessionHasNoErrors();
        $this->assertSame('stata', $user->fresh()->project_defaults['language']);

        $this->actingAs($user)->patch('/settings', ['language' => 'fortran', 'project_type' => 'project', 'ide' => 'positron', 'ai_canonical_file' => 'AGENTS.md'])
            ->assertSessionHasErrors('language');
    }

    public function test_editor_support_decides_the_editor_files_and_goes_in_the_project_layer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $paths = fn (string $ide) => collect($this->actingAs($user)->postJson('/settings/blueprint/preview', [
            'blueprint' => 'project', 'language' => 'r', 'values' => ['ide' => $ide],
        ])->json('tree'))->pluck('path');

        $this->assertTrue($paths('rstudio')->contains('project.Rproj'));
        $this->assertFalse($paths('rstudio')->contains('project.code-workspace'));
        $this->assertTrue($paths('vscode')->contains('project.code-workspace'));
        $this->assertFalse($paths('vscode')->contains('project.Rproj'));
        $this->assertTrue($paths('both')->contains('project.Rproj') && $paths('both')->contains('project.code-workspace'));
        $this->assertFalse($paths('none')->contains('project.Rproj') || $paths('none')->contains('project.code-workspace'));

        $setting = $user->globalSettings();
        $doc = $setting->document;
        $doc['defaults']['ide'] = 'both';
        $setting->replaceDocument($doc);
        $layers = (new Resolver($user->fresh()))->resolve(['name' => 'Survey'])['envelope']['layers'];
        $this->assertSame('both', $this->personal($layers)['scaffold']['ide']);
    }

    public function test_scaffold_settings_save_to_the_synced_defaults_and_go_in_the_project_layer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/settings/scaffold')
            ->assertInertia(fn (Assert $page) => $page
                ->where('values.seed', '123')
                ->where('values.set_theme_on_scaffold', false)
                ->where('values.source_all_functions', true));

        $base = ['project_type' => 'project', 'ide' => 'vscode', 'ai_canonical_file' => 'AGENTS.md'];
        $this->actingAs($user)->patch('/settings', $base + [
            'seed_on_scaffold' => true, 'seed' => '20241107', 'set_theme_on_scaffold' => true, 'ggplot_theme' => 'theme_bw', 'source_all_functions' => false,
        ])->assertSessionHasNoErrors();

        $defaults = $user->globalSettings()->document['defaults'];
        $this->assertSame('20241107', $defaults['seed']);
        $this->assertSame('theme_bw', $defaults['ggplot_theme']);
        $this->assertTrue($defaults['set_theme_on_scaffold']);
        $this->assertFalse($defaults['source_all_functions']);

        // Your defaults layer holds what differs from Framework's own, plus the editor
        $scaffold = fn (string $language) => $this->personal((new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'language' => $language])['envelope']['layers'])['scaffold'] ?? [];
        $this->assertSame(['ide' => 'vscode', 'seed' => '20241107', 'set_theme_on_scaffold' => true, 'ggplot_theme' => 'theme_bw', 'source_all_functions' => false], $scaffold('r'));
        // Python seeds and puts functions/ on the import path; the theme is R's
        $this->assertSame(['ide' => 'vscode', 'seed' => '20241107', 'source_all_functions' => false], $scaffold('python'));
        $this->assertSame([], $scaffold('stata'));

        $this->actingAs($user)->patch('/settings', $base + ['seed' => 'abc', 'ggplot_theme' => 'theme_neon'])
            ->assertSessionHasErrors(['seed', 'ggplot_theme']);
    }

    public function test_agents_you_use_decide_the_agent_files(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/settings/agents')
            ->assertInertia(fn (Assert $page) => $page->where('values.ai_agents', []));

        $base = ['project_type' => 'project', 'ide' => 'vscode'];
        $this->actingAs($user)->patch('/settings', $base + ['ai_agents' => ['copilot', 'codex'], 'ai_canonical_file' => 'AGENTS.md'])->assertSessionHasNoErrors();
        $this->assertSame(['codex', 'copilot'], $user->globalSettings()->document['ai']['assistants']);

        $layers = (new Resolver($user->fresh()))->resolve(['name' => 'Survey'])['envelope']['layers'];
        $this->assertSame(['claude' => false, 'codex' => true, 'copilot' => true], $this->personal($layers)['ai']['assistants']);

        $tree = fn (array $values) => collect($this->actingAs($user)->postJson('/settings/blueprint/preview', [
            'blueprint' => 'project', 'language' => 'r', 'values' => $values,
        ])->json('tree'))->pluck('path');
        $paths = $tree(['ai_agents' => ['codex', 'copilot']]);
        $this->assertTrue($paths->contains('AGENTS.md') && $paths->contains('.github') && $paths->contains('.agents'));
        $this->assertFalse($paths->contains('CLAUDE.md') || $paths->contains('.claude'));
        $paths = $tree(['ai_agents' => ['claude']]);
        $this->assertTrue($paths->contains('AGENTS.md') && $paths->contains('CLAUDE.md') && $paths->contains('.claude'));
        $this->assertFalse($paths->contains('.agents'));
    }

    public function test_each_language_keeps_its_own_workflow_and_r_markdown_is_r_quartos_variant(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/settings/notebooks')
            ->assertInertia(fn (Assert $page) => $page->where('values.workflows', ['r' => 'quarto', 'python' => 'jupyter', 'stata' => 'scripts'])->where('values.rmarkdown', false));

        $base = ['project_type' => 'project', 'ide' => 'vscode'];
        $resolve = fn (string $language) => (new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'language' => $language]);
        $this->assertSame('jupyter', $resolve('python')['workflow']);
        $this->assertSame('quarto', $resolve('r')['workflow']);
        $this->assertSame('scripts', $resolve('stata')['workflow']);
        // Jupyter brings its tool and Quarto for rendering
        $keys = fn (array $resolved) => collect($resolved['pieces'])->pluck('facet.key')->all();
        $this->assertContains('jupyter', $keys($resolve('python')));
        $this->assertContains('quarto', $keys($resolve('python')));

        // Choosing Jupyter for R leaves Python's alone
        $this->actingAs($user)->patch('/settings', $base + ['workflows' => ['r' => 'jupyter', 'python' => 'scripts']])->assertSessionHasNoErrors();
        $this->assertSame(['r' => 'jupyter', 'python' => 'scripts', 'stata' => 'scripts'], $user->globalSettings()->document['defaults']['workflows']);
        $this->assertSame('jupyter', $resolve('r')['workflow']);
        $this->assertNotContains('quarto', $keys($resolve('python')));

        // R Markdown only under R's Quarto workflow
        $this->actingAs($user)->patch('/settings', $base + ['workflows' => ['r' => 'quarto'], 'rmarkdown' => true])->assertSessionHasNoErrors();
        $this->assertSame('rmarkdown', $user->globalSettings()->document['defaults']['notebook_format']);
        $this->assertSame('rmarkdown', $this->personal($resolve('r')['envelope']['layers'])['scaffold']['notebook_format']);
        $this->actingAs($user)->patch('/settings', $base + ['workflows' => ['r' => 'scripts'], 'rmarkdown' => true])->assertSessionHasNoErrors();
        $this->assertSame('quarto', $user->globalSettings()->document['defaults']['notebook_format']);

        $this->actingAs($user)->patch('/settings', $base + ['workflows' => ['stata' => 'jupyter']])->assertSessionHasErrors('workflows.stata');

        // A creation choice the blueprint can't take is refused
        $this->expectException(ValidationException::class);
        (new Resolver($user->fresh()))->resolve(['name' => 'Talk', 'blueprint' => 'framework/presentation', 'workflow' => 'jupyter']);
    }

    public function test_quarto_settings_save_in_r_shape_and_go_to_r_and_python_projects(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/settings/quarto')
            ->assertInertia(fn (Assert $page) => $page
                ->where('values.quarto.html.toc_depth', 3)
                ->where('values.quarto.revealjs.transition', 'slide')
                ->where('blueprints.0.render_dirs', fn ($dirs) => collect($dirs)->contains(['from' => 'notebooks', 'to' => 'outputs/notebooks'])));

        $this->actingAs($user)->patch('/settings', [
            'project_type' => 'project', 'ide' => 'vscode',
            'quarto' => ['html' => ['theme' => 'cosmo', 'code_fold' => true, 'toc_depth' => 2], 'revealjs' => ['transition' => 'zoom', 'theme' => 'not-a-theme']],
        ])->assertSessionHasNoErrors();

        $quarto = $user->globalSettings()->document['quarto'];
        $this->assertSame('cosmo', $quarto['html']['theme']);
        $this->assertTrue($quarto['html']['code_fold']);
        $this->assertSame(2, $quarto['html']['toc_depth']);
        $this->assertSame('zoom', $quarto['revealjs']['transition']);
        // An unknown choice keeps R's default
        $this->assertSame('default', $quarto['revealjs']['theme']);

        $layer = fn (string $language) => $this->personal((new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'language' => $language])['envelope']['layers']);
        $this->assertSame('cosmo', $layer('r')['quarto']['html']['theme']);
        $this->assertSame('zoom', $layer('python')['quarto']['revealjs']['transition']);
        $this->assertArrayNotHasKey('quarto', $layer('stata'));
    }

    public function test_default_packages_go_into_new_projects_without_a_blueprint(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['packages' => [
            'r' => [['name' => 'dplyr', 'source' => 'cran', 'auto_attach' => true]],
            'python' => [['name' => 'pandas', 'source' => 'pypi', 'version' => '>=2']],
        ]]]);

        $spec = fn (string $language) => DefaultsPreview::merge((new Resolver($user))->resolve(['name' => 'Survey', 'language' => $language])['envelope']['layers']);
        $this->assertSame(['source' => 'cran', 'auto_attach' => true], $spec('r')['packages']['default_packages']['dplyr']);
        $this->assertSame(['source' => 'pypi', 'version' => '>=2', 'auto_attach' => false], $spec('python')['packages']['default_packages']['pandas']);
        $this->assertArrayNotHasKey('pandas', $spec('r')['packages']['default_packages']);

        // R starts every project with Framework's own list, so what the person took out goes as a null
        $layers = (new Resolver($user))->resolve(['name' => 'Survey', 'language' => 'r'])['envelope']['layers'];
        $personal = $this->personal($layers);
        $this->assertArrayHasKey('glue', $personal['packages']['default_packages']);
        $this->assertNull($personal['packages']['default_packages']['glue']);

        // A list left as Framework's own sends nothing, and the blueprint stays pinned
        $plain = User::factory()->create();
        $this->assertTrue((new Resolver($plain))->resolve(['name' => 'X'])['unchanged']);

        // Your packages come along with a chosen blueprint too
        $chosen = DefaultsPreview::merge((new Resolver($user))->resolve(['name' => 'Survey', 'blueprint' => 'framework/course'])['envelope']['layers']);
        $this->assertArrayHasKey('dplyr', $chosen['packages']['default_packages']);

        // An account with default package sets from before sees them as its lists
        $old = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['package_sets' => ['framework/tidyverse-analysis']]]);
        $this->actingAs($old)->get('/settings/packages')
            ->assertInertia(fn (Assert $page) => $page->where('values.packages.r', fn ($rows) => collect($rows)->contains('name', 'dplyr')));
    }

    /** The person's defaults layer in an envelope's layers. */
    private function personal(array $layers): array
    {
        return collect($layers)->firstWhere('kind', 'settings')['layer'] ?? [];
    }
}
