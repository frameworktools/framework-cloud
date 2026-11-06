<?php

namespace Tests\Feature;

use App\Support\TrustedProxies;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CloudflareProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    public function test_behind_cloudflare_the_app_sees_the_visitor(): void
    {
        config(['framework.trusted_proxies' => 'cloudflare']);
        $this->refreshApplicationWithTrustedProxies();
        Route::get('/_ip', fn () => response()->json(['ip' => request()->ip(), 'secure' => request()->secure()]));

        $this->withServerVariables(['REMOTE_ADDR' => '172.64.1.2'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/_ip')->assertJson(['ip' => '203.0.113.9', 'secure' => true]);

        // Anyone else claiming to forward is ignored
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->getJson('/_ip')->assertJson(['ip' => '198.51.100.7']);
    }

    private function refreshApplicationWithTrustedProxies(): void
    {
        TrustProxies::at(TrustedProxies::resolve(config('framework.trusted_proxies')));
    }
}
