<?php

namespace Tests\Feature;

use App\Models\GitHubAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** GitHub sits in the sidebar's main group, named by whether it is connected. */
class GitHubNavTest extends TestCase
{
    use RefreshDatabase;

    private function label(User $user): ?string
    {
        $label = null;
        $this->actingAs($user)->get('/dashboard')->assertInertia(function (AssertableInertia $page) use (&$label) {
            $label = collect($page->toArray()['props']['nav'])->firstWhere('href', '/account/github')['label'] ?? null;
        });

        return $label;
    }

    public function test_the_sidebar_offers_to_connect_then_names_github(): void
    {
        $this->withoutVite();
        config(['services.github.client_id' => 'Iv1.test']);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertSame('Connect GitHub', $this->label($user));

        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->forceFill(['access_token' => 'ghu_test'])->save();
        $this->assertSame('GitHub', $this->label($user));
    }

    public function test_the_sidebar_leaves_github_out_when_the_server_has_no_github_app(): void
    {
        $this->withoutVite();
        config(['services.github.client_id' => null]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertNull($this->label($user));
    }
}
