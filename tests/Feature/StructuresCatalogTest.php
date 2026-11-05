<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StructuresCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_structures_list_in_order_with_their_own_files_and_what_renders_where(): void
    {
        $this->seed(BlueprintSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/structures')->assertInertia(function (Assert $page) {
            $page->component('Pieces/Structures');
            $structures = collect($page->toArray()['props']['structures']);
            $this->assertSame(['Standard', 'Privacy sensitive', 'Presentation', 'Course', 'Bare'], $structures->pluck('name')->all());

            foreach ($structures as $structure) {
                $this->assertStringContainsString('**/private/**', $structure['gitignore_content']);
                $this->assertStringContainsString('.env', $structure['gitignore_content']);
            }

            $tree = fn (string $key) => collect($structures->firstWhere('key', $key)['preview']['tree'])->keyBy('path');
            $standard = $tree('project');
            $this->assertSame('R only', $standard['scaffold.R']['when']);
            $this->assertSame('Python only', $standard['scaffold.py']['when']);
            $this->assertSame('with Quarto', $standard['_quarto.yml']['when']);
            // Editor, agent, and Git files aren't the structure's
            $this->assertFalse($standard->has('AGENTS.md') || $standard->has('.git') || $standard->has('project.Rproj'));

            // Bare has no folders
            $this->assertSame([], $tree('bare')->where('type', 'dir')->keys()->all());

            $sensitive = $structures->firstWhere('key', 'project_sensitive')['preview'];
            $this->assertTrue($sensitive['sensitive']);
            $this->assertContains(['from' => 'notebooks', 'to' => 'outputs/private/notebooks'], $sensitive['renders']);
        });
    }
}
