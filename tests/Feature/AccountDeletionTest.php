<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\InstanceSetting;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(Project $project): void
    {
        LedgerEntry::create([
            'project_id' => $project->id, 'sequence' => 1, 'name' => 'inputs.raw.survey',
            'hash' => str_repeat('ab', 32), 'created_at' => now(),
        ]);
    }

    private function publish(Project $project): Document
    {
        $file = UploadedFile::fake()->createWithContent('report.html', '<html><body>Results</body></html>');

        return app(ArtifactPublisher::class)->publish($project, ['file' => $file])->document;
    }

    public function test_deleting_an_account_removes_its_ledger_documents_tokens_and_settings(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $archived = Project::factory()->for($user)->create();
        $this->ledger($project);
        $this->ledger($archived);
        $document = $this->publish($project);
        $file = $document->latestVersion()->disk_path;
        Storage::disk('local')->assertExists($file);
        $archived->delete();
        $user->globalSettings();
        $user->createToken('fw');
        InstanceSetting::create(['key' => 'github_client_id', 'value' => 'x', 'updated_by' => $user->id]);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->assertGreaterThan(0, Setting::where('owner_id', $user->id)->count());
        $this->assertSame(2, LedgerEntry::count());

        $this->actingAs($user)
            ->delete('/account/account', ['confirm_email' => $user->email])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertModelMissing($user);
        $this->assertSame(0, Project::withTrashed()->count());
        $this->assertSame(0, LedgerEntry::count());
        $this->assertSame(0, Document::count());
        $this->assertSame(0, Setting::count());
        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNull(InstanceSetting::where('key', 'github_client_id')->value('updated_by'));
        Storage::disk('local')->assertMissing($file);
    }

    public function test_deleting_a_user_directly_removes_its_settings_and_its_projects_settings(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $user->globalSettings();
        Setting::forOwner($project);
        $other = User::factory()->create();
        $other->globalSettings();

        $user->delete();

        $this->assertSame(0, Setting::where('owner_type', $user->getMorphClass())->where('owner_id', $user->id)->count());
        $this->assertSame(0, Setting::where('owner_type', $project->getMorphClass())->where('owner_id', $project->id)->count());
        $this->assertSame(1, Setting::where('owner_type', $other->getMorphClass())->where('owner_id', $other->id)->count());
    }

    public function test_deleting_an_account_leaves_other_accounts_alone(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Project::factory()->for($user)->create();
        $theirs = Project::factory()->for($other)->create();
        $this->ledger($mine);
        $this->ledger($theirs);
        $document = $this->publish($theirs);
        $other->globalSettings();
        $other->createToken('fw');
        $theirs->issuePullToken();
        $mine->issuePullToken();
        $user->createToken('fw');

        $this->actingAs($user)
            ->delete('/account/account', ['confirm_email' => $user->email])
            ->assertRedirect('/');

        $this->assertModelExists($other);
        $this->assertModelExists($theirs);
        $this->assertSame([$theirs->id], LedgerEntry::pluck('project_id')->all());
        Storage::disk('local')->assertExists($document->latestVersion()->disk_path);
        $this->assertSame(1, Setting::where('owner_id', $other->id)->count());
        $this->assertSame(2, PersonalAccessToken::count());
    }

    public function test_deletion_needs_the_account_email(): void
    {
        $user = User::factory()->create();
        $this->ledger(Project::factory()->for($user)->create());

        $this->actingAs($user)
            ->delete('/account/account', ['confirm_email' => 'someone@example.org'])
            ->assertSessionHasErrors('confirm_email');

        $this->assertModelExists($user);
        $this->assertSame(1, LedgerEntry::count());
    }
}
