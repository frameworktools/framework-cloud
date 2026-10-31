<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\BlueprintVersion;
use App\Models\Facet;
use App\Models\FacetVersion;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Models\User;
use App\Support\Layer;
use App\Support\LayerSummary;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['email_verified_at' => now()] + $attributes);
    }

    private function system(string $class, string $key)
    {
        return $class::whereNull('user_id')->where('key', $key)->firstOrFail();
    }

    /** A project structure, as the Structures editor sends it */
    private function facetPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'IRB data',
            'visibility' => 'private',
            'settings_yaml' => "type: project\nextra_directories:\n  irb:\n    label: IRB\n    path: irb\n",
            'files' => [],
            'skills' => [],
        ];
    }

    /** Choices, as the blueprint builder sends them */
    private function blueprintPayload(array $tools = [], array $overrides = []): array
    {
        return $overrides + [
            'name' => 'R01 analysis',
            'visibility' => 'private',
            'language' => 'r',
            'structure' => 'framework/project',
            'tools' => $tools,
            'package_sets' => [],
            'skill_sets' => [],
            'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
        ];
    }

    public function test_seeder_builds_the_system_library_and_is_idempotent(): void
    {
        $counts = Facet::whereNull('user_id')->get()->countBy('kind')->all();
        $this->assertEquals(['structure' => 5, 'tool' => 5, 'setting' => 1, 'skill_set' => 2, 'package_set' => 1], $counts);
        $this->assertSame(7, Skill::whereNull('user_id')->count());
        $this->assertSame(5, Blueprint::systemTypes()->count());

        $versions = FacetVersion::count() + BlueprintVersion::count() + SkillVersion::count();
        $this->seed(BlueprintSeeder::class);
        $this->assertSame($versions, FacetVersion::count() + BlueprintVersion::count() + SkillVersion::count());
    }

    public function test_bases_reference_package_skills_and_v1_still_receives_their_text(): void
    {
        $layer = $this->system(Blueprint::class, 'project_sensitive')->latestVersion->layer;
        $this->assertSame(['package' => true], $layer['skills']['framework-sensitive-data']);

        $user = $this->user();
        $response = $this->withToken($user->createToken('r')->plainTextToken)->getJson('/api/v1/blueprints')->assertOk();

        $sensitive = collect($response->json('blueprints'))->firstWhere('key', 'project_sensitive');
        $this->assertSame(
            File::get(database_path('seeders/masters/skills/framework-sensitive-data.md')),
            $sensitive['skills']['framework-sensitive-data'],
        );
        $this->assertSame(File::get(database_path('seeders/masters/agents/project_sensitive.md')), $sensitive['agents_md']);

        // Skills in skill sets reach v1 too, since it reads only the flattened shape
        $this->assertSame(File::get(database_path('seeders/masters/skills/ggplot2-exploring.md')), $sensitive['skills']['ggplot2-exploring']);
        $this->assertArrayHasKey('framework-data', $sensitive['skills']);
    }

    public function test_user_builds_a_structure_and_saving_unchanged_content_keeps_the_version(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/structures', $this->facetPayload([
            'files' => [['path' => 'irb/protocol.md', 'content' => '# Protocol {{author}}', 'template' => true]],
        ]))->assertRedirect();

        $facet = Facet::where('user_id', $user->id)->firstOrFail();
        $layer = $facet->latestVersion->layer;
        $this->assertSame('structure', $facet->kind);
        $this->assertSame('irb-data', $facet->key);
        $this->assertSame(['content' => '# Protocol {{author}}', 'template' => true], $layer['files']['irb/protocol.md']);

        // The edit page round-trips the layer, so resaving it changes nothing
        $this->actingAs($user)->get("/structures/{$facet->id}/edit")->assertInertia(function (Assert $page) use (&$values) {
            $values = $page->toArray()['props']['values'];
        });
        $this->actingAs($user)->put("/structures/{$facet->id}", $this->facetPayload([
            'settings_yaml' => $values['settings_yaml'],
            'files' => $values['files'],
        ]))->assertRedirect();
        $this->assertSame(1, $facet->fresh()->latestVersion->version);

        $this->actingAs($user)->put("/structures/{$facet->id}", $this->facetPayload(['settings_yaml' => "type: course\n"]))->assertRedirect();
        $this->assertSame(2, $facet->fresh()->latestVersion->version);

        // Packages and skills belong to sets, not structures
        $this->actingAs($user)->put("/structures/{$facet->id}", $this->facetPayload(['settings_yaml' => "type: project\npackages:\n  use_renv: true\n"]))
            ->assertSessionHasErrors('settings_yaml');
    }

    public function test_package_and_skill_sets_hold_only_their_part(): void
    {
        $user = $this->user();
        $skill = $this->system(Skill::class, 'framework-data');

        $this->actingAs($user)->post('/package-sets', ['name' => 'Survey stats', 'visibility' => 'private', 'language' => 'r', 'packages' => "survey\nsrvyr\ntidyverse/dbplyr"])
            ->assertSessionHasNoErrors();
        $set = Facet::where('user_id', $user->id)->where('kind', 'package_set')->firstOrFail();
        $this->assertSame(['r'], $set->language);
        $this->assertSame(['source' => 'cran', 'auto_attach' => true], $set->latestVersion->layer['packages']['default_packages']['survey']);
        $this->assertSame(['source' => 'github', 'repo' => 'tidyverse/dbplyr', 'auto_attach' => true], $set->latestVersion->layer['packages']['default_packages']['dbplyr']);
        $this->actingAs($user)->get("/package-sets/{$set->id}/edit")->assertInertia(fn (Assert $page) => $page->where('values.packages', "srvyr\nsurvey\ntidyverse/dbplyr"));

        $this->actingAs($user)->post('/package-sets', ['name' => 'Python', 'visibility' => 'private', 'language' => 'python', 'packages' => 'pandas'])->assertSessionHasNoErrors();
        $this->assertSame('pypi', Facet::where('key', 'python')->firstOrFail()->latestVersion->layer['packages']['default_packages']['pandas']['source']);

        $this->actingAs($user)->post('/package-sets', ['name' => 'Empty', 'visibility' => 'private', 'packages' => ''])->assertSessionHasErrors('packages');
        $this->actingAs($user)->post('/package-sets', ['name' => 'Bad', 'visibility' => 'private', 'packages' => 'not a package'])->assertSessionHasErrors('packages');

        $this->actingAs($user)->post('/skill-sets', [
            'name' => 'Data', 'visibility' => 'private', 'language' => 'r', 'packages' => 'readr',
            'skills' => [['name' => 'anything', 'skill_id' => $skill->id]],
        ])->assertSessionHasNoErrors();
        $layer = Facet::where('user_id', $user->id)->where('kind', 'skill_set')->firstOrFail()->latestVersion->layer;
        $this->assertSame(['package' => true], $layer['skills']['framework-data']);
        $this->assertArrayHasKey('readr', $layer['packages']['default_packages']);

        // Each page takes only its own kind
        $this->actingAs($user)->get("/structures/{$set->id}/edit")->assertForbidden();
    }

    public function test_every_seeded_layer_passes_the_pinned_schema(): void
    {
        $this->seed(BlueprintSeeder::class);

        foreach (FacetVersion::with('facet')->get() as $version) {
            $this->assertSame([], Layer::problems($version->layer, $version->facet->kind), $version->facet->key);
        }

        foreach (BlueprintVersion::all() as $version) {
            $this->assertSame([], Layer::problems($version->layer, 'blueprint'));
        }
    }

    public function test_layers_reject_credentials_unknown_keys_and_paths_outside_the_project(): void
    {
        $user = $this->user();

        $cases = [
            "connections:\n  databases:\n    main:\n      password: hunter2\n" => 'credential',
            "directories:\n  raw: ../outside\n" => 'relative path',
            "author:\n  name: Someone\n" => 'not a setting',
            "env:\n  DB_PASSWORD: hunter2\n" => 'secret',
            "env:\n  OPENAI_API_KEY: sk-123\n" => 'secret',
            "env:\n  GCP_CREDENTIALS: /keys/gcp.json\n" => 'secret',
            "packages:\n  use_renv: true\n" => 'package set',
            "ai:\n  assistants:\n    gemini: true\n" => 'claude, codex, copilot',
            "directories: [\n" => '',
        ];

        foreach ($cases as $yaml => $message) {
            $this->actingAs($user)->from('/structures/create')
                ->post('/structures', $this->facetPayload(['settings_yaml' => "type: project\n".$yaml]))
                ->assertSessionHasErrors('settings_yaml');

            if ($message !== '') {
                $this->assertStringContainsString($message, session('errors')->first('settings_yaml'));
            }
        }

        // An env() reference is how a credential belongs in a layer
        $this->actingAs($user)->post('/structures', $this->facetPayload([
            'settings_yaml' => "type: project\nconnections:\n  databases:\n    main:\n      driver: postgres\n      password: env(\"DB_PASSWORD\")\nenv:\n  DB_PASSWORD: ''\n  DB_HOST: localhost\n",
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Facet::where('user_id', $user->id)->count());
    }

    public function test_a_blueprint_pins_piece_versions_until_its_owner_saves_again(): void
    {
        $user = $this->user();
        $renv = $this->system(Facet::class, 'renv');

        $this->actingAs($user)->post('/blueprints', $this->blueprintPayload(['quarto' => true, 'quarto_manuscript' => true, 'renv' => true]))
            ->assertSessionHasNoErrors();

        $blueprint = Blueprint::where('user_id', $user->id)->firstOrFail();
        $version = $blueprint->latestVersion;
        $this->assertSame(1, $version->version);
        $this->assertSame(['quarto', 'quarto_manuscript', 'renv'], $version->facetVersions->map(fn ($v) => $v->facet->key)->all());

        // Saving the same choices keeps v1
        $this->actingAs($user)->put("/blueprints/{$blueprint->id}", $this->blueprintPayload(['quarto' => true, 'quarto_manuscript' => true, 'renv' => true]));
        $this->assertSame(1, $blueprint->fresh()->latestVersion->version);

        // A new piece version does not move the blueprint until its owner saves it
        $pinned = $renv->latestVersion->id;
        $renv->publishVersion(['layer' => ['packages' => ['use_renv' => true, 'default_packages' => ['targets' => null]]]]);
        $this->assertContains($pinned, $blueprint->fresh()->latestVersion->facetVersions->pluck('id'));

        $this->actingAs($user)->put("/blueprints/{$blueprint->id}", $this->blueprintPayload(['quarto' => true, 'quarto_manuscript' => true, 'renv' => true]));
        $latest = $blueprint->fresh()->latestVersion;
        $this->assertSame(2, $latest->version);
        $this->assertSame(2, $latest->facetVersions->firstWhere('facet_id', $renv->id)->version);
    }

    public function test_blueprints_reject_missing_requirements_and_hidden_pieces(): void
    {
        $user = $this->user();
        $other = $this->user(['handle' => 'other']);

        $hidden = Facet::create(['user_id' => $other->id, 'kind' => 'package_set', 'key' => 'secret', 'name' => 'Secret', 'visibility' => 'private']);
        $hidden->publishVersion(['layer' => []]);

        $this->actingAs($user)->from('/blueprints/create')->post('/blueprints', $this->blueprintPayload(['quarto_slides' => true]))
            ->assertSessionHasErrors(['tools' => 'Quarto slides needs the quarto tool.']);
        $this->actingAs($user)->from('/blueprints/create')->post('/blueprints', $this->blueprintPayload([], ['package_sets' => ['other/secret']]))
            ->assertSessionHasErrors('package_sets');
        $this->actingAs($user)->from('/blueprints/create')->post('/blueprints', $this->blueprintPayload([], ['structure' => 'framework/renv']))
            ->assertSessionHasErrors('structure');

        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
    }

    public function test_a_blueprint_records_settings_and_extra_yaml(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/blueprints', $this->blueprintPayload([], [
            'skill_sets' => ['framework/ggplot2'],
            'package_sets' => ['framework/tidyverse-analysis'],
            'settings' => ['sensitive' => true, 'ai' => true, 'git' => false],
            'extra_yaml' => "scaffold:\n  ggplot_theme: theme_minimal\n",
        ]))->assertSessionHasNoErrors();

        $version = Blueprint::where('user_id', $user->id)->firstOrFail()->latestVersion;
        $this->assertTrue($version->layer['security']['sensitive']);
        $this->assertFalse($version->layer['git']['use_git']);
        $this->assertSame('theme_minimal', $version->layer['scaffold']['ggplot_theme']);
        $this->assertContains('sensitive_data', $version->facetVersions->map(fn ($v) => $v->facet->key)->all());

        // The edit page gives the same choices back
        $this->actingAs($user)->get("/blueprints/{$version->blueprint_id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('start.skill_sets', ['framework/ggplot2'])
            ->where('start.package_sets', ['framework/tidyverse-analysis'])
            ->where('start.settings', ['sensitive' => true, 'ai' => true, 'git' => false])
            ->where('start.extra_yaml', "scaffold:\n  ggplot_theme: theme_minimal\n"));
    }

    public function test_visibility_controls_who_sees_and_who_edits(): void
    {
        $owner = $this->user(['handle' => 'erik']);
        $viewer = $this->user();
        $base = $this->system(Facet::class, 'project')->latestVersion;

        $make = function (string $visibility) use ($owner, $base) {
            $blueprint = Blueprint::create(['user_id' => $owner->id, 'key' => $visibility, 'name' => ucfirst($visibility), 'visibility' => $visibility]);
            $blueprint->publish($base, []);

            return $blueprint;
        };
        [$private, $unlisted, $public] = [$make('private'), $make('unlisted'), $make('public')];

        $this->assertSame('@erik/public', $public->handle);

        $this->actingAs($viewer)->get("/blueprints/{$private->id}")->assertNotFound();
        $this->actingAs($viewer)->get("/blueprints/{$unlisted->id}")->assertOk();
        $this->actingAs($viewer)->get("/blueprints/{$public->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/blueprints/{$public->id}", $this->blueprintPayload())->assertForbidden();

        $this->actingAs($viewer)->get('/blueprints')->assertInertia(fn (Assert $page) => $page
            ->component('Blueprints/Index')
            ->where('blueprints', fn ($rows) => collect($rows)->pluck('key')->intersect(['private', 'unlisted', 'public'])->values()->all() === ['public']));

        $this->actingAs($owner)->get("/blueprints/{$private->id}/edit")->assertOk();
    }

    public function test_forking_copies_the_composition_into_a_private_blueprint(): void
    {
        $owner = $this->user();
        $forker = $this->user();
        $renv = $this->system(Facet::class, 'renv')->latestVersion;

        $source = Blueprint::create(['user_id' => $owner->id, 'key' => 'shared', 'name' => 'Shared', 'visibility' => 'public']);
        $sourceVersion = $source->publish($this->system(Facet::class, 'course')->latestVersion, [$renv->id], ['quarto' => ['toc' => true]]);

        $this->actingAs($forker)->post("/blueprints/{$source->id}/fork")->assertRedirect();

        $copy = Blueprint::where('user_id', $forker->id)->firstOrFail();
        $this->assertSame('private', $copy->visibility);
        $this->assertSame($sourceVersion->id, $copy->forked_from_id);
        $this->assertSame($sourceVersion->base_version_id, $copy->latestVersion->base_version_id);
        $this->assertSame([$renv->id], $copy->latestVersion->facetVersions->pluck('id')->all());
        $this->assertSame(['quarto' => ['toc' => true]], $copy->latestVersion->layer);
    }

    public function test_a_project_pins_its_blueprint_version_and_v1_pull_still_works(): void
    {
        $user = $this->user();
        $blueprint = Blueprint::create(['user_id' => $user->id, 'key' => 'mine', 'name' => 'Mine', 'visibility' => 'private']);
        $version = $blueprint->publish(
            $this->system(Facet::class, 'project_sensitive')->latestVersion,
            [$this->system(Facet::class, 'quarto_manuscript')->latestVersion->id],
        );

        $this->actingAs($user)->post('/projects', ['name' => 'Vaccine uptake', 'blueprint_id' => $blueprint->id])->assertRedirect();

        $project = Project::where('slug', 'vaccine-uptake')->firstOrFail();
        $this->assertSame($version->id, $project->blueprint_version_id);
        $this->assertSame('project_sensitive', $project->project_type);

        // v1 knows nothing of facets: it serves the system type the project stands on
        auth()->forgetGuards();
        $this->withToken($project->issuePullToken())->getJson('/api/v1/project')
            ->assertOk()
            ->assertJsonPath('blueprint.key', 'project_sensitive')
            ->assertJsonPath('project.project_type', 'project_sensitive');

        // A blueprint that made projects cannot be deleted out from under them
        $this->actingAs($user)->delete("/blueprints/{$blueprint->id}")->assertSessionHasErrors('blueprint');
        $this->assertNotNull($blueprint->fresh());
    }

    public function test_a_facet_used_by_a_blueprint_cannot_be_deleted(): void
    {
        $user = $this->user();
        $facet = Facet::create(['user_id' => $user->id, 'key' => 'mine', 'name' => 'Mine', 'visibility' => 'private']);
        $facetVersion = $facet->publishVersion(['layer' => []]);

        $blueprint = Blueprint::create(['user_id' => $user->id, 'key' => 'uses-it', 'name' => 'Uses it', 'visibility' => 'private']);
        $blueprint->publish($this->system(Facet::class, 'bare')->latestVersion, [$facetVersion->id]);

        $this->actingAs($user)->delete("/structures/{$facet->id}")->assertSessionHasErrors('facet');
        $this->assertNotNull($facet->fresh());
    }

    public function test_only_admins_edit_system_rows(): void
    {
        $facet = $this->system(Facet::class, 'bare');
        $payload = $this->facetPayload(['name' => 'Bare', 'visibility' => 'public', 'settings_yaml' => "type: bare\n"]);

        $this->actingAs($this->user())->get("/structures/{$facet->id}/edit")->assertForbidden();
        $this->actingAs($this->user())->put("/structures/{$facet->id}", $payload)->assertForbidden();

        $admin = $this->user();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->put("/structures/{$facet->id}", $payload)->assertRedirect();
        $this->assertSame(2, $facet->fresh()->latestVersion->version);
        $this->assertNull($facet->fresh()->user_id);
    }

    public function test_skills_take_their_name_and_description_from_frontmatter(): void
    {
        $user = $this->user();
        $content = "---\nname: irb-checklist\ndescription: Use before sharing any output from an IRB project.\n---\n\n# IRB checklist\n";

        $this->actingAs($user)->from('/skills/create')
            ->post('/skills', ['content' => "# No frontmatter\n", 'visibility' => 'private'])
            ->assertSessionHasErrors('content');

        $this->actingAs($user)->post('/skills', ['content' => $content, 'visibility' => 'public'])->assertRedirect();

        $skill = Skill::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('irb-checklist', $skill->key);
        $this->assertSame('Use before sharing any output from an IRB project.', $skill->description);

        // Renaming means forking, so facets that reference the name keep working
        $this->actingAs($user)->from("/skills/{$skill->id}/edit")
            ->put("/skills/{$skill->id}", ['content' => str_replace('irb-checklist', 'renamed', $content), 'visibility' => 'public'])
            ->assertSessionHasErrors('content');

        // A user skill travels into a skill set as a copy
        $this->actingAs($user)->post('/skill-sets', ['name' => 'IRB', 'visibility' => 'private', 'skills' => [['name' => 'x', 'skill_id' => $skill->id]]]);
        $layer = Facet::where('user_id', $user->id)->firstOrFail()->latestVersion->layer;
        $this->assertSame(['content' => $content], $layer['skills']['irb-checklist']);
    }

    public function test_a_skill_shows_its_metadata_title_and_falls_back_to_its_name(): void
    {
        $user = $this->user();
        $content = "---\nname: irb-checklist\ndescription: Use before sharing output.\nmetadata:\n  title: \"IRB checklist\"\n  version: 2\n---\n\n# IRB\n";

        $this->actingAs($user)->post('/skills', ['content' => $content, 'visibility' => 'private'])->assertRedirect();
        $skill = Skill::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('IRB checklist', $skill->name);
        $this->assertSame('irb-checklist', $skill->key);

        // Removing the title falls back to the official name
        $this->actingAs($user)->put("/skills/{$skill->id}", ['content' => "---\nname: irb-checklist\ndescription: Use before sharing output.\n---\n", 'visibility' => 'private']);
        $this->assertSame('irb-checklist', $skill->fresh()->name);

        // The system skills carry readable titles
        $this->assertSame('Reading and saving data', Skill::whereNull('user_id')->where('key', 'framework-data')->value('name'));
    }

    public function test_library_pages_render(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/blueprints/create')->assertInertia(fn (Assert $page) => $page
            ->component('Blueprints/Builder')->has('catalog.structures', 5)->has('catalog.tools', 5)->has('catalog.skill_sets', 2));
        $this->actingAs($user)->get('/structures')->assertInertia(fn (Assert $page) => $page->component('Pieces/Index')->has('pieces', 5));
        $this->actingAs($user)->get('/package-sets')->assertInertia(fn (Assert $page) => $page->component('Pieces/Index')->has('pieces', 1));
        $this->actingAs($user)->get('/skill-sets')->assertInertia(fn (Assert $page) => $page->component('Pieces/Index')->has('pieces', 2));
        $this->actingAs($user)->get('/structures/create')->assertInertia(fn (Assert $page) => $page
            ->component('Pieces/Edit')->where('kind', 'structure')->where('values.settings_yaml', "type: project\n"));
        $this->actingAs($user)->get('/facets')->assertRedirect('/structures');
        $this->actingAs($user)->get('/skills')->assertInertia(fn (Assert $page) => $page->component('Skills/Index')->has('skills', 7));

        $system = $this->system(Blueprint::class, 'project_sensitive');
        $this->actingAs($user)->get("/blueprints/{$system->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Blueprints/Show')->where('version.base.handle', 'framework/project_sensitive'));
        $this->actingAs($user)->get("/skills/{$this->system(Skill::class, 'framework-data')->id}/edit")->assertOk();
    }

    public function test_sensitive_layers_say_what_assistants_are_kept_from(): void
    {
        $this->seed(BlueprintSeeder::class);

        $summary = collect(LayerSummary::of(Facet::where('key', 'sensitive_data')->firstOrFail()->latestVersion->layer))
            ->pluck('items', 'label');

        $this->assertSame(['.env', 'inputs/private/', 'outputs/private/'], $summary['Kept from AI assistants']);
        $this->assertStringContainsString('Claude Code: enforced', $summary['Protection'][0]);
        $this->assertStringContainsString('guidance only', $summary['Protection'][1]);
    }
}
