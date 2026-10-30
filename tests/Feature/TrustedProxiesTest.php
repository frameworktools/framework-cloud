<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_loopback_proxies_set_the_scheme_by_default(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'cloud.example.edu'])
            ->get('/login')
            ->assertSee('https://cloud.example.edu', false);
    }

    public function test_other_addresses_are_not_trusted_by_default(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'cloud.example.edu'])
            ->get('/login')
            ->assertDontSee('https://cloud.example.edu', false);
    }
}
