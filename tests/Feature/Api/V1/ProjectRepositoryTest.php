<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\ScaffoldOnGitHub;
use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

/** A project's repository through the API: scaffold status and retry, linking, and files. */
class ProjectRepositoryTest extends TestCase
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
    }

    private function connected(): User
    {
        $user = User::factory()->create();
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();

        return $user->fresh();
    }

    private function fakeCommits(): void
    {
        Http::fake([
            'api.github.com/repos/lab/course' => Http::response(['id' => 7, 'full_name' => 'lab/course', 'private' => false, 'default_branch' => 'main', 'html_url' => 'https://github.com/lab/course', 'permissions' => ['push' => true]]),
            'api.github.com/repos/lab/course/git/ref/heads/main' => Http::response(['object' => ['sha' => 'head1']]),
            'api.github.com/repos/lab/course/git/commits/head1' => Http::response(['tree' => ['sha' => 'tree1']]),
            'api.github.com/repos/lab/course/git/blobs' => Http::response(['sha' => 'blob1'], 201),
            'api.github.com/repos/lab/course/git/trees' => Http::response(['sha' => 'tree2'], 201),
            'api.github.com/repos/lab/course/git/commits' => Http::response(['sha' => 'commit2'], 201),
            'api.github.com/repos/lab/course/git/refs/heads/main' => Http::response(['object' => ['sha' => 'commit2']]),
            'api.github.com/repos/lab/course/git/trees/main*' => Http::response(['tree' => [
                ['path' => 'README.md', 'type' => 'blob', 'size' => 12],
                ['path' => 'data', 'type' => 'tree'],
                ['path' => 'data/survey.csv', 'type' => 'blob', 'size' => 300],
            ], 'truncated' => false]),
        ]);
    }

    public function test_show_reports_the_scaffold_even_before_a_repository_exists(): void
    {
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['scaffold_status' => 'failed', 'scaffold_error' => 'Install the app.']);

        $this->withToken($user->createToken('t')->plainTextToken)->getJson("/api/v1/projects/{$project->slug}")
            ->assertOk()
            ->assertJsonPath('github', null)
            ->assertJsonPath('scaffold.scaffold_status', 'failed')
            ->assertJsonPath('scaffold.scaffold_error', 'Install the app.');
    }

    public function test_scaffold_creates_the_repository_for_a_project_without_one(): void
    {
        Queue::fake();
        Http::fake(['api.github.com/user/installations*' => Http::response(['installations' => [
            ['id' => 2, 'account' => ['login' => 'lab', 'type' => 'Organization'], 'repository_selection' => 'all'],
        ]])]);
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['language' => 'r']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/projects/{$project->slug}/scaffold")->assertStatus(422)->assertJsonValidationErrors('github');

        $this->withToken($token)->postJson("/api/v1/projects/{$project->slug}/scaffold", ['github' => ['owner' => 'lab', 'name' => 'course', 'private' => false]])
            ->assertStatus(202)
            ->assertJsonPath('scaffold_status', 'pending')
            ->assertJsonPath('repository', 'lab/course');

        Queue::assertPushed(ScaffoldOnGitHub::class, fn ($job) => $job->org === 'lab' && $job->repoName === 'course' && ! $job->private);
    }

    public function test_link_adopts_an_existing_repository(): void
    {
        $this->fakeCommits();
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['scaffold_status' => 'pending']);

        $this->withToken($user->createToken('t')->plainTextToken)->postJson("/api/v1/projects/{$project->slug}/github", ['repository' => 'lab/course'])
            ->assertOk()->assertJsonPath('repository', 'lab/course');

        $project->refresh();
        $this->assertSame('lab/course', $project->github_repo);
        $this->assertSame(7, (int) $project->github_repo_id);
        $this->assertNull($project->scaffold_status);
    }

    public function test_files_are_written_in_one_commit(): void
    {
        $this->fakeCommits();
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'scaffold_status' => 'done']);

        $this->withToken($user->createToken('t')->plainTextToken)->postJson("/api/v1/projects/{$project->slug}/files", [
            'files' => [
                ['path' => 'README.md', 'content' => '# Course'],
                ['path' => 'data/x.bin', 'content' => base64_encode("\x00\x01"), 'encoding' => 'base64'],
            ],
            'delete' => ['old.txt'],
            'message' => 'Add course files',
        ])->assertCreated()
            ->assertJsonPath('commit', 'commit2')
            ->assertJsonPath('written', ['README.md', 'data/x.bin'])
            ->assertJsonPath('deleted', ['old.txt']);

        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/git/trees') && count($r['tree']) === 3
            && $r['tree'][2] === ['path' => 'old.txt', 'mode' => '100644', 'type' => 'blob', 'sha' => null]);
        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/git/blobs') && $r['content'] === base64_encode("\x00\x01"));
        Http::assertSent(fn (ClientRequest $r) => $r->method() === 'PATCH' && $r['sha'] === 'commit2');
    }

    public function test_writes_wait_for_the_scaffold_and_refuse_unsafe_paths(): void
    {
        $this->fakeCommits();
        $user = $this->connected();
        $token = $user->createToken('t')->plainTextToken;
        $running = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'scaffold_status' => 'running']);
        $done = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'scaffold_status' => 'done']);

        $this->withToken($token)->postJson("/api/v1/projects/{$running->slug}/files", ['files' => [['path' => 'a.md', 'content' => 'x']]])
            ->assertStatus(422)->assertJsonValidationErrors('project');

        foreach (['../etc/passwd', '.git/config', '/', 'a//b'] as $bad) {
            $this->withToken($token)->postJson("/api/v1/projects/{$done->slug}/files", ['files' => [['path' => $bad, 'content' => 'x']]])
                ->assertStatus(422)->assertJsonValidationErrors('path');
        }
    }

    public function test_listing_files(): void
    {
        $this->fakeCommits();
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'scaffold_status' => 'done']);

        $this->withToken($user->createToken('t')->plainTextToken)->getJson("/api/v1/projects/{$project->slug}/files")
            ->assertOk()->assertJsonPath('files', [['path' => 'README.md', 'size' => 12], ['path' => 'data/survey.csv', 'size' => 300]]);
    }

    public function test_an_upload_link_commits_a_zip_once(): void
    {
        $this->fakeCommits();
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'scaffold_status' => 'done']);

        $link = $this->withToken($user->createToken('t')->plainTextToken)->postJson("/api/v1/projects/{$project->slug}/files/uploads", ['unzip' => true, 'strip_top_folder' => true])
            ->assertCreated()->json('upload_url');

        $zipPath = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('course-main/README.md', '# Course');
        $zip->addFromString('course-main/data/survey.csv', "a,b\n1,2\n");
        $zip->addFromString('__MACOSX/course-main/._README.md', 'junk');
        $zip->close();
        $bytes = file_get_contents($zipPath);
        unlink($zipPath);

        $path = parse_url($link, PHP_URL_PATH);
        $this->call('PUT', $path, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $bytes)
            ->assertCreated()->assertJsonPath('written', ['README.md', 'data/survey.csv']);

        $this->call('PUT', $path, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $bytes)
            ->assertNotFound();
    }

    public function test_active_and_the_public_download_link(): void
    {
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'github_private' => false, 'scaffold_status' => 'done']);
        $token = $user->createToken('t')->plainTextToken;

        $url = $this->withToken($token)->patchJson("/api/v1/projects/{$project->slug}", ['active' => false, 'public_download' => true])
            ->assertOk()->assertJsonPath('active', false)->json('public_download_url');
        $this->assertNotNull($url);
        $this->withToken($token)->getJson('/api/v1/projects')->assertJsonPath('projects.0.active', false);

        $zipPath = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('course-abc123/README.md', '# Course');
        $zip->addFromString('course-abc123/course.Rproj', 'Version: 1.0');
        $zip->close();
        Http::fake(['github.com/lab/course/archive/HEAD.zip' => Http::response(file_get_contents($zipPath), 200, ['Content-Type' => 'application/zip'])]);
        unlink($zipPath);

        $this->app['auth']->forgetGuards();
        $response = $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
        $downloaded = tempnam(sys_get_temp_dir(), 'zip');
        copy($response->baseResponse->getFile()->getPathname(), $downloaded);
        $zip->open($downloaded);
        $this->assertSame('# Course', $zip->getFromName("{$project->slug}/README.md"));
        $this->assertNotFalse($zip->getFromName("{$project->slug}/course.Rproj"));
        $zip->close();
        unlink($downloaded);

        $this->withToken($token)->patchJson("/api/v1/projects/{$project->slug}", ['public_download' => false])->assertJsonPath('public_download_url', null);
        $this->get(parse_url($url, PHP_URL_PATH))->assertNotFound();
    }

    public function test_a_private_repository_gets_no_public_download(): void
    {
        $user = $this->connected();
        $project = Project::factory()->for($user)->create(['github_repo' => 'lab/course', 'github_private' => true]);

        $this->withToken($user->createToken('t')->plainTextToken)->patchJson("/api/v1/projects/{$project->slug}", ['public_download' => true])
            ->assertStatus(422)->assertJsonValidationErrors('public_download');
    }

    public function test_the_project_page_marks_a_project_inactive_and_makes_a_download_link(): void
    {
        $user = User::factory()->create(['setup_completed_at' => now(), 'email_verified_at' => now()]);
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->put("/projects/{$project->id}/active", ['active' => false])->assertRedirect();
        $this->assertFalse($project->fresh()->active);
        $this->actingAs($user)->get('/projects')->assertInertia(fn ($page) => $page->where('projects.0.active', false));

        $this->actingAs($user)->put("/projects/{$project->id}/public-download", ['on' => true])->assertRedirect();
        $this->assertNotNull($project->fresh()->public_download_key);
        $this->actingAs($user)->put("/projects/{$project->id}/public-download", ['on' => false]);
        $this->assertNull($project->fresh()->public_download_key);
    }
}
