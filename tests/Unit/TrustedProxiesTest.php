<?php

namespace Tests\Unit;

use App\Support\TrustedProxies;
use PHPUnit\Framework\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_settings_resolve_to_proxy_lists(): void
    {
        $this->assertSame(['127.0.0.1', '::1'], TrustedProxies::resolve(null));
        $this->assertSame('*', TrustedProxies::resolve(' * '));
        $this->assertContains('173.245.48.0/20', TrustedProxies::resolve('cloudflare'));
        $this->assertContains('127.0.0.1', TrustedProxies::resolve('cloudflare'));
        $this->assertSame(['127.0.0.1', '::1', '10.0.0.0/8'], TrustedProxies::resolve('10.0.0.0/8'));
        $this->assertContains('2606:4700::/32', TrustedProxies::resolve('10.0.0.1, Cloudflare'));
    }
}
