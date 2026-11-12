<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Editing a project's details and setup, and what tokens and downloads get afterwards. */
class ProjectEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlueprintSeeder::class);
        config(['services.scaffolder.url' => 'http://scaffolder:8000']);
    }

    private function blueprint(string $key = 'project'): Blueprint
    {
        return Blueprint::whereNull('user_id')->where('key', $key)->firstOrFail();
    }

    private function project(User $user): Project
    {
        $this->actingAs($user)->post('/projects', [
            'name' => 'NHANES Study', 'blueprint_id' => $this->blueprint()->id, 'language' => 'r', 'workflow' => 'quarto',
        ])->assertRedirect();

        return $user->projects()->listed()->firstOrFail();
    }

    /** The New project form's choices with the sensitive structure. */
    private function sensitiveChoices(Project $project, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'NHANES Study', 'description' => 'Survey data', 'blueprint_id' => $this->blueprint()->id,
            'language' => 'r', 'workflow' => 'quarto', 'update_setup' => true,
            'customized' => true, 'structure' => 'framework/project_sensitive', 'notebook_format' => 'quarto',
            'tools' => ['quarto' => true], 'package_sets' => [], 'skill_sets' => [], 'skills' => [], 'excluded_skills' => [],
            'settings' => ['sensitive' => true, 'git' => true, 'hooks' => ['data_security'], 'ai' => ['enabled' => true, 'assistants' => ['claude' => true]]],
        ];
    }

    public function test_the_edit_page_opens_on_the_project(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);

        $this->actingAs($user)->get("/projects/{$project->slug}/edit")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Projects/Create')
                ->where('editing.id', $project->id)
                ->where('editing.name', 'NHANES Study')
                ->where('editing.language', 'r')
                ->where('editing.blueprint_id', $this->blueprint()->id)
                ->where('editing.choices.customized', false)
                ->where('editing.choices.workflow', 'quarto')
                ->where('stage', 'project'));

        $this->actingAs(User::factory()->create())->get("/projects/{$project->slug}/edit")->assertNotFound();
    }

    public function test_editing_validates_the_details_and_the_blueprint(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);

        $this->actingAs($user)->put("/projects/{$project->id}", ['name' => '', 'blueprint_id' => $this->blueprint()->id])
            ->assertSessionHasErrors('name');
        $this->actingAs($user)->put("/projects/{$project->id}", ['name' => 'X', 'blueprint_id' => $this->blueprint()->id, 'language' => 'cobol'])
            ->assertSessionHasErrors('language');
        $this->actingAs($user)->put("/projects/{$project->id}", ['name' => 'X', 'blueprint_id' => 999999, 'update_setup' => true])
            ->assertSessionHasErrors('blueprint_id');

        $this->assertSame('NHANES Study', $project->fresh()->name);
        $this->actingAs(User::factory()->create())->put("/projects/{$project->id}", ['name' => 'Mine', 'blueprint_id' => $this->blueprint()->id])
            ->assertNotFound();
    }

    public function test_a_details_edit_keeps_the_setup_and_the_slug(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        $layers = ProjectLayers::for($project)['layers'];

        $this->actingAs($user)->put("/projects/{$project->id}", ['name' => 'NHANES Survey', 'description' => 'Waves 1 to 3', 'blueprint_id' => $this->blueprint()->id])
            ->assertRedirect("/projects/{$project->slug}")
            ->assertSessionHas('status', 'project-updated');

        $project->refresh();
        $this->assertSame('nhanes-study', $project->slug);
        $this->assertSame('NHANES Survey', $project->name);
        $this->assertSame('Waves 1 to 3', $project->description);
        // The name names files, so the envelope carries it; the layers stay
        $this->assertSame('NHANES Survey', ProjectLayers::for($project)['name']);
        $this->assertSame($layers, ProjectLayers::for($project)['layers']);
    }

    public function test_a_setup_edit_stores_new_layers_that_pulls_and_downloads_use(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        $this->assertFalse(ProjectLayers::isSensitive(ProjectLayers::for($project)['layers']));
        $token = $project->issuePullToken();

        $this->actingAs($user)->put("/projects/{$project->id}", $this->sensitiveChoices($project))
            ->assertSessionHasNoErrors()
            ->assertRedirect("/projects/{$project->slug}");

        $project->refresh();
        $this->assertSame('Survey data', $project->description);
        $this->assertNotNull($project->composition);
        $this->assertTrue($project->choices['customized']);
        $this->assertSame('framework/project_sensitive', $project->choices['structure']);
        $this->assertTrue(ProjectLayers::isSensitive(ProjectLayers::for($project)['layers']));

        // A computer pulling with an earlier token gets the new setup
        $this->app['auth']->forgetGuards();
        $pulled = $this->withToken($token)->getJson('/api/v1/projects/pull')->assertOk()->json();
        $this->assertTrue(ProjectLayers::isSensitive($pulled['layers']));

        // So does the zip
        Http::fake(['scaffolder:8000/build' => Http::response('PK', 200, ['Content-Type' => 'application/zip'])]);
        $this->actingAs($user)->get("/projects/{$project->id}/download");
        Http::assertSent(fn (HttpRequest $request) => ProjectLayers::isSensitive(json_decode($request->body(), true)['layers']['layers']));

        // The edit page opens on the saved choices
        $this->actingAs($user)->get("/projects/{$project->slug}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('editing.choices.structure', 'framework/project_sensitive')
                ->where('editing.unknown_choices', false));
    }

    public function test_a_setup_edit_can_change_the_language(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);

        $this->actingAs($user)->put("/projects/{$project->id}", [
            'name' => 'NHANES Study', 'blueprint_id' => $this->blueprint()->id, 'language' => 'python', 'workflow' => 'jupyter', 'update_setup' => true,
        ])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('python', $project->language);
        $this->assertSame('python', last(ProjectLayers::for($project)['layers'])['layer']['language']);
    }
}
