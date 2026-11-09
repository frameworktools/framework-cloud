<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomePageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_serves_signed_out_visitors_the_cached_page(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $key = 'home:'.HomeController::release();

        $first = $this->get('/')->assertOk()->getContent();
        $this->assertSame($first, Cache::get($key));
        $this->assertStringContainsString('Create a Framework Cloud account', $first);

        // Served from the cache, not rendered again
        Cache::put($key, 'cached home page');
        $this->get('/')->assertOk()->assertSee('cached home page');
    }

    public function test_signed_in_visitors_get_their_own_page(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Cache::put('home:'.HomeController::release(), 'cached home page');

        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertDontSee('cached home page')
            ->assertSee('Open dashboard');
    }

    public function test_outside_production_the_page_is_rendered_each_time(): void
    {
        Cache::put('home:'.HomeController::release(), 'cached home page');

        $this->get('/')->assertOk()->assertDontSee('cached home page');
    }
}
