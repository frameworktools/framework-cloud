<?php

namespace Tests\Feature;

use App\Models\Facet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StataManualPackagesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_stata_lists_become_ssc_entries_and_back(): void
    {
        $user = User::factory()->create();
        $set = Facet::create(['user_id' => $user->id, 'kind' => 'package_set', 'key' => 'stata-old', 'name' => 'Old', 'language' => ['stata'], 'visibility' => 'private']);
        $set->publishVersion(['layer' => [], 'manual_packages' => ['reghdfe', 'estout']]);
        $migration = require database_path('migrations/2026_11_14_120000_convert_stata_manual_packages.php');

        $migration->up();
        $version = $set->latestVersion()->first();
        $this->assertNull($version->manual_packages);
        $this->assertSame([
            'reghdfe' => ['source' => 'ssc', 'auto_attach' => false],
            'estout' => ['source' => 'ssc', 'auto_attach' => false],
        ], $version->layer['packages']['default_packages']);

        $migration->down();
        $version = $set->latestVersion()->first();
        $this->assertSame(['reghdfe', 'estout'], $version->manual_packages);
        $this->assertArrayNotHasKey('packages', $version->layer ?? []);
    }
}
