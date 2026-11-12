<?php

namespace Tests\Feature;

use App\Models\Facet;
use App\Models\User;
use App\Support\Layer;
use App\Support\ProjectLayers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PackageSetEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_structured_packages_preserve_sources_repositories_and_versions(): void
    {
        $user = User::factory()->create();
        $rows = [
            ['name' => 'dplyr', 'source' => 'cran', 'version' => '1.1.4'],
            ['name' => 'DESeq2', 'source' => 'bioc'],
            ['name' => 'ggplot2', 'source' => 'github', 'repo' => 'tidyverse/ggplot2', 'ref' => 'main'],
        ];
        $this->actingAs($user)->post('/package-sets', ['name' => 'Analysis', 'visibility' => 'private', 'language' => 'r', 'package_rows' => $rows])->assertSessionHasNoErrors();
        $set = Facet::where('user_id', $user->id)->sole();
        $packages = $set->latestVersion->layer['packages']['default_packages'];
        $this->assertSame('bioc', $packages['DESeq2']['source']);
        $this->assertSame('1.1.4', $packages['dplyr']['version']);
        $this->assertSame('tidyverse/ggplot2', $packages['ggplot2']['repo']);
        $this->assertFalse($packages['dplyr']['auto_attach']);
        $this->assertSame('main', $packages['ggplot2']['ref']);
        $this->get('/package-sets/'.$set->id.'/edit')->assertInertia(fn (Assert $page) => $page->has('values.package_rows', 3)->where('values.package_rows.1.source', 'bioc'));
        $this->put('/package-sets/'.$set->id, ['name' => 'Analysis', 'visibility' => 'private', 'language' => 'python', 'package_rows' => $rows])->assertSessionHasErrors('package_rows.0.source');
    }

    public function test_stata_names_are_versioned_and_copied_as_manual_lists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/package-sets/create?language=stata')->assertInertia(fn (Assert $page) => $page->where('language', 'stata'));
        $this->actingAs($user)->post('/package-sets', ['name' => 'Stata analysis', 'visibility' => 'private', 'language' => 'stata', 'package_rows' => [
            ['name' => 'reghdfe', 'source' => 'manual'], ['name' => 'estout', 'source' => 'manual'],
        ]])->assertSessionHasNoErrors();
        $set = Facet::where('user_id', $user->id)->sole();
        $this->assertSame(['reghdfe', 'estout'], $set->latestVersion->manual_packages);
        $entries = ProjectLayers::entries($set->latestVersion, 'stata');
        $this->assertArrayNotHasKey('packages', $entries[0]['layer']);
        $this->assertSame("reghdfe\nestout\n", $entries[0]['layer']['files']['.framework/packages/stata-'.$set->id.'.txt']['content']);
        $this->assertSame([], Layer::problems($entries[0]['layer'], 'package_set'));
        $this->post('/package-sets/'.$set->id.'/fork')->assertRedirect();
        $copy = Facet::where('user_id', $user->id)->whereKeyNot($set->id)->sole();
        $this->assertSame(['reghdfe', 'estout'], $copy->latestVersion->manual_packages);
        $this->get('/package-sets?language=stata')->assertInertia(fn (Assert $page) => $page->where('language', 'stata')->where('sets.0.packages.0.name', 'reghdfe'));
    }

    public function test_github_search_and_exact_repository_lookup_use_the_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.github.com/search/repositories*' => Http::response(['items' => [['full_name' => 'tidyverse/ggplot2', 'description' => 'Graphics']]]),
            'api.github.com/repos/pandas-dev/pandas' => Http::response(['full_name' => 'pandas-dev/pandas', 'description' => 'Data analysis']),
        ]);
        $this->actingAs(User::factory()->create())->getJson('/packages/search?source=github&language=r&q=ggplot2')->assertOk()->assertJsonPath('packages.0.name', 'tidyverse/ggplot2');
        $this->getJson('/packages/search?source=github&language=python&q=pandas-dev%2Fpandas')->assertOk()->assertJsonPath('packages.0.name', 'pandas-dev/pandas');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'search/repositories') && $request['q'] === 'ggplot2 language:R');
    }
}
