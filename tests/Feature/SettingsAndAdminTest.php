<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\Layer;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_defaults_page_saves_into_settings_document_with_revision_bump(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/settings/defaults')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Defaults')
                ->where('revision', 0));

        $this->actingAs($user)
            ->patch('/settings/defaults', [
                'project_type' => 'bare',
                'notebook_format' => 'quarto',
                'ide' => 'rstudio',
                'use_git' => true,
                'use_renv' => true,
                'seed_on_scaffold' => true,
                'ai_enabled' => true,
                'ai_canonical_file' => 'AGENTS.md',
                'ai_skills' => true,
                'package_sets' => ['framework/tidyverse-analysis'],
                'skill_sets' => ['framework/ggplot2'],
                'tools' => ['targets' => true, 'renv' => true],
                'sensitive' => true,
            ])
            ->assertRedirect();

        // Preferred pieces stay in the cloud, out of the synced document; renv stays synced
        $this->assertSame([
            'package_sets' => ['framework/tidyverse-analysis'],
            'skill_sets' => ['framework/ggplot2'],
            'tools' => ['targets' => true],
            'sensitive' => true,
        ], $user->fresh()->project_defaults);
        $this->assertArrayNotHasKey('package_sets', $user->globalSettings()->document['defaults']);

        $setting = $user->globalSettings();
        $this->assertSame(1, $setting->revision);
        $this->assertSame('rstudio', $setting->document['defaults']['ide']);
        $this->assertSame('bare', $setting->document['defaults']['project_type']);
        $this->assertTrue($setting->document['defaults']['use_renv']);
    }

    public function test_profile_saves_a_quarto_author_block(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Ada Lovelace']);

        $this->actingAs($user)->get('/settings/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Profile')
                ->where('author.name', 'Ada Lovelace')
                ->where('author.affiliations', []));

        $this->actingAs($user)
            ->patch('/settings/profile', [
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
        $this->assertSame('Analytical Engine Lab', $envelope['author']['affiliation']);
        $this->assertSame('0000-0002-1825-0097', $envelope['author']['orcid']);
        $this->assertCount(2, $envelope['author']['affiliations']);
        $this->assertSame([], Layer::envelopeProblems($envelope));
    }

    public function test_profile_rejects_a_malformed_orcid(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->patch('/settings/profile', ['name' => 'A', 'orcid' => '1234'])
            ->assertSessionHasErrors('orcid');
    }

    public function test_production_seeder_creates_admin(): void
    {
        putenv('FW_ADMIN_EMAIL=admin@example.com');

        $this->seed(ProductionSeeder::class);
        putenv('FW_ADMIN_EMAIL');

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertSame(5, Blueprint::count());
    }

    public function test_reseeding_on_every_start_leaves_the_admin_as_edited(): void
    {
        // Docker runs the seeder on every start, with FW_ADMIN_NAME often blank
        putenv('FW_ADMIN_EMAIL=ada@example.com');
        putenv('FW_ADMIN_NAME=');

        $this->seed(ProductionSeeder::class);
        $user = User::where('email', 'ada@example.com')->first();
        $this->assertSame('Ada', $user->name);

        $user->update(['name' => 'Ada Lovelace']);
        $this->seed(ProductionSeeder::class);

        putenv('FW_ADMIN_EMAIL');
        putenv('FW_ADMIN_NAME');

        $this->assertSame('Ada Lovelace', $user->fresh()->name);
        $this->assertSame(1, User::count());
    }
}
