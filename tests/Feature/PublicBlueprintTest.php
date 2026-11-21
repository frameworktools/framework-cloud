<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\ShortLink;
use App\Models\User;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Public and unlisted blueprints have a page anyone can open; private ones (the default) don't. */
class PublicBlueprintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function own(string $visibility): Blueprint
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('t')->plainTextToken)->postJson('/api/v1/blueprints', array_filter([
            'name' => 'Course exercise', 'description' => 'For the workshop.', 'language' => 'r', 'structure' => 'framework/project',
            'skill_sets' => [], 'visibility' => $visibility,
        ]))->assertCreated();

        return Blueprint::where('user_id', $user->id)->firstOrFail();
    }

    public function test_public_blueprints_are_indexed_and_in_the_sitemap_and_unlisted_ones_are_not(): void
    {
        $public = tap($this->own('unlisted'))->update(['visibility' => 'public']);
        $this->app['auth']->forgetGuards(); // the next request signs in as its own user
        $unlisted = $this->own('unlisted');

        $this->get(parse_url($public->publicUrl(), PHP_URL_PATH))->assertOk()
            ->assertDontSee('content="noindex"', false)
            ->assertSee('<link rel="canonical" href="'.$public->publicUrl().'">', false)
            ->assertSee('<meta property="og:description" content="For the workshop.">', false)
            ->assertSee('"genre":"Project template"', false);
        $this->get(parse_url($unlisted->publicUrl(), PHP_URL_PATH))->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>'.$public->publicUrl().'</loc>', false)
            ->assertDontSee($unlisted->publicUrl(), false);
    }

    public function test_new_blueprints_are_private_and_have_no_public_page(): void
    {
        $blueprint = $this->own('');

        $this->assertSame('private', $blueprint->visibility);
        $this->assertNull($blueprint->publicUrl());

        $link = ShortLink::for($blueprint);
        $this->get("/go/{$link->code}")->assertNotFound();
    }

    public function test_a_public_blueprint_page_shows_what_a_project_gets_and_presents_a_short_link(): void
    {
        $blueprint = $this->own('public');
        $url = $blueprint->publicUrl();

        $this->assertNotNull($url);
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk()
            ->assertSee('Course exercise')
            ->assertSee('fw new my-project --from '.$blueprint->handle)
            ->assertSee('settings.yml')
            ->assertSee('/go/', false);

        $link = ShortLink::for($blueprint);
        $this->get("/go/{$link->code}")->assertRedirect($url);

        // Unlisted: the page still opens from its own link, but the short code, which anyone could guess, retires
        $blueprint->update(['visibility' => 'unlisted']);
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertDontSee('/go/', false);
        $this->get("/go/{$link->code}")->assertNotFound();

        // Made private again: the page and the short link stop working
        $blueprint->update(['visibility' => 'private']);
        $this->get(parse_url($url, PHP_URL_PATH))->assertNotFound();
        $this->get("/go/{$link->code}")->assertNotFound();
    }
}
