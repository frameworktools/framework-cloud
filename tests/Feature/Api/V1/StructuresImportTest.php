<?php

namespace Tests\Feature\Api\V1;

use App\Models\Facet;
use App\Models\User;
use App\Support\Layer;
use App\Support\StructureLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructuresImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_imported_layout_becomes_a_private_structure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/structures', [
            'name' => 'Clinic analysis',
            'directories' => ['inputs_raw' => 'data/raw', 'functions' => 'R', 'outputs_figures' => 'output/figures'],
            'extra_directories' => ['codebooks' => ['label' => 'codebooks', 'path' => 'docs/codebooks']],
        ])->assertCreated();

        $facet = Facet::where('user_id', $user->id)->where('kind', 'structure')->sole();
        $this->assertSame($facet->handle, $response->json('key'));
        $this->assertSame('private', $facet->visibility);
        $this->assertSame('data/raw', $facet->latestVersion->layer['directories']['inputs_raw']);
        $this->assertSame('docs/codebooks', $facet->latestVersion->layer['extra_directories']['codebooks']['path']);

        $this->postJson('/api/v1/structures', ['name' => 'Bad', 'directories' => ['Raw Data' => 'data']])->assertUnprocessable();
        $this->postJson('/api/v1/structures', ['name' => 'Bad', 'directories' => ['inputs_raw' => '../outside']])->assertUnprocessable();
    }

    public function test_package_import_can_be_refined_and_saved_in_the_folder_editor(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/structures', [
            'name' => 'Imported layout',
            'directories' => ['functions' => 'R', 'cache' => 'data/cache', 'inputs_raw' => 'data/raw', 'outputs_figures' => 'reports/figures'],
            'extra_directories' => ['codebooks' => ['label' => 'Codebooks', 'path' => 'docs/codebooks']],
        ])->assertCreated();
        $facet = Facet::where('user_id', $user->id)->sole();
        $this->actingAs($user)->get('/structures/'.$facet->id.'/edit?tab=locations')
            ->assertInertia(fn (Assert $page) => $page->component('Pieces/Edit')
                ->where('values.framework_locations', ['functions' => 'R', 'cache' => 'data/cache'])
                ->has('values.folders', 8));

        $layer = $facet->latestVersion->layer;
        $choices = StructureLayout::choices($layer);
        $folders = array_map(fn ($folder) => [...$folder, 'path' => preg_replace('#^data(?=/|$)#', 'research', $folder['path'])], $choices['folders']);
        $this->put('/structures/'.$facet->id, [
            'name' => $facet->name, 'visibility' => 'private', 'settings_yaml' => Layer::toYaml($layer),
            'folders' => $folders, 'framework_locations' => ['functions' => 'R', 'cache' => 'research/cache'],
            'files' => [], 'skills' => [],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $saved = $facet->fresh()->latestVersion->layer;
        $this->assertSame('research/raw', $saved['directories']['inputs_raw']);
        $this->assertSame('research/cache', $saved['directories']['cache']);
        $this->assertSame('reports/figures', $saved['directories']['outputs_figures']);
        $this->assertSame(['label' => 'Codebooks', 'path' => 'docs/codebooks'], $saved['extra_directories']['codebooks']);
        $this->assertSame([], Layer::problems($saved, 'structure'));
    }

    public function test_a_project_token_cannot_save_structures(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'S', 'slug' => 's']);
        $this->withToken($project->issuePullToken())->postJson('/api/v1/structures', ['name' => 'X', 'directories' => []])->assertForbidden();
    }
}
