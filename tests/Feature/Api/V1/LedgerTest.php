<?php

namespace Tests\Feature\Api\V1;

use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function entry(array $overrides = []): array
    {
        return array_merge([
            'name' => 'inputs.raw.enrollment',
            'hash' => str_repeat('ab', 32),
            'size_bytes' => 1024,
            'client' => ['device' => 'test', 'package_version' => '1.1.0'],
        ], $overrides);
    }

    public function test_project_token_appends_entries_with_monotonic_sequence(): void
    {
        $project = Project::factory()->create();
        $token = $project->issuePullToken();

        $this->withToken($token)->postJson('/api/v1/ledger', $this->entry())
            ->assertCreated()
            ->assertJsonPath('sequence', 1);

        $this->withToken($token)->postJson('/api/v1/ledger', $this->entry(['hash' => str_repeat('cd', 32)]))
            ->assertCreated()
            ->assertJsonPath('sequence', 2);
    }

    public function test_history_is_readable_and_filterable_by_name(): void
    {
        $project = Project::factory()->create();
        $token = $project->issuePullToken();

        $this->withToken($token)->postJson('/api/v1/ledger', $this->entry());
        $this->withToken($token)->postJson('/api/v1/ledger', $this->entry(['name' => 'inputs.raw.other']));

        $this->withToken($token)->getJson('/api/v1/ledger?name=inputs.raw.enrollment')
            ->assertOk()
            ->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.hash', str_repeat('ab', 32));
    }

    public function test_entries_are_append_only_at_the_model_layer(): void
    {
        $project = Project::factory()->create();
        $entry = LedgerEntry::create([
            'project_id' => $project->id, 'sequence' => 1, 'name' => 'x',
            'hash' => str_repeat('ab', 32), 'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $entry->update(['hash' => str_repeat('ff', 32)]);
    }

    public function test_deletes_are_refused_at_the_model_layer(): void
    {
        $project = Project::factory()->create();
        $entry = LedgerEntry::create([
            'project_id' => $project->id, 'sequence' => 1, 'name' => 'x',
            'hash' => str_repeat('ab', 32), 'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_user_tokens_cannot_touch_the_ledger(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/ledger', $this->entry())->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/ledger')->assertForbidden();
    }

    public function test_non_hex_hashes_are_rejected(): void
    {
        $project = Project::factory()->create();

        $this->withToken($project->issuePullToken())
            ->postJson('/api/v1/ledger', $this->entry(['hash' => 'not-a-hash!']))
            ->assertStatus(422);
    }
}
