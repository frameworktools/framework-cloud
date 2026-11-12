<?php

namespace Tests\Feature;

use App\Models\Facet;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\Resolver;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PackageSetsCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function pythonSet(User $user): Facet
    {
        $this->actingAs($user)->post('/package-sets', ['name' => 'Plots', 'visibility' => 'private', 'language' => 'python', 'packages' => "numpy\nmatplotlib", 'requirements' => 'Nothing outside Python.'])
            ->assertSessionHasNoErrors();

        return Facet::where('user_id', $user->id)->where('kind', 'package_set')->where('key', 'plots')->sole();
    }

    public function test_the_catalog_starts_at_the_preferred_language_and_says_how_much_each_set_adds(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['language' => 'python']]);
        $this->pythonSet($user);

        $this->actingAs($user)->get('/package-sets')->assertInertia(fn (Assert $page) => $page
            ->component('Pieces/PackageSets')
            ->where('language', 'python')
            // Framework's sets, small general ones first, then yours
            ->where('sets.0.key', 'tidy-core')
            ->where('sets.1.language', ['r'])
            ->where('sets.3.language', ['python'])
            ->where('sets.3.packages.0.name', 'pandas')
            ->where('sets.27.language', ['python'])
            ->where('sets.27.requirements', 'Nothing outside Python.')
            ->where('sets.27.packages.0.source', 'pypi'));

        // Stata has a manual package-list tab.
        $stata = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['language' => 'stata']]);
        $this->actingAs($stata)->get('/package-sets')->assertInertia(fn (Assert $page) => $page->where('language', 'stata'));
    }

    public function test_a_package_set_names_its_language_and_isnt_installed_for_another(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/package-sets', ['name' => 'Anything', 'visibility' => 'private', 'packages' => 'numpy'])->assertSessionHasErrors('language');
        $this->actingAs($user)->post('/package-sets', ['name' => 'Anything', 'visibility' => 'private', 'language' => 'stata', 'packages' => 'reghdfe'])->assertSessionHasNoErrors();

        $stataSet = Facet::where('user_id', $user->id)->where('key', 'anything')->sole();
        $stataResolved = (new Resolver($user))->resolve(['name' => 'Manual Stata', 'language' => 'stata', 'package_sets' => [$stataSet->handle]]);
        $this->assertSame("reghdfe\n", DefaultsPreview::merge($stataResolved['envelope']['layers'])['files']['.framework/packages/stata-'.$stataSet->id.'.txt']['content']);
        $set = $this->pythonSet($user);
        $resolved = (new Resolver($user))->resolve(['name' => 'S', 'language' => 'r', 'package_sets' => [$set->handle]]);
        $this->assertNotContains($set->id, collect($resolved['pieces'])->pluck('facet_id'));
        $this->assertStringContainsString('Plots is for Python, so this R project leaves it out.', implode(' ', $resolved['notices']));
    }
}
