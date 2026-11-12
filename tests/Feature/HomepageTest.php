<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_keeps_its_workflow_and_explains_each_product(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Structured data science workflows')
            ->assertSee('Start your project')
            ->assertSee('Conduct your analysis')
            ->assertSee('Publish your work')
            ->assertSee('Start a Framework project')->assertSee('Start from Framework Cloud')->assertSee('Start from your computer')->assertSee('fw new penguin-study')->assertSee('destination=download', false)
            ->assertSee('Put it on GitHub')->assertSee('Download penguin-study.zip')
            ->assertSee('data-demo-slide', false)
            ->assertSee('Exploring data with Stata graphs');
    }

    public function test_without_a_live_example_the_result_card_has_no_link(): void
    {
        config(['framework.homepage_example_url' => null]);

        $this->get('/')->assertSee('Penguin body mass model')->assertDontSee('View model');
    }

    public function test_stata_tabs_appear_once_the_package_is_released(): void
    {
        config(['framework.stata_package' => true]);

        $this->get('/')->assertSee('data-project-language="stata"', false);
    }

    public function test_the_example_command_publishes_two_public_versions_that_open_signed_out(): void
    {
        Storage::fake('local');
        config(['framework.artifacts_disk' => 'local']);

        $this->artisan('fw:homepage-example')->assertSuccessful();
        $this->artisan('fw:homepage-example')->assertSuccessful();

        $document = Document::where('slug', 'penguin-body-mass')->firstOrFail();
        $this->assertSame(2, $document->versions()->count());
        $this->assertSame('public', $document->visibility);

        $this->get($document->shareUrl())->assertOk()->assertSee('Version 2 (latest)', false);
        foreach (['penguins', 'mass-by-species', 'flipper-and-mass'] as $slug) {
            $other = Document::where('project_id', $document->project_id)->where('slug', $slug)->firstOrFail();
            $this->assertSame(1, $other->versions()->count());
            $this->get($other->shareUrl())->assertOk();
        }

        config(['framework.homepage_example_url' => $document->shareUrl()]);
        $this->get('/')->assertSee('View model')->assertSee('speciesGentoo')
            ->assertSee(parse_url($document->shareUrl(), PHP_URL_PATH), false);
    }
}
