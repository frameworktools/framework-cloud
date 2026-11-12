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

    public function test_the_default_blueprint_page_saves_a_blueprint_for_each_language(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $standard = $this->system('project');
        $mine = $this->mine($user, 'python', 'scripts');

        $this->actingAs($user)->get('/settings/blueprint')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/DefaultBlueprint')
                ->where('language', 'r')
                // An account that never chose has the standard project everywhere
                ->where('defaults', ['r' => $standard->id, 'python' => $standard->id, 'stata' => $standard->id])
                // Framework's first, then your own, which takes only its language
                ->where('blueprints.0.name', $standard->name)
                ->where('blueprints', fn ($list) => collect($list)->firstWhere('id', $mine->id)['languages'] === ['python']
                    && collect($list)->firstWhere('id', $mine->id)['workflows'] === ['python' => 'scripts']));

        $this->actingAs($user)->patch('/settings/blueprint', [
            'language' => 'python',
            'blueprints' => ['r' => $this->system('course')->id, 'python' => $mine->id, 'stata' => $standard->id],
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'defaults-updated');

        $user = $user->fresh();
        $this->assertSame(['language' => 'python', 'blueprints' => ['r' => $this->system('course')->id, 'python' => $mine->id, 'stata' => $standard->id]], $user->blueprint_defaults);
        $this->assertSame($mine->handle, ProjectDefaults::for($user)['blueprint']);
        $this->assertSame(['r' => 'framework/course', 'python' => $mine->handle, 'stata' => 'framework/project'], ProjectDefaults::for($user)['blueprints']);
        // The packages read the default language's type and workflow when they work offline
        $doc = $user->globalSettings()->document;
        $this->assertSame('project', $doc['defaults']['project_type']);
        $this->assertSame('scripts', $doc['defaults']['workflows']['python']);

        // A new project without a blueprint takes its language's default
        $this->assertSame($mine->handle, (new Resolver($user))->resolve(['name' => 'Survey'])['blueprint']->blueprint->handle);
        $this->assertSame('python', (new Resolver($user))->resolve(['name' => 'Survey'])['language']);
        $this->assertSame('framework/course', (new Resolver($user))->resolve(['name' => 'Survey', 'language' => 'r'])['blueprint']->blueprint->handle);

        // A blueprint for another language, or someone else's, can't be the default
        $this->actingAs($user)->patch('/settings/blueprint', ['language' => 'r', 'blueprints' => ['r' => $mine->id]])->assertSessionHasErrors('blueprints.r');
        $theirs = $this->mine(User::factory()->create(), 'r', 'quarto');
        $this->actingAs($user)->patch('/settings/blueprint', ['language' => 'r', 'blueprints' => ['r' => $theirs->id]])->assertSessionHasErrors('blueprints.r');
        $this->actingAs($user)->patch('/settings/blueprint', ['language' => 'fortran', 'blueprints' => []])->assertSessionHasErrors('language');
    }

    public function test_the_old_settings_pages_lead_to_the_new_ones(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/settings')->assertRedirect('/settings/blueprint');
        foreach (['defaults', 'language', 'packages', 'scaffold', 'agents', 'quarto', 'jupyter', 'hooks'] as $old) {
            $this->get("/settings/{$old}")->assertRedirect('/settings/blueprint');
        }
        $this->get('/settings/profile')->assertRedirect('/settings/personal-data');
        $this->get('/account/profile')->assertRedirect('/settings/personal-data');
        $this->get('/account')->assertRedirect('/account/account');
    }

    public function test_personal_data_saves_a_quarto_author_block(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Ada Lovelace']);

        $this->actingAs($user)->get('/settings/personal-data')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/PersonalData')
                ->where('author.name', 'Ada Lovelace')
                // The private sign-in address is never offered as the public one
                ->where('author.email', '')
                ->where('author.affiliations', []));

        $this->actingAs($user)
            ->patch('/settings/personal-data', [
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
        $this->assertSame('ada@example.edu', $envelope['author']['email']);
        $this->assertSame('Analytical Engine Lab', $envelope['author']['affiliation']);
        $this->assertSame('0000-0002-1825-0097', $envelope['author']['orcid']);
        $this->assertCount(2, $envelope['author']['affiliations']);
        $this->assertSame([], Layer::envelopeProblems($envelope));
        // A resolved project carries it too
        $this->assertSame('ada@example.edu', (new Resolver($user->fresh()))->resolve(['name' => 'Survey'])['envelope']['author']['email']);
    }

    public function test_personal_data_rejects_a_malformed_orcid(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->patch('/settings/personal-data', ['name' => 'A', 'orcid' => '1234'])
            ->assertSessionHasErrors('orcid');
    }

    /**
     * Sets environment variables the way env() reads them. A .env file copied
     * from .env.example holds FW_ADMIN_EMAIL= in $_ENV, which would hide a
     * value set with putenv() alone. Each value is restored after the test.
     *
     * @param  array<string, string>  $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $before = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
            $this->beforeApplicationDestroyed(function () use ($key, $before) {
                $before[0] === false ? putenv($key) : putenv("{$key}={$before[0]}");
                if ($before[1] === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $before[1];
                }
                if ($before[2] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $before[2];
                }
            });
        }
    }

    public function test_production_seeder_creates_admin(): void
    {
        $this->setEnv(['FW_ADMIN_EMAIL' => 'admin@example.com']);

        $this->seed(ProductionSeeder::class);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertSame(9, Blueprint::count());
    }

    public function test_reseeding_on_every_start_leaves_the_admin_as_edited(): void
    {
        // Docker runs the seeder on every start, with FW_ADMIN_NAME often blank
        $this->setEnv(['FW_ADMIN_EMAIL' => 'ada@example.com', 'FW_ADMIN_NAME' => '']);

        $this->seed(ProductionSeeder::class);
        $user = User::where('email', 'ada@example.com')->first();
        $this->assertSame('Ada', $user->name);

        $user->update(['name' => 'Ada Lovelace']);
        $this->seed(ProductionSeeder::class);

        $this->assertSame('Ada Lovelace', $user->fresh()->name);
        $this->assertSame(1, User::count());
    }

    public function test_a_saved_bare_default_stays_and_an_unsaved_one_means_the_standard_project(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->assertSame('framework/project', ProjectDefaults::for($user)['blueprint']);

        // The type saved before per-language defaults still counts where it fits
        $setting = $user->globalSettings();
        $doc = $setting->document;
        $doc['defaults']['project_type'] = 'bare';
        $setting->replaceDocument($doc);

        $this->assertSame('framework/bare', ProjectDefaults::for($user->fresh())['blueprint']);
        $this->actingAs($user)->get('/settings/blueprint')
            ->assertInertia(fn (Assert $page) => $page->where('defaults.r', $this->system('bare')->id));
    }

    public function test_the_guided_setup_preview_resolves_a_framework_blueprint_and_saves_nothing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $revision = $user->globalSettings()->revision;

        $preview = $this->actingAs($user)->postJson('/settings/blueprint/preview', [
            'blueprint' => 'project_sensitive', 'language' => 'r', 'values' => ['workflows' => ['r' => 'scripts']],
        ])->assertOk();

        $paths = collect($preview->json('tree'))->pluck('path');
        $this->assertTrue($paths->contains('inputs/private/raw'));
        $this->assertTrue($paths->contains('scaffold.R'));
        $this->assertSame('scripts', $preview->json('workflow'));
        $this->assertTrue($preview->json('settings.sensitive.on'));

        // Stata has no presentation structure; previewing changes nothing saved
        $this->actingAs($user)->postJson('/settings/blueprint/preview', ['blueprint' => 'presentation', 'language' => 'stata'])
            ->assertOk()->assertJsonPath('available', false);
        $this->assertSame($revision, $user->globalSettings()->revision);
        $this->assertNull($user->fresh()->blueprint_defaults);
    }

    public function test_without_a_language_a_project_takes_the_blueprints_then_the_default_language(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->assertSame('r', (new Resolver($user))->resolve(['name' => 'Survey'])['language']);

        ProjectDefaults::setLanguage($user, 'stata');
        $this->assertSame('stata', (new Resolver($user->fresh()))->resolve(['name' => 'Survey'])['language']);
        // A blueprint that takes the default language uses it; one pinned to a language uses its own
        $this->assertSame('stata', (new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'blueprint' => 'framework/project'])['language']);
        $mine = $this->mine($user, 'python', 'jupyter');
        $this->assertSame('python', (new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'blueprint' => $mine->handle])['language']);
    }

    public function test_framework_settings_sit_beneath_the_blueprint_and_account_settings_no_longer_apply(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        // Values the old default settings pages saved
        $setting = $user->globalSettings();
        $doc = $setting->document;
        $doc['defaults'] = ['ide' => 'both', 'seed' => '20241107', 'ggplot_theme' => 'theme_bw', 'set_theme_on_scaffold' => true, 'use_git' => false, 'workflows' => ['r' => 'jupyter']] + $doc['defaults'];
        $doc['ai']['assistants'] = ['codex'];
        $doc['quarto'] = ['html' => ['theme' => 'cosmo']];
        $setting->replaceDocument($doc);
        $user->forceFill(['project_defaults' => ['skill_sets' => ['framework/ggplot2'], 'tools' => ['renv' => true], 'packages' => ['r' => [['name' => 'dplyr', 'source' => 'cran']]]]])->save();

        $resolved = (new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'language' => 'r']);
        $this->assertTrue($resolved['unchanged']);
        $this->assertSame('quarto', $resolved['workflow']);
        $base = $this->base($resolved['envelope']['layers']);
        $this->assertSame(['ide' => 'vscode', 'seed_on_scaffold' => true, 'seed' => '123', 'set_theme_on_scaffold' => false, 'ggplot_theme' => 'theme_minimal', 'source_all_functions' => true], $base['scaffold']);
        $this->assertSame(['claude' => false, 'codex' => false, 'copilot' => false], $base['ai']['assistants']);
        $spec = DefaultsPreview::merge($resolved['envelope']['layers']);
        $this->assertNotSame('cosmo', $spec['quarto']['html']['theme'] ?? null);
        $this->assertNotSame(false, $spec['git']['use_git'] ?? true);
        $this->assertFalse($spec['tools']['renv'] ?? false);
        // Python seeds and puts functions/ on the import path; the theme is R's. Stata has no scaffold settings
        $this->assertSame(['ide', 'seed_on_scaffold', 'seed', 'source_all_functions'], array_keys($this->base((new Resolver($user->fresh()))->resolve(['name' => 'S', 'language' => 'python'])['envelope']['layers'])['scaffold']));
        $this->assertArrayNotHasKey('scaffold', $this->base((new Resolver($user->fresh()))->resolve(['name' => 'S', 'language' => 'stata'])['envelope']['layers']));
    }

    public function test_the_blueprint_decides_agents_scaffold_quarto_and_packages(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $mine = $this->mine($user, 'r', 'quarto');
        $mine->latestVersion->update([
            'agent_defaults' => ['codex', 'copilot'],
            'ide' => 'rstudio',
            'layer' => [
                'scaffold' => ['seed' => '42'],
                'quarto' => ['html' => ['theme' => 'cosmo']],
                'packages' => ['default_packages' => ['fixest' => ['source' => 'cran', 'auto_attach' => true], 'glue' => null]],
            ] + ($mine->latestVersion->layer ?? []),
        ]);

        $resolved = (new Resolver($user))->resolve(['name' => 'Survey', 'blueprint' => $mine->handle]);
        $this->assertSame(['claude' => false, 'codex' => true, 'copilot' => true], $this->base($resolved['envelope']['layers'])['ai']['assistants']);
        $this->assertSame('rstudio', $this->base($resolved['envelope']['layers'])['scaffold']['ide']);
        $this->assertContains('survey.Rproj', array_column($this->actingAs($user)->postJson('/projects/preview', ['blueprint_id' => $mine->id, 'language' => 'r', 'name' => 'Survey'])->json('tree'), 'path'));
        $this->assertNotContains('survey.code-workspace', array_column($this->actingAs($user)->postJson('/projects/preview', ['blueprint_id' => $mine->id, 'language' => 'r', 'name' => 'Survey'])->json('tree'), 'path'));
        $spec = DefaultsPreview::merge($resolved['envelope']['layers']);
        $this->assertSame('42', $spec['scaffold']['seed']);
        $this->assertSame('cosmo', $spec['quarto']['html']['theme']);
        $this->assertSame(['source' => 'cran', 'auto_attach' => true], $spec['packages']['default_packages']['fixest']);

        // The defaults API describes the same blueprint once it's the default
        ProjectDefaults::setBlueprint($user, 'r', $mine->fresh());
        $defaults = ProjectDefaults::for($user->fresh());
        $this->assertSame(['codex', 'copilot'], $defaults['settings']['agents']);
        $this->assertSame('rstudio', $defaults['settings']['ide']);
        $this->assertSame('42', $defaults['settings']['scaffold']['seed']);
        $this->assertSame('cosmo', $defaults['settings']['quarto']['html']['theme']);
        $names = array_column($defaults['packages']['r'], 'name');
        $this->assertContains('fixest', $names);
        $this->assertNotContains('glue', $names);

        // A blueprint with agents set to none turns agent files off
        $mine->latestVersion->update(['agent_defaults' => ['none']]);
        $spec = DefaultsPreview::merge((new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'blueprint' => $mine->handle])['envelope']['layers']);
        $this->assertFalse($spec['ai']['enabled']);
    }

    public function test_each_language_uses_its_blueprints_workflow(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $resolve = fn (string $language) => (new Resolver($user->fresh()))->resolve(['name' => 'Survey', 'language' => $language]);
        $this->assertSame('jupyter', $resolve('python')['workflow']);
        $this->assertSame('quarto', $resolve('r')['workflow']);
        $this->assertSame('scripts', $resolve('stata')['workflow']);
        // Jupyter brings its tool and Quarto for rendering
        $keys = fn (array $resolved) => collect($resolved['pieces'])->pluck('facet.key')->all();
        $this->assertContains('jupyter', $keys($resolve('python')));
        $this->assertContains('quarto', $keys($resolve('python')));

        // A default blueprint for R with Jupyter leaves Python's alone
        ProjectDefaults::setBlueprint($user, 'r', $this->mine($user, 'r', 'jupyter'));
        $this->assertSame('jupyter', $resolve('r')['workflow']);
        $this->assertSame('jupyter', $resolve('python')['workflow']);

        // A creation choice the blueprint can't take is refused
        $this->expectException(ValidationException::class);
        (new Resolver($user->fresh()))->resolve(['name' => 'Talk', 'blueprint' => 'framework/presentation', 'workflow' => 'jupyter']);
    }

    private function system(string $key): Blueprint
    {
        return Blueprint::whereNull('user_id')->where('key', $key)->firstOrFail();
    }

    /** A personal blueprint for one language and workflow, copied from the standard project. */
    private function mine(User $user, string $language, string $workflow): Blueprint
    {
        $this->actingAs($user)->post("/blueprints/{$this->system('project')->id}/fork", ['language' => $language, 'workflow' => $workflow])->assertRedirect();

        return Blueprint::where('user_id', $user->id)->latest('id')->firstOrFail()->load('latestVersion');
    }

    /** Framework's settings layer in an envelope's layers. */
    private function base(array $layers): array
    {
        return collect($layers)->firstWhere('key', ProjectDefaults::BASE_LAYER)['layer'] ?? [];
    }

    public function test_the_project_defaults_page_saves_the_projects_folder_in_the_synced_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $standard = $this->system('project');
        $blueprints = ['r' => $standard->id, 'python' => $standard->id, 'stata' => $standard->id];

        $this->actingAs($user)->get('/settings/blueprint')
            ->assertInertia(fn (Assert $page) => $page->component('Settings/DefaultBlueprint')->where('projectsDir', null));

        $this->patch('/settings/blueprint', ['language' => 'r', 'blueprints' => $blueprints, 'projects_dir' => '  ~/code  '])
            ->assertSessionHasNoErrors();
        $setting = $user->fresh()->globalSettings();
        $this->assertSame('~/code', $setting->document['defaults']['projects_dir']);
        $this->assertSame(['device' => 'web', 'app' => 'framework.pub'], $setting->revisions()->latest('revision')->first()->client);
        $this->get('/settings/blueprint')->assertInertia(fn (Assert $page) => $page->where('projectsDir', '~/code'));

        // Leaving it out of the request keeps it; saving it blank removes it
        $this->patch('/settings/blueprint', ['language' => 'r', 'blueprints' => $blueprints])->assertSessionHasNoErrors();
        $this->assertSame('~/code', ProjectDefaults::projectsDir($user->fresh()));
        $this->patch('/settings/blueprint', ['language' => 'r', 'blueprints' => $blueprints, 'projects_dir' => ''])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('projects_dir', $user->fresh()->globalSettings()->document['defaults']);
        $this->assertNull(ProjectDefaults::projectsDir($user->fresh()));
    }

    public function test_the_projects_folder_must_be_a_full_path(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $standard = $this->system('project');
        $blueprints = ['r' => $standard->id, 'python' => $standard->id, 'stata' => $standard->id];

        foreach (['~', '~/code', '~/My Projects/2026', '/Users/ada/code', 'C:\\Users\\ada\\code', 'D:/work', '\\\\server\\share'] as $good) {
            $this->actingAs($user)->patch('/settings/blueprint', ['language' => 'r', 'blueprints' => $blueprints, 'projects_dir' => $good])
                ->assertSessionHasNoErrors();
            $this->assertSame($good, ProjectDefaults::projectsDir($user->fresh()), $good);
        }
        foreach (['code', './code', '../code', '~ada/code', 'C:code', "~/code\nrm", '/'.str_repeat('a', 255), ['~/code']] as $bad) {
            $this->actingAs($user)->patch('/settings/blueprint', ['language' => 'r', 'blueprints' => $blueprints, 'projects_dir' => $bad])
                ->assertSessionHasErrors('projects_dir');
        }
        $this->assertSame('\\\\server\\share', ProjectDefaults::projectsDir($user->fresh()));
    }
}
