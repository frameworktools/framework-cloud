<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlueprintCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function system(string $key): Blueprint
    {
        return Blueprint::whereNull('user_id')->where('key', $key)->firstOrFail();
    }

    public function test_the_catalog_lists_what_each_blueprint_supports_and_where_browsing_starts(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['language' => 'python']]);

        $this->actingAs($user)->get('/blueprints')->assertInertia(fn (Assert $page) => $page
            ->component('Blueprints/Index')
            ->where('language', 'python')
            ->where('workflows.python', 'jupyter')
            ->where('default_blueprint', 'framework/project')
            // Short names, Bare last
            ->where('blueprints.0.name', 'Standard')
            ->where('blueprints.4.name', 'Bare')
            // Course is Quarto only and has no Stata; Standard has Scripts only for Stata
            ->where('blueprints.3.combinations', ['r' => ['quarto'], 'python' => ['quarto']])
            ->where('blueprints.0.combinations.stata', ['scripts'])
            ->where('blueprints.0.can_be_default', true));
    }

    public function test_the_preview_resolves_the_blueprint_language_and_workflow_with_saved_defaults(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $standard = $this->system('project');
        $paths = fn (array $body) => collect($this->actingAs($user)->postJson("/blueprints/{$standard->id}/preview", $body)->assertOk()->json('tree'))->pluck('path');

        $this->assertContains('_quarto.yml', $paths(['language' => 'r', 'workflow' => 'quarto']));
        $this->assertNotContains('_quarto.yml', $paths(['language' => 'r', 'workflow' => 'scripts']));

        // Python gets no R packages
        $python = $this->actingAs($user)->postJson("/blueprints/{$standard->id}/preview", ['language' => 'python', 'workflow' => 'jupyter'])->assertOk();
        $this->assertSame('jupyter', $python->json('workflow'));
        $this->assertNotContains('dplyr', collect($python->json('packages'))->pluck('name'));

        // A combination the blueprint doesn't support says so
        $course = $this->actingAs($user)->postJson("/blueprints/{$this->system('course')->id}/preview", ['language' => 'stata', 'workflow' => 'scripts'])->assertOk();
        $this->assertFalse($course->json('available'));
    }

    public function test_set_as_default_changes_only_the_blueprint_and_that_languages_workflow(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->patch('/settings', ['project_type' => 'project', 'ide' => 'vscode', 'workflows' => ['r' => 'quarto', 'python' => 'jupyter'], 'rmarkdown' => true])->assertSessionHasNoErrors();

        $this->actingAs($user)->post("/blueprints/{$this->system('project_sensitive')->id}/default", ['language' => 'python', 'workflow' => 'scripts'])
            ->assertSessionHas('status', 'blueprint-default');
        $doc = $user->fresh()->globalSettings()->document;
        $this->assertSame('project_sensitive', $doc['defaults']['project_type']);
        $this->assertSame(['r' => 'quarto', 'python' => 'scripts', 'stata' => 'scripts'], $doc['defaults']['workflows']);
        $this->assertSame('rmarkdown', $doc['defaults']['notebook_format']);
        $this->assertSame('vscode', $doc['defaults']['ide']);

        // Only supported combinations, and only blueprints the defaults can hold
        $this->actingAs($user)->post("/blueprints/{$this->system('course')->id}/default", ['language' => 'r', 'workflow' => 'jupyter'])->assertSessionHasErrors('workflow');
        $this->actingAs($user)->post('/blueprints/'.$this->forkOf($user, 'project')->id.'/default', ['language' => 'r', 'workflow' => 'quarto'])->assertNotFound();
    }

    private function forkOf(User $user, string $key): Blueprint
    {
        $this->actingAs($user)->post("/blueprints/{$this->system($key)->id}/fork");

        return Blueprint::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_copy_and_edit_carries_the_browsed_language_and_workflow(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post("/blueprints/{$this->system('project')->id}/fork", ['language' => 'python', 'workflow' => 'scripts'])->assertRedirect();
        $copy = Blueprint::where('user_id', $user->id)->sole();
        $this->assertSame('python', $copy->language);
        $this->assertSame(['python' => 'scripts'], $copy->latestVersion->default_workflow);
        $this->assertSame(['r', 'python', 'stata'], $copy->latestVersion->languages);

        // Narrowing the copy drops a default workflow it no longer has
        $this->actingAs($user)->put("/blueprints/{$copy->id}", ['name' => 'Notebooks', 'visibility' => 'private', 'language' => 'python', 'structure' => 'framework/project', 'tools' => [], 'package_sets' => [], 'skill_sets' => [], 'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true], 'languages' => ['python'], 'workflows' => ['jupyter']])->assertSessionHasNoErrors();
        $this->assertNull($copy->fresh()->latestVersion->default_workflow);
        $copy->fresh()->latestVersion->update(['default_workflow' => ['python' => 'scripts']]);

        $this->actingAs($user)->get("/blueprints/{$copy->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('start.language', 'python')->where('start.default_workflow.python', 'scripts'));
    }

    public function test_new_project_opens_with_the_language_and_workflow_and_creates_with_them(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $standard = $this->system('project');

        $this->actingAs($user)->get("/projects/create?blueprint={$standard->id}&language=python&workflow=scripts")->assertInertia(fn (Assert $page) => $page
            ->where('selected', $standard->id)->where('start.language', 'python')->where('start.workflow', 'scripts'));

        $this->actingAs($user)->postJson('/projects/preview', ['blueprint_id' => $standard->id, 'language' => 'python', 'workflow' => 'scripts'])
            ->assertOk()->assertJsonPath('workflow.key', 'scripts');

        $this->actingAs($user)->post('/projects', ['name' => 'Survey', 'blueprint_id' => $standard->id, 'language' => 'python', 'workflow' => 'scripts'])->assertSessionHasNoErrors();
        $resolved = $user->projects()->sole()->resolved;
        $layers = collect((is_string($resolved) ? json_decode($resolved, true) : $resolved)['layers']);
        $this->assertTrue($layers->contains(fn (array $l) => str_contains((string) ($l['key'] ?? ''), 'workflow-scripts')));
        $this->assertFalse($layers->contains(fn (array $l) => str_contains((string) ($l['key'] ?? ''), 'workflow-jupyter')));
    }
}
