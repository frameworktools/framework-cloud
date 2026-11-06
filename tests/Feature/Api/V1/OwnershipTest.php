<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\ReportPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Everything an account token (or a connected agent, which uses one) can change or delete belongs
 * to that account. Another account's projects, reports, skills, artifacts, and Trash are out of reach,
 * admins included.
 */
class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    private Report $report;

    private Skill $skill;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create(['email_verified_at' => now(), 'can_publish' => true]);
        $this->project = Project::factory()->for($this->owner)->create(['slug' => 'penguin-study']);
        app(ArtifactPublisher::class)->publish($this->project, ['slug' => 'mass', 'file' => UploadedFile::fake()->createWithContent('mass.csv', "a\n1\n")]);
        $this->report = app(ReportPublisher::class)->create($this->project, ['title' => 'Body mass']);
        $this->skill = Skill::create(['user_id' => $this->owner->id, 'key' => 'penguin-checks', 'name' => 'Checks', 'description' => 'x', 'visibility' => 'public']);
        $this->skill->publishVersion(['content' => "---\nname: penguin-checks\ndescription: x\n---\nOne.", 'files' => []]);
    }

    private function as(User $user): static
    {
        app('auth')->forgetGuards();

        return $this->withToken($user->createToken('t')->plainTextToken);
    }

    public function test_another_account_cannot_change_or_delete_anything(): void
    {
        foreach ([User::factory()->create(), User::factory()->create(['is_admin' => true])] as $other) {
            $slug = $this->project->slug;
            $this->as($other)->deleteJson("/api/v1/projects/{$slug}")->assertNotFound();
            $this->as($other)->patchJson("/api/v1/projects/{$slug}", ['name' => 'Taken'])->assertNotFound();
            $this->as($other)->deleteJson("/api/v1/projects/{$slug}/reports/{$this->report->slug}")->assertNotFound();
            $this->as($other)->patchJson("/api/v1/projects/{$slug}/reports/{$this->report->slug}", ['title' => 'Taken'])->assertNotFound();
            $this->as($other)->postJson("/api/v1/projects/{$slug}/artifacts", ['filename' => 'x.html', 'content' => 'x'])->assertNotFound();
            $this->as($other)->postJson("/api/v1/projects/{$slug}/linked-artifacts", ['artifact' => 'mass'])->assertNotFound();
            $this->as($other)->getJson("/api/v1/projects/{$slug}/artifacts/mass")->assertNotFound();

            // A public skill can be read and copied, never changed or deleted
            $this->as($other)->getJson('/api/v1/skills/penguin-checks')->assertOk()->assertJsonPath('editable', false);
            $this->as($other)->putJson('/api/v1/skills/penguin-checks', ['content' => "---\nname: penguin-checks\ndescription: x\n---\nMine."])->assertForbidden();
            $this->as($other)->deleteJson('/api/v1/skills/penguin-checks')->assertForbidden();
        }

        $this->assertNull($this->project->fresh()->deleted_at);
        $this->assertSame($this->project->name, $this->project->fresh()->name);
        $this->assertNull($this->report->fresh()->deleted_at);
        $this->assertSame('Body mass', $this->report->fresh()->title);
        $this->assertNull($this->skill->fresh()->deleted_at);
        $this->assertSame(1, $this->skill->fresh()->latestVersion->version);
    }

    public function test_another_account_cannot_see_or_restore_the_trash(): void
    {
        $this->report->delete();
        $this->skill->delete();
        $this->project->delete();
        $other = User::factory()->create(['is_admin' => true]);

        $this->actingAs($other)->get('/trash')->assertInertia(fn ($page) => $page->has('items', 0));
        $this->post("/trash/project/{$this->project->id}/restore")->assertNotFound();
        $this->post("/trash/skill/{$this->skill->id}/restore")->assertNotFound();
        $this->post("/trash/report/{$this->report->id}/restore")->assertNotFound();
        $this->assertTrue($this->project->fresh()->trashed());
    }

    public function test_a_project_token_cannot_delete(): void
    {
        app('auth')->forgetGuards();
        $token = $this->project->issuePullToken();

        $this->withToken($token)->deleteJson("/api/v1/projects/penguin-study/reports/{$this->report->slug}")->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/projects/penguin-study')->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/skills/penguin-checks')->assertForbidden();
        $this->assertNull($this->report->fresh()->deleted_at);
    }
}
