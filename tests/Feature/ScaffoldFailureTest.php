<?php

namespace Tests\Feature;

use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\Scaffolder;
use App\Support\PackageSkills;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A failed scaffold run says why, and Retry runs it again at the current pin. */
class ScaffoldFailureTest extends TestCase
{
    use RefreshDatabase;

    /** The end of a real failed job log, from the Scaffold step on */
    private const LOG = <<<'LOG'
2026-11-18T15:06:45.6607126Z ✔ Installed framework 2.0.10 (github::frameworktools/framework@0f12d41) (29ms)
2026-11-18T15:06:45.7395569Z ##[group]Run framework::project_create_from_layers(".framework/layers.json", path = ".", interactive = FALSE)
2026-11-18T15:06:45.7396211Z [36;1mframework::project_create_from_layers(".framework/layers.json", path = ".", interactive = FALSE)[0m
2026-11-18T15:06:45.7401587Z shell: /usr/local/bin/Rscript {0}
2026-11-18T15:06:45.7401803Z env:
2026-11-18T15:06:45.7401993Z   R_LIBS_USER: /home/runner/work/_temp/Library
2026-11-18T15:06:45.7403670Z ##[endgroup]
2026-11-18T15:06:45.9409831Z ##[error]Error: The resolved project spec is invalid:
2026-11-18T15:06:45.9417605Z   - skills.ggplot2-exploring: not shipped with this version of framework
2026-11-18T15:06:45.9418546Z   - skills.ggplot2-publishing: not shipped with this version of framework
2026-11-18T15:06:45.9419661Z Execution halted
2026-11-18T15:06:45.9482571Z ##[error]Process completed with exit code 1.
2026-11-18T15:06:45.9591404Z Post job cleanup.
2026-11-18T15:06:46.0386165Z [command]/usr/bin/git version
LOG;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.client_id' => 'Iv1.test',
            'services.github.webhook_secret' => 'hook-secret',
            'services.github.framework_sha' => PackageSkills::defaultRef('r'),
        ]);
    }

    private function project(array $attributes = []): Project
    {
        $user = User::factory()->create();
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();

        return $user->projects()->create($attributes + [
            'name' => 'Study', 'slug' => 'study', 'language' => 'r', 'github_repo' => 'ada/study', 'github_repo_id' => 900, 'scaffold_status' => 'running',
        ])->fresh();
    }

    public function test_the_error_lines_come_from_the_failed_step(): void
    {
        $this->assertSame([
            'Error: The resolved project spec is invalid:',
            '  - skills.ggplot2-exploring: not shipped with this version of framework',
            '  - skills.ggplot2-publishing: not shipped with this version of framework',
        ], Scaffolder::errorLines(self::LOG));

        $this->assertSame([], Scaffolder::errorLines("2026-11-18T15:06:45Z all good\n"));
    }

    public function test_a_failed_run_records_its_step_and_error_lines(): void
    {
        $project = $this->project();
        Http::fake([
            'api.github.com/repos/ada/study/actions/runs/77/jobs*' => Http::response(['jobs' => [[
                'id' => 5, 'conclusion' => 'failure',
                'steps' => [['name' => 'Install framework', 'conclusion' => 'success'], ['name' => 'Scaffold', 'conclusion' => 'failure'], ['name' => 'Commit', 'conclusion' => 'skipped']],
            ]]]),
            'api.github.com/repos/ada/study/actions/jobs/5/logs' => Http::response(self::LOG),
        ]);

        $payload = json_encode([
            'action' => 'completed',
            'workflow_run' => ['id' => 77, 'path' => Scaffolder::WORKFLOW_PATH, 'conclusion' => 'failure', 'html_url' => 'https://github.com/ada/study/actions/runs/77'],
            'repository' => ['id' => 900],
        ]);
        $this->call('POST', '/api/github/webhook', [], [], [], [
            'HTTP_X_GITHUB_EVENT' => 'workflow_run',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'hook-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertNoContent();

        $project->refresh();
        $this->assertSame('failed', $project->scaffold_status);
        $this->assertSame("The scaffold workflow failed in the Scaffold step:\n"
            ."Error: The resolved project spec is invalid:\n"
            ."  - skills.ggplot2-exploring: not shipped with this version of framework\n"
            .'  - skills.ggplot2-publishing: not shipped with this version of framework', $project->scaffold_error);
    }

    public function test_without_a_log_the_failure_points_at_github(): void
    {
        $project = $this->project();
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Resource not accessible by integration'], 403)]);

        app(Scaffolder::class)->finish($project, 'failure', 'https://github.com/ada/study/actions/runs/77');

        $this->assertSame('The scaffold workflow finished with "failure". Its log on GitHub says why.', $project->fresh()->scaffold_error);
    }

    public function test_retry_commits_the_current_layers_and_workflow(): void
    {
        $this->seed(BlueprintSeeder::class);
        // Resolved before framework retired the two ggplot2 skills, and committed with the old pin
        $envelope = ['spec_version' => 1, 'name' => 'Study', 'author' => ['name' => 'Ada', 'email' => '', 'affiliation' => ''], 'layers' => [
            ['kind' => 'base', 'key' => 'framework/project', 'version' => 1, 'layer' => ['type' => 'project']],
            ['kind' => 'facet', 'key' => 'framework/visualization-r', 'version' => 1, 'layer' => ['skills' => ['ggplot2-exploring' => ['package' => true], 'ggplot2-publishing' => ['package' => true]]]],
            ['kind' => 'project', 'key' => 'study choices', 'layer' => ['skills' => ['ggplot2-figures' => null]]],
            ['kind' => 'project', 'key' => 'study', 'layer' => ['language' => 'r']],
        ]];
        $project = $this->project(['scaffold_status' => 'failed', 'scaffold_error' => 'The scaffold workflow failed.', 'resolved' => json_encode($envelope)]);
        $old = str_replace(PackageSkills::defaultRef('r'), '0f12d4155e03966a019b7edb24ab4dd49ed6573c', Scaffolder::workflow('r'));

        Http::fake([
            'api.github.com/repos/ada/study/contents/*' => fn (HttpRequest $request) => $request->method() === 'GET'
                ? Http::response(['type' => 'file', 'sha' => 'old', 'size' => 1, 'content' => base64_encode(str_contains($request->url(), 'workflows') ? $old : json_encode($envelope))])
                : Http::response(['content' => ['sha' => 'new']], 200),
        ]);

        $this->actingAs($project->user)->post("/projects/{$project->id}/scaffold/retry")->assertRedirect();

        $this->assertSame([null, 'running'], [$project->fresh()->scaffold_error, $project->fresh()->scaffold_status]);
        $puts = Http::recorded(fn (HttpRequest $r) => $r->method() === 'PUT')->map(fn ($pair) => $pair[0])->values();
        $this->assertCount(2, $puts);
        $layers = json_decode(base64_decode($puts[0]['content']), true);
        $this->assertSame(ProjectLayers::toJson(PackageSkills::upgrade($envelope)), base64_decode($puts[0]['content']));
        $this->assertSame(['ggplot2-figures' => ['package' => true]], $layers['layers'][1]['layer']['skills']);
        $this->assertStringContainsString('frameworktools/framework@'.PackageSkills::defaultRef('r'), base64_decode($puts[1]['content']));
        // The changed workflow file starts the run
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/dispatches'));
    }

    public function test_retry_with_nothing_changed_starts_the_workflow_by_hand(): void
    {
        $project = $this->project(['scaffold_status' => 'failed', 'scaffold_error' => 'The scaffold workflow failed.']);
        $layers = ProjectLayers::toJson(ProjectLayers::for($project));

        Http::fake([
            'api.github.com/repos/ada/study/contents/*' => fn (HttpRequest $request) => Http::response(['type' => 'file', 'sha' => 'same', 'size' => 1,
                'content' => base64_encode(str_contains($request->url(), 'workflows') ? Scaffolder::workflow('r') : $layers)]),
            'api.github.com/repos/ada/study' => Http::response(['default_branch' => 'main']),
            'api.github.com/repos/ada/study/actions/workflows/*/dispatches' => Http::response(null, 204),
        ]);

        app(Scaffolder::class)->retry($project);

        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'PUT');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/actions/workflows/framework-scaffold.yml/dispatches') && $r['ref'] === 'main');
    }
}
