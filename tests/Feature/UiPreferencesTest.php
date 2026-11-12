<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UiPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_collapsed_section_is_remembered_on_the_account(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->postJson('/preferences/ui', ['key' => 'dashboard_quick_commands', 'collapsed' => true])->assertNoContent();
        $this->assertTrue($user->fresh()->ui_preferences['collapsed']['dashboard_quick_commands']);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.user.collapsed.dashboard_quick_commands', true));

        // Opening it again is remembered too, and other sections are kept
        $this->actingAs($user)->postJson('/preferences/ui', ['key' => 'dashboard_publish', 'collapsed' => true])->assertNoContent();
        $this->actingAs($user)->postJson('/preferences/ui', ['key' => 'dashboard_quick_commands', 'collapsed' => false])->assertNoContent();
        $this->assertSame(['dashboard_quick_commands' => false, 'dashboard_publish' => true], $user->fresh()->ui_preferences['collapsed']);
    }

    public function test_only_known_sections_and_true_or_false(): void
    {
        $this->postJson('/preferences/ui', ['key' => 'dashboard_publish', 'collapsed' => true])->assertUnauthorized();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->postJson('/preferences/ui', ['key' => 'anything', 'collapsed' => true])->assertUnprocessable();
        $this->actingAs($user)->postJson('/preferences/ui', ['key' => 'dashboard_publish', 'collapsed' => 'maybe'])->assertUnprocessable();
    }
}
