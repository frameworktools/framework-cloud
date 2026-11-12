<?php

namespace Tests\Feature;

use App\Jobs\UpdateOnGitHub;
use App\Models\Blueprint;
use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\SetupUpdate;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use ZipArchive;

/** Update on GitHub: Framework's regenerated files in a pull request, and nothing else. */
class ProjectGitHubUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** What the scaffolder builds: Framework's files and the person's. */
    private const BUILT = [
        '.framework/spec.json' => "{\"name\": \"Course\", \"packages\": [\"dplyr\"]}\n",
        'settings.yml' => "default:\n  packages: [dplyr]\n",
        'settings/data.yml' => "data: {}\n",
        'settings/private.yml' => "secret: {}\n",
        '.claude/skills/framework/SKILL.md' => "# Framework\n",
        'notebooks/analysis.qmd' => "# Analysis\n",
        'README.md' => "# Course\n",
        'AGENTS.md' => "# Agents\n",
        'renv.lock' => "{}\n",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlueprintSeeder::class);
        config([
            'services.scaffolder.url' => 'http://scaffolder:8000',
            'services.github.client_id' => 'Iv1.test',
            'services.github.app_slug' => 'framework-pub',
            'services.github.framework_sha' => str_repeat('a', 40),
        ]);
    }

    private function project(): Project
    {
        $user = User::factory()->create();
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();
        $this->actingAs($user)->post('/projects', ['name' => 'Course', 'blueprint_id' => $blueprint->id, 'language' => 'r'])->assertRedirect();

        $project = $user->projects()->listed()->firstOrFail();
        $project->update(['github_repo' => 'lab/course', 'scaffold_status' => 'done']);

        return $project->fresh();
    }

    private static function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fake-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString("course/{$name}", $content);
        }
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    /**
     * Fake the scaffolder and GitHub.
     *
     * @param  array<string, string>  $repo  path => content now on the default branch
     * @param  array<string, string>  $lastCommits  path => message of the last commit that touched it
     * @param  list<array<string, mixed>>  $pulls  open pull requests
     */
    private function fake(array $repo, array $lastCommits = [], array $pulls = []): void
    {
        $zip = self::zip(self::BUILT);
        Http::fake(function (HttpRequest $r) use ($repo, $lastCommits, $pulls, $zip) {
            $url = $r->url();
            $path = parse_url($url, PHP_URL_PATH);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return match (true) {
                str_contains($url, 'scaffolder:8000/build') => Http::response($zip, 200, ['Content-Type' => 'application/zip']),
                $path === '/repos/lab/course' => Http::response(['default_branch' => 'main', 'full_name' => 'lab/course']),
                $path === '/repos/lab/course/git/trees/main' && $r->method() === 'GET' => Http::response(['tree' => collect($repo)
                    ->map(fn ($content, $p) => ['path' => $p, 'type' => 'blob', 'sha' => SetupUpdate::blobSha($content)])->values()->all()]),
                str_starts_with($path, '/repos/lab/course/contents/') => isset($repo[substr($path, strlen('/repos/lab/course/contents/'))])
                    ? Http::response(['type' => 'file', 'content' => base64_encode($repo[substr($path, strlen('/repos/lab/course/contents/'))]), 'sha' => 'x', 'size' => 1])
                    : Http::response([], 404),
                $path === '/repos/lab/course/commits' => Http::response(isset($lastCommits[$query['path'] ?? ''])
                    ? [['sha' => 'c1', 'commit' => ['message' => $lastCommits[$query['path']]]]] : []),
                $path === '/repos/lab/course/pulls' && $r->method() === 'GET' => Http::response($pulls),
                $path === '/repos/lab/course/pulls' => Http::response(['number' => 7, 'html_url' => 'https://github.com/lab/course/pull/7'], 201),
                str_starts_with($path, '/repos/lab/course/pulls/') => Http::response(['number' => 4, 'html_url' => 'https://github.com/lab/course/pull/4']),
                str_starts_with($path, '/repos/lab/course/git/ref/heads/') => Http::response(['object' => ['sha' => 'head1']]),
                $path === '/repos/lab/course/git/refs' => Http::response(['ref' => 'x'], 201),
                str_starts_with($path, '/repos/lab/course/git/commits/') => Http::response(['tree' => ['sha' => 'tree1']]),
                $path === '/repos/lab/course/git/blobs' => Http::response(['sha' => 'blob1'], 201),
                $path === '/repos/lab/course/git/trees' => Http::response(['sha' => 'tree2'], 201),
                $path === '/repos/lab/course/git/commits' => Http::response(['sha' => 'commit2'], 201),
                str_starts_with($path, '/repos/lab/course/git/refs/heads/') => Http::response(['object' => ['sha' => 'commit2']]),
                default => Http::response(['message' => "Unexpected {$r->method()} {$url}"], 500),
            };
        });
    }

    private function runUpdate(Project $project): Project
    {
        (new UpdateOnGitHub($project))->handle(app(SetupUpdate::class));

        return $project->fresh();
    }

    /** @return list<string> paths in the commit's tree */
    private function committedPaths(): array
    {
        $tree = Http::recorded(fn (HttpRequest $r) => str_ends_with($r->url(), '/git/trees') && $r->method() === 'POST')->first();

        return $tree ? array_column($tree[0]['tree'], 'path') : [];
    }

    /** The repository as Framework scaffolded it: its files as built now. */
    private function scaffolded(): array
    {
        return SetupUpdate::owned(self::BUILT) + ['README.md' => "# My course\n", 'notebooks/analysis.qmd' => "# Mine\n"];
    }

    public function test_an_up_to_date_repository_gets_no_pull_request(): void
    {
        $project = $this->project();
        $this->fake($this->scaffolded());

        $project = $this->runUpdate($project);

        $this->assertSame('current', $project->github_update['status']);
        $this->assertNotNull($project->github_setup_applied_at);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/pulls') && $r->method() !== 'GET');
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/git/refs'));
    }

    public function test_changed_files_go_on_a_new_branch_in_a_pull_request(): void
    {
        $project = $this->project();
        $repo = ['settings.yml' => "default:\n  packages: []\n", '.framework/spec.json' => "{\"name\": \"Course\", \"packages\": []}\n"] + $this->scaffolded();
        unset($repo['.claude/skills/framework/SKILL.md']);
        $this->fake($repo, ['settings.yml' => 'Scaffold with Framework', '.framework/spec.json' => "Scaffold with Framework\n\nfrom layers"]);

        $project = $this->runUpdate($project);

        $this->assertSame('done', $project->github_update['status']);
        $this->assertSame('https://github.com/lab/course/pull/7', $project->github_update['pr_url']);
        $this->assertSame(['.claude/skills/framework/SKILL.md', '.framework/spec.json', 'settings.yml'], $project->github_update['changed']);
        $this->assertEqualsCanonicalizing(['.claude/skills/framework/SKILL.md', '.framework/spec.json', 'settings.yml'], $this->committedPaths());

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/git/refs') && str_starts_with($r['ref'], 'refs/heads/framework/update-') && $r['sha'] === 'head1');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/pulls') && $r->method() === 'POST'
            && $r['title'] === 'Update Framework setup' && $r['base'] === 'main' && str_starts_with($r['head'], 'framework/update-')
            && str_contains($r['body'], '`settings.yml`') && str_contains($r['body'], '**Setup changes:** packages.'));
        // Never the default branch
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/git/refs/heads/main') && $r->method() === 'PATCH');
    }

    public function test_an_open_update_pull_request_is_updated(): void
    {
        $project = $this->project();
        $this->fake(['settings.yml' => "old\n"] + $this->scaffolded(), ['settings.yml' => 'Update Framework setup (#3)'], [
            ['number' => 2, 'head' => ['ref' => 'feature/plots', 'repo' => ['full_name' => 'lab/course']]],
            ['number' => 4, 'head' => ['ref' => 'framework/update-abc1234', 'repo' => ['full_name' => 'lab/course']]],
        ]);

        $project = $this->runUpdate($project);

        $this->assertSame('https://github.com/lab/course/pull/4', $project->github_update['pr_url']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/git/refs/heads/framework%2Fupdate-abc1234') && $r->method() === 'PATCH');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/pulls/4') && $r->method() === 'PATCH' && str_contains($r['body'], '`settings.yml`'));
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/pulls') && $r->method() === 'POST');
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/git/refs'));
    }

    public function test_ignored_and_edited_files_are_left_alone(): void
    {
        $project = $this->project();
        $repo = [
            '.gitignore' => "settings/private.yml\n.env\n",
            'settings.yml' => "default:\n  packages: [mine]\n",
            'settings/data.yml' => "data: {mine: true}\n",
        ] + $this->scaffolded();
        unset($repo['settings/private.yml']);
        $this->fake($repo, ['settings.yml' => 'Scaffold with Framework', 'settings/data.yml' => 'Point data at the shared drive']);

        $project = $this->runUpdate($project);

        $this->assertSame(['settings.yml'], $this->committedPaths());
        $this->assertEqualsCanonicalizing([
            ['path' => 'settings/private.yml', 'reason' => 'matched by .gitignore'],
            ['path' => 'settings/data.yml', 'reason' => 'changed in the repository since Framework wrote it'],
        ], $project->github_update['skipped']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/pulls') && $r->method() === 'POST'
            && str_contains($r['body'], '`settings/data.yml`: changed in the repository since Framework wrote it'));
    }

    public function test_the_gitignore_matcher_follows_git(): void
    {
        $rules = [...SetupUpdate::parseIgnore("*.log\n/settings/local.yml\nsecrets/\n!keep.log\n"), ...SetupUpdate::parseIgnore("backups/\n", '.framework')];

        $this->assertTrue(SetupUpdate::ignored('a/b/debug.log', $rules));
        $this->assertFalse(SetupUpdate::ignored('keep.log', $rules));
        $this->assertTrue(SetupUpdate::ignored('settings/local.yml', $rules));
        $this->assertFalse(SetupUpdate::ignored('other/settings/local.yml', $rules));
        $this->assertTrue(SetupUpdate::ignored('.claude/skills/secrets/SKILL.md', $rules));
        $this->assertTrue(SetupUpdate::ignored('.framework/backups/x/settings.yml', $rules));
        $this->assertFalse(SetupUpdate::ignored('.framework/spec.json', $rules));
    }

    public function test_update_needs_the_app_on_the_repository_owner(): void
    {
        Queue::fake();
        $project = $this->project();
        Http::fake(['api.github.com/user/installations*' => Http::response(['installations' => [
            ['id' => 3, 'account' => ['login' => 'ada', 'type' => 'User'], 'repository_selection' => 'all'],
        ]])]);

        $this->actingAs($project->user)->post("/projects/{$project->id}/github/update")
            ->assertSessionHasErrors(['github' => 'Install the Framework app on lab first.']);
        Queue::assertNothingPushed();
        $this->assertNull($project->fresh()->github_update);
    }

    public function test_a_setup_edit_offers_the_update_and_requesting_it_queues_the_job(): void
    {
        Queue::fake();
        $project = $this->project();
        Http::fake(['api.github.com/user/installations*' => Http::response(['installations' => [
            ['id' => 2, 'account' => ['login' => 'lab', 'type' => 'Organization'], 'repository_selection' => 'all'],
        ]])]);
        $user = $project->user;

        $this->actingAs($user)->get("/projects/{$project->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.github.update_available', false));

        $this->actingAs($user)->put("/projects/{$project->id}", [
            'name' => 'Course', 'blueprint_id' => Blueprint::whereNull('user_id')->where('key', 'project')->value('id'), 'language' => 'python', 'update_setup' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->get("/projects/{$project->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.github.update_available', true));

        $this->actingAs($user)->post("/projects/{$project->id}/github/update")->assertSessionHasNoErrors();
        Queue::assertPushed(UpdateOnGitHub::class, fn ($job) => $job->project->is($project));
        $this->assertSame('pending', $project->fresh()->github_update['status']);

        // One at a time
        $this->actingAs($user)->post("/projects/{$project->id}/github/update")->assertSessionHasErrors('github');
    }
}
