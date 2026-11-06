<?php

namespace Tests\Feature\Api\V1;

use App\Models\Facet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_project_token_cannot_save_structures(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'S', 'slug' => 's']);
        $this->withToken($project->issuePullToken())->postJson('/api/v1/structures', ['name' => 'X', 'directories' => []])->assertForbidden();
    }
}
