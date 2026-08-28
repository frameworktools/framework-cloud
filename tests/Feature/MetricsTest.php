<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_require_the_bearer_token(): void
    {
        config(['monitoring.metrics_token' => 'scrape-token']);

        $this->get('/api/metrics')->assertUnauthorized();
        $this->withToken('wrong')->get('/api/metrics')->assertUnauthorized();
    }

    public function test_metrics_refuse_everything_when_unconfigured(): void
    {
        config(['monitoring.metrics_token' => null]);

        $this->withToken('anything')->get('/api/metrics')->assertUnauthorized();
    }

    public function test_metrics_serve_prometheus_text_format(): void
    {
        config(['monitoring.metrics_token' => 'scrape-token']);
        Project::factory()->count(2)->create();

        $response = $this->withToken('scrape-token')->get('/api/metrics')->assertOk();

        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $response->assertSee('framework_app_up 1', false);
        $response->assertSee('framework_projects_total 2', false);
        $response->assertSee('# TYPE framework_queue_jobs_pending gauge', false);
    }
}
