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

    public function test_stata_sets_hold_ssc_github_and_net_packages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/package-sets/create?language=stata')->assertInertia(fn (Assert $page) => $page->where('language', 'stata'));
        $this->actingAs($user)->post('/package-sets', ['name' => 'Stata analysis', 'visibility' => 'private', 'language' => 'stata', 'package_rows' => [
            ['name' => 'estout', 'source' => 'ssc'],
            ['name' => 'reghdfe', 'source' => 'github', 'repo' => 'sergiocorreia/reghdfe', 'ref' => '6.12.3'],
            ['name' => 'mytools', 'source' => 'net', 'url' => 'https://example.org/stata'],
        ]])->assertSessionHasNoErrors();
        $set = Facet::where('user_id', $user->id)->sole();
        $this->assertSame([
            'estout' => ['source' => 'ssc', 'auto_attach' => false],
            'reghdfe' => ['source' => 'github', 'repo' => 'sergiocorreia/reghdfe', 'ref' => '6.12.3', 'auto_attach' => false],
            'mytools' => ['source' => 'net', 'url' => 'https://example.org/stata', 'auto_attach' => false],
        ], $set->latestVersion->layer['packages']['default_packages']);
        $entries = ProjectLayers::entries($set->latestVersion, 'stata');
        $this->assertSame([], Layer::problems($entries[0]['layer'], 'package_set'));
        $this->get('/package-sets/'.$set->id.'/edit')->assertInertia(fn (Assert $page) => $page->has('values.package_rows', 3)
            ->where('values.package_rows.1.ref', '6.12.3')->where('values.package_rows.2.url', 'https://example.org/stata'));
        $this->post('/package-sets/'.$set->id.'/fork')->assertRedirect();
        $copy = Facet::where('user_id', $user->id)->whereKeyNot($set->id)->sole();
        $this->assertSame($set->latestVersion->layer, $copy->latestVersion->layer);
        $this->get('/package-sets?language=stata')->assertInertia(fn (Assert $page) => $page->where('language', 'stata')->where('sets.0.packages.0.name', 'estout'));
    }

    public function test_stata_rows_need_a_stata_name_and_their_sources_fields(): void
    {
        $user = User::factory()->create();
        $post = fn (array $row) => $this->actingAs($user)->post('/package-sets', ['name' => 'Stata', 'visibility' => 'private', 'language' => 'stata', 'package_rows' => [$row]]);
        $post(['name' => 'dplyr', 'source' => 'cran'])->assertSessionHasErrors('package_rows.0.source');
        $post(['name' => 'mytools', 'source' => 'net'])->assertSessionHasErrors('package_rows.0.url');
        $post(['name' => 'mytools', 'source' => 'net', 'url' => 'ftp://example.org'])->assertSessionHasErrors('package_rows.0.url');
        $post(['name' => 'my-tools', 'source' => 'ssc'])->assertSessionHasErrors('package_rows.0.name');
        $post(['name' => 'reghdfe', 'source' => 'github'])->assertSessionHasErrors('package_rows.0.source');
        $this->assertSame(0, Facet::where('user_id', $user->id)->count());
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
