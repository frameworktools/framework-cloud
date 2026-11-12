<?php

namespace Tests\Feature;

use App\Models\Facet;
use App\Models\User;
use App\Support\Layer;
use App\Support\ProjectTree;
use App\Support\StructureLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StructureLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_folders_save_without_input_or_output_roles(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/structures', [
            'name' => 'My layout', 'visibility' => 'private', 'settings_yaml' => 'type: project',
            'folders' => [['path' => 'data'], ['path' => 'reports'], ['path' => '.cache']],
            'framework_locations' => ['cache' => '.cache'], 'files' => [], 'skills' => [],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $layer = Facet::where('user_id', $user->id)->sole()->latestVersion->layer;
        $this->assertSame(['cache' => '.cache'], $layer['directories']);
        $this->assertEqualsCanonicalizing(['data', 'reports', '.cache'], array_column($layer['extra_directories'], 'path'));
        $this->assertSame([], Layer::problems($layer, 'structure'));
        $tree = collect(ProjectTree::for($layer, 'r', [], false))->keyBy('path');
        $this->assertSame('dir', $tree['.cache']['type']);
        $this->actingAs($user)->get('/structures/'.Facet::where('user_id', $user->id)->sole()->id.'/edit')->assertOk();
    }

    public function test_renaming_preserves_legacy_roles_render_paths_and_other_settings(): void
    {
        $layer = ['type' => 'project', 'directories' => ['inputs_raw' => 'inputs/raw', 'notebooks' => 'notebooks', 'functions' => 'functions'], 'render_dirs' => ['notebooks' => 'outputs/reports'], 'quarto' => ['render_dir' => 'outputs/reports'], 'scaffold' => ['ide' => 'none']];
        $choices = StructureLayout::choices($layer);
        $folders = array_map(fn ($folder) => [...$folder, 'path' => str_replace(['inputs', 'outputs'], ['data', 'results'], $folder['path'])], $choices['folders']);
        $saved = StructureLayout::apply($layer, $folders, ['functions' => 'functions']);
        $this->assertSame('data/raw', $saved['directories']['inputs_raw']);
        $this->assertSame('results/reports', $saved['render_dirs']['notebooks']);
        $this->assertSame('results/reports', $saved['quarto']['render_dir']);
        $this->assertSame(['ide' => 'none'], $saved['scaffold']);
        $this->assertSame([], Layer::problems($saved, 'structure'));
    }

    public function test_invalid_paths_and_missing_mappings_are_rejected(): void
    {
        foreach (['../outside', '/tmp', 'a//b', 'C:/folder', 'a\\b'] as $path) {
            try {
                StructureLayout::apply([], [['path' => $path]], []);
                $this->fail('Accepted '.$path);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('folders.0.path', $e->errors());
            }
        }
        $this->expectException(ValidationException::class);
        StructureLayout::apply([], [], ['functions' => 'missing']);
    }

    public function test_removing_folders_removes_legacy_roles_and_render_routes(): void
    {
        $saved = StructureLayout::apply(['directories' => ['notebooks' => 'notebooks'], 'render_dirs' => ['notebooks' => 'reports']], [], []);
        $this->assertSame([], $saved['directories']);
        $this->assertSame([], $saved['render_dirs']);
        $this->assertSame([], $saved['extra_directories']);
    }
}
