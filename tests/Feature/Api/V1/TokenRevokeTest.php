<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenRevokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_token_revokes_itself_and_leaves_the_others(): void
    {
        $user = User::factory()->create();
        $mine = $user->createToken('laptop')->plainTextToken;
        $other = $user->createToken('desktop')->plainTextToken;

        $this->withToken($mine)->deleteJson('/api/v1/token')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($mine)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/v1/me')->assertOk();
        $this->assertSame(['desktop'], $user->tokens()->pluck('name')->all());
    }

    public function test_a_project_token_revokes_itself(): void
    {
        $project = Project::factory()->create();
        $token = $project->createToken('pull')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/token')->assertNoContent();

        $this->assertSame(0, $project->tokens()->count());
    }

    public function test_without_a_token_it_answers_401_in_json(): void
    {
        $this->delete('/api/v1/token')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }
}
