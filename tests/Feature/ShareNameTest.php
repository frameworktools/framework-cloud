<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ArtifactEmbed;
use App\Support\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FW_SHARE_NAME, or the Server administration setting, names the server in embeds and link previews. */
class ShareNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_unset_it_is_the_servers_host(): void
    {
        config(['app.url' => 'https://www.example.org']);
        url()->forceRootUrl('https://www.example.org');

        $this->assertSame('example.org', ArtifactEmbed::providerName());
    }

    public function test_an_admin_sets_it_in_server_administration(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);

        $this->actingAs($admin)->put('/admin/setup/sharing', ['sharing__name' => ' Example.pub '])->assertRedirect();
        Credentials::apply();

        $this->assertSame('Example.pub', ArtifactEmbed::providerName());
        $this->get('/')->assertSee('<meta property="og:site_name" content="Example.pub">', false);

        $this->put('/admin/setup/sharing', ['sharing__name' => ''])->assertRedirect();
        config(['framework.share_name' => null]);
        Credentials::apply();
        $this->assertSame('localhost', ArtifactEmbed::providerName());
    }
}
