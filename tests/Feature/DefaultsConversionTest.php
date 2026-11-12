<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\ProjectDefaults;
use App\Support\Resolver;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultsConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    public function test_own_defaults_become_a_personal_default_blueprint_once(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'project_defaults' => [
            'language' => 'r',
            'skill_sets' => ['framework/ggplot2'],
            'hooks' => ['data_security' => true],
            'packages' => ['r' => [['name' => 'dplyr', 'source' => 'cran', 'auto_attach' => true], ['name' => 'fixest', 'source' => 'cran', 'auto_attach' => false]]],
            'ai_other' => true,
        ]]);
        $setting = $user->globalSettings();
        $doc = $setting->document;
        $doc['defaults']['seed'] = '2024';
        $doc['defaults']['ide'] = 'rstudio';
        $doc['ai']['assistants'] = ['claude'];
        $doc['quarto'] = ['html' => ['theme' => 'cosmo']];
        $setting->replaceDocument($doc);
        $user->projects()->create(['name' => 'Old', 'slug' => 'old', 'language' => 'python', 'project_type' => 'project']);

        $this->artisan('fw:convert-defaults', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());

        $this->artisan('fw:convert-defaults')->expectsOutputToContain('ada@example.com: r own blueprint My defaults (R)')->assertSuccessful();

        $user = $user->fresh();
        $mine = Blueprint::where('user_id', $user->id)->where('name', 'My defaults (R)')->sole();
        $this->assertSame($mine->id, $user->blueprint_defaults['blueprints']['r']);
        $this->assertSame('r', $user->blueprint_defaults['language']);
        $this->assertSame(['r'], $mine->latestVersion->languages);
        $this->assertSame(['claude', 'other'], $mine->latestVersion->agent_defaults);
        $this->assertSame('rstudio', $mine->latestVersion->ide);
        // Python, used by a project, gets its own, without R's skill set
        $python = ProjectDefaults::blueprint($user, 'python');
        $this->assertSame('My defaults (Python)', $python->name);
        $this->assertNotContains('ggplot2', $python->latestVersion->facetVersions->pluck('facet.key')->all());
        $this->assertArrayNotHasKey('stata', $user->blueprint_defaults['blueprints']);

        // A new R project gets what the old defaults gave it
        $resolved = (new Resolver($user))->resolve(['name' => 'Survey']);
        $this->assertSame($mine->handle, $resolved['blueprint']->blueprint->handle);
        $this->assertContains('ggplot2', collect($resolved['pieces'])->pluck('facet.key')->all());
        $this->assertContains('git', collect($resolved['pieces'])->pluck('facet.key')->all());
        $spec = DefaultsPreview::merge($resolved['envelope']['layers']);
        $this->assertSame('2024', $spec['scaffold']['seed']);
        $this->assertSame('rstudio', $spec['scaffold']['ide']);
        $this->assertSame('cosmo', $spec['quarto']['html']['theme']);
        $this->assertTrue($spec['git']['hooks']['data_security']);
        $this->assertTrue($spec['ai']['assistants']['claude']);
        $this->assertArrayHasKey('fixest', $spec['packages']['default_packages']);
        // R starts with Framework's list, so what the person took out stays out
        $entries = $resolved['layers']['blueprint']['packages']['default_packages'];
        $this->assertArrayHasKey('readr', $entries);
        $this->assertNull($entries['readr']);

        // Running again changes nothing
        $versions = $mine->versions()->count();
        $this->artisan('fw:convert-defaults')->expectsOutputToContain('already converted')->assertSuccessful();
        $this->artisan('fw:convert-defaults', ['--force' => true, '--email' => 'ada@example.com'])->expectsOutputToContain('r kept')->assertSuccessful();
        $this->assertSame(2, Blueprint::where('user_id', $user->id)->count());
        $this->assertSame($versions, $mine->versions()->count());
        // The old values stay where they were
        $this->assertSame(['framework/ggplot2'], $user->fresh()->project_defaults['skill_sets']);
    }

    public function test_defaults_that_match_framework_keep_framework_blueprints(): void
    {
        $plain = User::factory()->create(['email' => 'plain@example.com']);
        $course = User::factory()->create(['email' => 'course@example.com']);
        $setting = $course->globalSettings();
        $doc = $setting->document;
        $doc['defaults']['project_type'] = 'course';
        $setting->replaceDocument($doc);

        $this->artisan('fw:convert-defaults')->assertSuccessful();

        $this->assertSame([], Blueprint::whereNotNull('user_id')->pluck('name')->all());
        $this->assertArrayNotHasKey('blueprints', $plain->fresh()->blueprint_defaults);
        $this->assertNotNull($plain->fresh()->blueprint_defaults['converted_at']);
        $this->assertSame(['r' => Blueprint::whereNull('user_id')->where('key', 'course')->value('id')], $course->fresh()->blueprint_defaults['blueprints']);
    }

    public function test_r_defaults_exclude_optional_packages_but_explicit_sets_can_add_them(): void
    {
        $user = User::factory()->create();
        $blueprint = ProjectDefaults::blueprint($user, 'r');
        $preview = DefaultsPreview::forBlueprint($user, $blueprint, 'r', 'quarto');
        $names = array_column($preview['packages'], 'name');
        $this->assertContains('dplyr', $names);
        foreach (['glue', 'here', 'lme4'] as $name) {
            $this->assertNotContains($name, $names);
            $this->assertNotContains($name, array_column(ProjectDefaults::for($user)['packages']['r'], 'name'));
        }

        $resolved = (new Resolver($user))->resolve(['name' => 'Survey', 'language' => 'r']);
        // Simulate an older R client's defaults beneath the Cloud envelope.
        $oldDefaults = ['layer' => ['packages' => ['default_packages' => array_fill_keys(['glue', 'here', 'lme4'], ['source' => 'cran'])]]];
        $spec = DefaultsPreview::merge([$oldDefaults, ...$resolved['envelope']['layers']]);
        $this->assertEmpty(array_intersect(['glue', 'here', 'lme4'], array_keys($spec['packages']['default_packages'])));

        $resolved = (new Resolver($user))->resolve(['name' => 'Survey', 'language' => 'r', 'package_sets' => ['framework/tidyverse-extended', 'framework/regression-r']]);
        $spec = DefaultsPreview::merge([$oldDefaults, ...$resolved['envelope']['layers']]);
        $this->assertArrayHasKey('glue', $spec['packages']['default_packages']);
        $this->assertArrayHasKey('lme4', $spec['packages']['default_packages']);
        $this->assertArrayNotHasKey('here', $spec['packages']['default_packages']);
    }

    public function test_an_unknown_email_fails(): void
    {
        $this->artisan('fw:convert-defaults', ['--email' => 'nobody@example.com'])->assertFailed();
    }
}
