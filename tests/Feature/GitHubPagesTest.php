<?php

namespace Tests\Feature;

use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\Pages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.github.client_id' => 'Iv1.test', 'services.github.client_secret' => 'secret']);
    }

    private function project(array $attributes = []): Project
    {
        $user = User::factory()->create();
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();

        return $user->projects()->create($attributes + [
            'name' => 'S', 'slug' => 's', 'github_repo' => 'ada/s', 'github_repo_id' => 900,
            'github_private' => false, 'scaffold_status' => 'done',
        ]);
    }

    /** GitHub with Pages off until the POST turns it on. */
    private function fakeGitHub(int $createStatus = 201): void
    {
        $on = false;

        Http::fake([
            'api.github.com/repos/ada/s/pages' => function (HttpRequest $request) use (&$on, $createStatus) {
                return match ($request->method()) {
                    'POST' => ($on = $createStatus < 300)
                        ? Http::response(['html_url' => 'https://ada.github.io/s/'], $createStatus)
                        : Http::response(['message' => 'Your current plan does not support GitHub Pages for this repository.'], $createStatus),
                    'GET' => $on ? Http::response(['html_url' => 'https://ada.github.io/s/', 'build_type' => 'workflow']) : Http::response(['message' => 'Not Found'], 404),
                    default => Http::response(null, 204),
                };
            },
            'api.github.com/repos/ada/s/contents/*' => fn (HttpRequest $request) => $request->method() === 'GET'
                ? Http::response(['type' => 'file', 'sha' => 'wf-sha', 'size' => 10, 'content' => ''])
                : Http::response([], 200),
            'api.github.com/repos/ada/s' => Http::response(['full_name' => 'ada/s', 'default_branch' => 'trunk']),
        ]);
    }

    public function test_turning_pages_on_adds_the_workflow_and_records_the_site(): void
    {
        $project = $this->project();
        $this->fakeGitHub();

        $this->actingAs($project->user)->post("/projects/{$project->id}/pages")->assertSessionHasNoErrors();

        $this->assertSame('https://ada.github.io/s/', $project->fresh()->pages_url);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/repos/ada/s/pages') && $r['build_type'] === 'workflow');
        Http::assertSent(function ($r) {
            if ($r->method() !== 'PUT' || ! str_ends_with($r->url(), '/contents/.github/workflows/framework-pages.yml')) {
                return false;
            }
            $workflow = base64_decode($r['content']);

            // Deploys only from the repository's default branch, which here is not main
            return str_contains($workflow, 'branches: ["trunk"]') && str_contains($workflow, 'path: published');
        });
    }

    public function test_a_private_repository_on_a_free_plan_explains_the_limit(): void
    {
        $project = $this->project(['github_private' => true]);
        $this->fakeGitHub(createStatus: 422);

        $this->actingAs($project->user)->post("/projects/{$project->id}/pages")
            ->assertSessionHasErrors(['github' => 'GitHub Pages on a private repository needs a paid GitHub plan. Make the repository public, or upgrade the account or organization that owns it.']);

        $this->assertNull($project->fresh()->pages_url);
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/contents/'));
    }

    public function test_turning_pages_off_removes_the_site_and_the_workflow(): void
    {
        $project = $this->project(['pages_url' => 'https://ada.github.io/s/']);
        $this->fakeGitHub();

        $this->actingAs($project->user)->delete("/projects/{$project->id}/pages")->assertSessionHasNoErrors();

        $this->assertNull($project->fresh()->pages_url);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/repos/ada/s/pages'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/contents/.github/workflows/framework-pages.yml') && $r['sha'] === 'wf-sha');
    }

    public function test_only_the_owner_of_a_scaffolded_project_can_turn_pages_on(): void
    {
        $project = $this->project();
        $pending = $project->user->projects()->create(['name' => 'P', 'slug' => 'p', 'github_repo' => 'ada/p', 'scaffold_status' => 'running']);
        Http::fake();

        $this->actingAs(User::factory()->create())->post("/projects/{$project->id}/pages")->assertNotFound();
        $this->actingAs($project->user)->post("/projects/{$pending->id}/pages")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_the_workflow_refuses_a_branch_name_it_cannot_quote(): void
    {
        $this->expectException(\RuntimeException::class);

        Pages::workflow('main"; rm -rf');
    }
}
