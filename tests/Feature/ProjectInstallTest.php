<?php

namespace Tests\Feature;

use App\Jobs\ScaffoldOnGitHub;
use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Overview's installation boxes: a token for local setup and Create on GitHub. */
class ProjectInstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.client_id' => 'Iv1.test',
            'services.github.app_slug' => 'framework-pub',
            'services.github.framework_sha' => str_repeat('a', 40),
        ]);
        Queue::fake();
        Http::fake(['api.github.com/user/installations*' => Http::response(['installations' => [
            ['id' => 2, 'account' => ['login' => 'lab', 'type' => 'Organization'], 'repository_selection' => 'all'],
            ['id' => 3, 'account' => ['login' => 'ada', 'type' => 'User'], 'repository_selection' => 'all'],
        ]])]);
    }

    private function connected(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();

        return $user->fresh();
    }

    private function project(User $user, array $attributes = []): Project
    {
        return Project::factory()->for($user)->create(['language' => 'r'] + $attributes);
    }

    private function sensitive(): array
    {
        return ['resolved' => json_encode(['layers' => [['kind' => 'base', 'key' => 'framework/project_sensitive', 'layer' => ['security' => ['sensitive' => true]]]]])];
    }

    public function test_overview_issues_a_token_and_stays_on_overview(): void
    {
        $user = $this->connected();
        $project = $this->project($user);
        $overview = "/projects/{$project->slug}";

        $this->actingAs($user)->from($overview)->post("/projects/{$project->id}/token")
            ->assertRedirect($overview)
            ->assertSessionHas('status', 'project-token')
            ->assertSessionHas('token_language', 'r')
            ->assertSessionHas('token');

        $this->assertSame(1, $project->tokens()->count());
    }

    public function test_overview_offers_create_on_github_for_a_project_without_a_repository(): void
    {
        $user = $this->connected();
        $project = $this->project($user, $this->sensitive());

        $this->actingAs($user)->get("/projects/{$project->slug}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('githubSetup.enabled', true)
                ->where('githubSetup.login', 'ada')
                ->where('githubSetup.sensitive', true)
                ->has('githubSetup.owners', 2));

        $linked = $this->project($user, ['github_repo' => 'lab/course', 'scaffold_status' => 'done']);
        $this->actingAs($user)->get("/projects/{$linked->slug}/github")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('githubSetup', null));
    }

    public function test_create_on_github_scaffolds_the_repository(): void
    {
        $user = $this->connected();
        $project = $this->project($user);

        $this->actingAs($user)->from("/projects/{$project->slug}")
            ->post("/projects/{$project->id}/scaffold", ['github' => ['owner' => 'lab', 'name' => 'course', 'private' => false]])
            ->assertRedirect("/projects/{$project->slug}")
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', $project->fresh()->scaffold_status);
        Queue::assertPushed(ScaffoldOnGitHub::class, fn ($job) => $job->project->is($project) && $job->org === 'lab' && $job->repoName === 'course' && ! $job->private);

        // The page shows the pending scaffold before the repository exists
        $this->actingAs($user)->get("/projects/{$project->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.github.status', 'pending'));
    }

    public function test_create_on_github_refuses_a_project_with_a_repository(): void
    {
        $user = $this->connected();
        $project = $this->project($user, ['github_repo' => 'lab/course', 'scaffold_status' => 'done']);

        $this->actingAs($user)->post("/projects/{$project->id}/scaffold", ['github' => ['owner' => 'lab', 'name' => 'other']])
            ->assertSessionHasErrors('github');

        $running = $this->project($user, ['scaffold_status' => 'pending']);
        $this->actingAs($user)->post("/projects/{$running->id}/scaffold", ['github' => ['owner' => 'lab', 'name' => 'other']])
            ->assertSessionHasErrors('github');

        Queue::assertNothingPushed();
    }

    public function test_create_on_github_needs_the_app_installed_on_the_owner(): void
    {
        $user = $this->connected();
        $project = $this->project($user);

        $this->actingAs($user)->post("/projects/{$project->id}/scaffold", ['github' => ['owner' => 'elsewhere', 'name' => 'course']])
            ->assertSessionHasErrors('github.owner');

        $this->assertNull($project->fresh()->scaffold_status);
        Queue::assertNothingPushed();
    }

    public function test_a_sensitive_project_gets_a_private_repository(): void
    {
        $user = $this->connected();
        $project = $this->project($user, $this->sensitive());

        $this->actingAs($user)->post("/projects/{$project->id}/scaffold", ['github' => ['owner' => 'ada', 'name' => 'private-study', 'private' => false]])
            ->assertSessionHasErrors('github.private');
        Queue::assertNothingPushed();

        // Private by default
        $this->actingAs($user)->post("/projects/{$project->id}/scaffold", ['github' => ['owner' => 'ada', 'name' => 'private-study']])
            ->assertSessionHasNoErrors();
        Queue::assertPushed(ScaffoldOnGitHub::class, fn ($job) => $job->org === null && $job->private);
    }

    public function test_people_cannot_scaffold_each_others_projects(): void
    {
        $project = $this->project($this->connected());

        $this->actingAs(User::factory()->create())->post("/projects/{$project->id}/scaffold", ['github' => ['owner' => 'lab', 'name' => 'course']])
            ->assertNotFound();
    }
}
