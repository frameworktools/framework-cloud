<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsAndAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BlueprintSeeder::class);
    }

    public function test_defaults_page_saves_into_settings_document_with_revision_bump(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/settings/defaults')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Defaults')
                ->where('revision', 0));

        $this->actingAs($user)
            ->patch('/settings/defaults', [
                'author_name' => 'Erik Westlund',
                'project_type' => 'bare',
                'notebook_format' => 'quarto',
                'ide' => 'rstudio',
                'use_git' => true,
                'use_renv' => true,
                'seed_on_scaffold' => true,
                'ai_enabled' => true,
                'ai_canonical_file' => 'AGENTS.md',
                'ai_skills' => true,
            ])
            ->assertRedirect();

        $setting = $user->globalSettings();
        $this->assertSame(1, $setting->revision);
        $this->assertSame('Erik Westlund', $setting->document['author']['name']);
        $this->assertSame('rstudio', $setting->document['defaults']['ide']);
        $this->assertSame('bare', $setting->document['defaults']['project_type']);
        $this->assertTrue($setting->document['defaults']['use_renv']);
    }

    public function test_admin_page_is_forbidden_for_non_admins(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/admin/blueprints')->assertForbidden();
    }

    public function test_admin_can_edit_a_blueprint_master(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/admin/blueprints')
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Blueprints')->has('blueprints', 5));

        $blueprint = Blueprint::where('key', 'project')->first();
        $skills = $blueprint->skills;
        $skills['framework-custom'] = "---\nname: framework-custom\n---\ncustom";

        $this->actingAs($admin)
            ->patch("/admin/blueprints/{$blueprint->id}", [
                'name' => $blueprint->name,
                'description' => $blueprint->description,
                'structure' => $blueprint->structure,
                'agents_md' => '# Custom master',
                'skills' => $skills,
            ])
            ->assertRedirect();

        $blueprint->refresh();
        $this->assertSame('# Custom master', $blueprint->agents_md);
        $this->assertArrayHasKey('framework-custom', $blueprint->skills);
        $this->assertArrayHasKey('framework-workflow', $blueprint->skills);
    }

    public function test_admin_rejects_non_array_structure(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->forceFill(['is_admin' => true])->save();

        $blueprint = Blueprint::where('key', 'bare')->first();

        $this->actingAs($admin)
            ->patch("/admin/blueprints/{$blueprint->id}", [
                'name' => 'Bare',
                'structure' => 'not-an-array',
            ])
            ->assertSessionHasErrors('structure');
    }

    public function test_production_seeder_creates_admin(): void
    {
        putenv('FW_ADMIN_EMAIL=admin@example.com');

        $this->seed(\Database\Seeders\ProductionSeeder::class);
        putenv('FW_ADMIN_EMAIL');

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertSame(5, Blueprint::count());
    }
}
