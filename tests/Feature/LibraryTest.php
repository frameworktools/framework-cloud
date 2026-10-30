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

    private function facetPayload(array $overrides = []): array
    {
        return $overrides + [
            'kind' => 'facet',
            'name' => 'IRB data',
            'category' => 'data',
            'question' => 'Is this under an IRB protocol?',
            'visibility' => 'private',
            'settings_yaml' => "extra_directories:\n  irb:\n    label: IRB\n    path: irb\n",
            'files' => [],
            'skills' => [],
        ];
    }

    private function blueprintPayload(array $facetVersionIds, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'R01 analysis',
            'visibility' => 'private',
            'base_version_id' => $this->system(Facet::class, 'project')->latestVersion->id,
            'facet_version_ids' => $facetVersionIds,
        ];
    }

    public function test_seeder_builds_the_system_library_and_is_idempotent(): void
    {
        $this->assertSame(5, Facet::whereNull('user_id')->where('kind', 'base')->count());
        $this->assertSame(9, Facet::whereNull('user_id')->where('kind', 'facet')->count());
        $this->assertSame(5, Skill::whereNull('user_id')->count());
        $this->assertSame(5, Blueprint::systemTypes()->count());

        $versions = FacetVersion::count() + BlueprintVersion::count() + SkillVersion::count();
        $this->seed(BlueprintSeeder::class);
        $this->assertSame($versions, FacetVersion::count() + BlueprintVersion::count() + SkillVersion::count());
    }

    public function test_bases_reference_package_skills_and_v1_still_receives_their_text(): void
    {
        $layer = $this->system(Facet::class, 'project_sensitive')->latestVersion->layer;
        $this->assertSame(['package' => true], $layer['skills']['framework-sensitive-data']);

        $user = $this->user();
        $response = $this->withToken($user->createToken('r')->plainTextToken)->getJson('/api/v1/blueprints')->assertOk();

        $sensitive = collect($response->json('blueprints'))->firstWhere('key', 'project_sensitive');
        $this->assertSame(
            File::get(database_path('seeders/masters/skills/framework-sensitive-data.md')),
            $sensitive['skills']['framework-sensitive-data'],
        );
        $this->assertSame(File::get(database_path('seeders/masters/agents/project_sensitive.md')), $sensitive['agents_md']);
    }

    public function test_user_builds_a_facet_and_saving_unchanged_content_keeps_the_version(): void
    {
        $user = $this->user();
        $skill = $this->system(Skill::class, 'framework-data');

        $this->actingAs($user)->post('/facets', $this->facetPayload([
            'files' => [['path' => 'irb/protocol.md', 'content' => '# Protocol {{author}}', 'template' => true]],
            'skills' => [['name' => 'anything', 'skill_id' => $skill->id]],
        ]))->assertRedirect();

        $facet = Facet::where('user_id', $user->id)->firstOrFail();
        $layer = $facet->latestVersion->layer;
        $this->assertSame('irb-data', $facet->key);
        $this->assertSame(1, $facet->latestVersion->version);
        $this->assertSame(['package' => true], $layer['skills']['framework-data']);
        $this->assertSame(['content' => '# Protocol {{author}}', 'template' => true], $layer['files']['irb/protocol.md']);

        // The edit page round-trips the layer, so resaving it changes nothing
        $this->actingAs($user)->get("/facets/{$facet->id}/edit")->assertInertia(function (Assert $page) use (&$values) {
            $values = $page->toArray()['props']['values'];
        });
        $this->actingAs($user)->put("/facets/{$facet->id}", $this->facetPayload([
            'settings_yaml' => $values['settings_yaml'],
            'files' => $values['files'],
            'skills' => $values['skills'],
        ]))->assertRedirect();
        $this->assertSame(1, $facet->fresh()->latestVersion->version);

        $this->actingAs($user)->put("/facets/{$facet->id}", $this->facetPayload(['settings_yaml' => "packages:\n  use_renv: true\n"]))
            ->assertRedirect();
        $this->assertSame(2, $facet->fresh()->latestVersion->version);
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
            "type: project\n" => 'Only a base',
            "directories:\n  raw: ../outside\n" => 'relative path',
            "author:\n  name: Someone\n" => 'not a setting',
            "env:\n  DB_PASSWORD: hunter2\n" => 'secret',
            "env:\n  OPENAI_API_KEY: sk-123\n" => 'secret',
            "env:\n  GCP_CREDENTIALS: /keys/gcp.json\n" => 'secret',
            "packages:\n  default_packages:\n    targets:\n      source: cran\n" => 'needs auto_attach',
            "ai:\n  assistants:\n    gemini: true\n" => 'claude, codex, copilot',
            "directories: [\n" => '',
        ];

        foreach ($cases as $yaml => $message) {
            $this->actingAs($user)->from('/facets/create')
                ->post('/facets', $this->facetPayload(['settings_yaml' => $yaml]))
                ->assertSessionHasErrors('settings_yaml');

            if ($message !== '') {
                $this->assertStringContainsString($message, session('errors')->first('settings_yaml'));
            }
        }

        // An env() reference is how a credential belongs in a layer
        $this->actingAs($user)->post('/facets', $this->facetPayload([
            'settings_yaml' => "connections:\n  databases:\n    main:\n      driver: postgres\n      password: env(\"DB_PASSWORD\")\nenv:\n  DB_PASSWORD: ''\n  DB_HOST: localhost\n",
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Facet::where('user_id', $user->id)->count());
    }

    public function test_a_blueprint_pins_facet_versions_until_its_owner_updates(): void
    {
        $user = $this->user();
        $renv = $this->system(Facet::class, 'renv');
        $manuscript = $this->system(Facet::class, 'manuscript');

        $this->actingAs($user)->post('/blueprints', $this->blueprintPayload([
            $renv->latestVersion->id, $manuscript->latestVersion->id,
        ]))->assertRedirect();

        $blueprint = Blueprint::where('user_id', $user->id)->firstOrFail();
        $version = $blueprint->latestVersion;
        $this->assertSame(1, $version->version);
        $this->assertSame(['renv', 'manuscript'], $version->facetVersions->map(fn ($v) => $v->facet->key)->all());

        // A new facet version does not move the blueprint
        $pinned = $renv->latestVersion->id;
        $renv->publishVersion(['layer' => ['packages' => ['use_renv' => true, 'default_packages' => ['targets' => null]]]]);
        $this->assertSame($pinned, $blueprint->fresh()->latestVersion->facetVersions->first()->id);

        // Resubmitting the same pins keeps v1; reordering is a change
        $this->actingAs($user)->put("/blueprints/{$blueprint->id}", $this->blueprintPayload([$pinned, $manuscript->latestVersion->id]));
        $this->assertSame(1, $blueprint->fresh()->latestVersion->version);

        $this->actingAs($user)->put("/blueprints/{$blueprint->id}", $this->blueprintPayload([
            $manuscript->latestVersion->id, $renv->fresh()->latestVersion->id,
        ]));
        $latest = $blueprint->fresh()->latestVersion;
        $this->assertSame(2, $latest->version);
        $this->assertSame(['manuscript', 'renv'], $latest->facetVersions->map(fn ($v) => $v->facet->key)->all());
        $this->assertSame(2, $latest->facetVersions->last()->version);
    }

    public function test_blueprints_reject_duplicate_facets_bases_as_facets_and_hidden_facets(): void
    {
        $user = $this->user();
        $other = $this->user();
        $renv = $this->system(Facet::class, 'renv')->latestVersion->id;

        $hidden = Facet::create(['user_id' => $other->id, 'key' => 'secret', 'name' => 'Secret', 'visibility' => 'private']);
        $hiddenVersion = $hidden->publishVersion(['layer' => []])->id;

        foreach ([[$renv, $renv], [$this->system(Facet::class, 'bare')->latestVersion->id], [$hiddenVersion]] as $ids) {
            $this->actingAs($user)->from('/blueprints/create')
                ->post('/blueprints', $this->blueprintPayload($ids))
                ->assertSessionHasErrors('facet_version_ids');
        }

        $this->actingAs($user)->from('/blueprints/create')
            ->post('/blueprints', $this->blueprintPayload([], ['base_version_id' => $renv]))
            ->assertSessionHasErrors('base_version_id');

        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
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
        $this->actingAs($viewer)->put("/blueprints/{$public->id}", $this->blueprintPayload([]))->assertForbidden();

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
            [$this->system(Facet::class, 'manuscript')->latestVersion->id],
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

        $this->actingAs($user)->delete("/facets/{$facet->id}")->assertSessionHasErrors('facet');
        $this->assertNotNull($facet->fresh());
    }

    public function test_only_admins_edit_system_rows(): void
    {
        $facet = $this->system(Facet::class, 'renv');
        $payload = $this->facetPayload(['name' => 'Locked package versions', 'visibility' => 'public', 'settings_yaml' => "packages:\n  use_renv: false\n"]);

        $this->actingAs($this->user())->get("/facets/{$facet->id}/edit")->assertForbidden();
        $this->actingAs($this->user())->put("/facets/{$facet->id}", $payload)->assertForbidden();

        $admin = $this->user();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->put("/facets/{$facet->id}", $payload)->assertRedirect();
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

        // A user skill travels into a facet as a copy
        $this->actingAs($user)->post('/facets', $this->facetPayload(['skills' => [['name' => 'x', 'skill_id' => $skill->id]]]));
        $layer = Facet::where('user_id', $user->id)->firstOrFail()->latestVersion->layer;
        $this->assertSame(['content' => $content], $layer['skills']['irb-checklist']);
    }

    public function test_library_pages_render(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/blueprints/create')->assertInertia(fn (Assert $page) => $page
            ->component('Blueprints/Builder')->has('bases', 5)->has('facets', 9));
        $this->actingAs($user)->get('/facets')->assertInertia(fn (Assert $page) => $page->component('Facets/Index')->has('facets', 14));
        $this->actingAs($user)->get('/facets/create?kind=base')->assertInertia(fn (Assert $page) => $page
            ->component('Facets/Edit')->where('kind', 'base')->where('values.settings_yaml', "type: project\n"));
        $this->actingAs($user)->get('/skills')->assertInertia(fn (Assert $page) => $page->component('Skills/Index')->has('skills', 5));

        $system = $this->system(Blueprint::class, 'project_sensitive');
        $this->actingAs($user)->get("/blueprints/{$system->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Blueprints/Show')->where('version.base.handle', 'framework/project_sensitive'));
        $this->actingAs($user)->get("/skills/{$this->system(Skill::class, 'framework-data')->id}/edit")->assertOk();
    }

    public function test_sensitive_layers_say_what_assistants_are_kept_from(): void
    {
        $this->seed(BlueprintSeeder::class);

        $summary = collect(LayerSummary::of(Facet::where('key', 'restricted-data')->firstOrFail()->latestVersion->layer))
            ->pluck('items', 'label');

        $this->assertSame(['.env', 'inputs/private/', 'outputs/private/'], $summary['Kept from AI assistants']);
        $this->assertStringContainsString('Claude Code: enforced', $summary['Protection'][0]);
        $this->assertStringContainsString('guidance only', $summary['Protection'][1]);
    }
}
