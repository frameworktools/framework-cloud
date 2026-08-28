<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_public_profile_renders_for_opted_in_users(): void
    {
        $user = User::factory()->create(['name' => 'Erik Westlund']);
        $user->forceFill(['handle' => 'erik', 'profile_public' => true])->save();

        $this->get('/@erik')
            ->assertOk()
            ->assertSee('Erik Westlund')
            ->assertSee('@erik')
            ->assertSee('Using Framework since');
    }

    public function test_private_and_unknown_profiles_404(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['handle' => 'private-person', 'profile_public' => false])->save();

        $this->get('/@private-person')->assertNotFound();
        $this->get('/@nobody-here')->assertNotFound();
    }

    public function test_user_can_update_profile_and_claim_a_handle(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'handle' => 'my-handle',
                'profile_public' => true,
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('my-handle', $user->handle);
        $this->assertTrue($user->profile_public);
        $this->get('/@my-handle')->assertOk();
    }

    public function test_changing_email_unverifies_it(): void
    {
        $user = $this->user();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => 'new@example.com',
            'handle' => '',
            'profile_public' => false,
        ])->assertRedirect();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_reserved_and_taken_handles_are_rejected(): void
    {
        $other = User::factory()->create();
        $other->forceFill(['handle' => 'taken'])->save();

        $user = $this->user();
        $base = ['name' => $user->name, 'email' => $user->email, 'profile_public' => false];

        $this->actingAs($user)
            ->patch('/settings/profile', [...$base, 'handle' => 'admin'])
            ->assertSessionHasErrors('handle');

        $this->actingAs($user)
            ->patch('/settings/profile', [...$base, 'handle' => 'taken'])
            ->assertSessionHasErrors('handle');
    }

    public function test_public_without_a_handle_stays_private(): void
    {
        $user = $this->user();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'handle' => '',
            'profile_public' => true,
        ])->assertRedirect();

        $this->assertFalse($user->fresh()->profile_public);
    }

    public function test_account_deletion_confirms_by_email(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->delete('/settings/profile', ['confirm_email' => 'wrong@example.com'])
            ->assertSessionHasErrors('confirm_email');
        $this->assertNotNull($user->fresh());

        $this->actingAs($user)
            ->delete('/settings/profile', ['confirm_email' => $user->email])
            ->assertRedirect('/');
        $this->assertNull(User::find($user->id));
        $this->assertGuest();
    }
}
