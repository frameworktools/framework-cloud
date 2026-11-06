<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** FW_AGENTS, or the Server administration setting, turns AI agents off. */
class AgentsSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function agentsPaths(): array
    {
        return ['/.well-known/oauth-authorization-server', '/.well-known/oauth-protected-resource/mcp'];
    }

    public function test_agents_are_on_by_default(): void
    {
        $this->withoutVite();
        foreach ($this->agentsPaths() as $path) {
            $this->getJson($path)->assertOk();
        }
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/agents')->assertOk();
    }

    public function test_turned_off_everything_agents_use_is_gone(): void
    {
        $this->withoutVite();
        config(['framework.agents' => 'false']);
        foreach ($this->agentsPaths() as $path) {
            $this->getJson($path)->assertNotFound();
        }
        $this->postJson('/mcp', [])->assertNotFound();
        $this->postJson('/oauth/register', ['redirect_uris' => ['http://localhost:1/cb']])->assertNotFound();
        $this->postJson('/oauth/token', [])->assertNotFound();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/agents')->assertNotFound();
        $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nav', fn ($nav) => collect($nav)->doesntContain('href', '/agents')));
    }

    public function test_an_admin_turns_agents_off_in_server_administration(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);

        $this->actingAs($admin)->put('/admin/setup/agents', ['agents__enabled' => 'false'])->assertRedirect();
        Credentials::apply();
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();

        $this->put('/admin/setup/agents', ['agents__enabled' => 'true'])->assertRedirect();
        Credentials::apply();
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk();

        $this->put('/admin/setup/agents', ['agents__enabled' => 'maybe'])->assertSessionHasErrors('agents__enabled');
    }
}
