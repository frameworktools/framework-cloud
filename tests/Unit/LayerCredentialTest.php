<?php

namespace Tests\Unit;

use App\Support\Layer;
use Tests\TestCase;

class LayerCredentialTest extends TestCase
{
    private function problems(string $password): array
    {
        return Layer::problems(['connections' => ['databases' => ['main' => ['driver' => 'postgres', 'password' => $password]]]], 'facet');
    }

    public function test_env_references_pass_with_or_without_an_empty_default(): void
    {
        $this->assertSame([], $this->problems('env("POSTGRES_PASSWORD")'));
        $this->assertSame([], $this->problems('env("POSTGRES_PASSWORD", "")'));
        $this->assertSame([], $this->problems("env('POSTGRES_PASSWORD', '')"));
    }

    public function test_a_literal_value_or_a_filled_default_is_refused(): void
    {
        $this->assertStringContainsString('looks like a credential', implode(' ', $this->problems('hunter2')));
        $this->assertStringContainsString('gives env() a default', implode(' ', $this->problems('env("POSTGRES_PASSWORD", "hunter2")')));
    }
}
