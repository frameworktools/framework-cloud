<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\Installations;
use App\Services\GitHub\SetupUpdate;
use App\Services\Scaffolder\ProjectZip;
use App\Services\Scaffolder\ProjectZipFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Update on GitHub: the pull request with Framework's regenerated files, built off the request. */
class UpdateOnGitHub implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Project $project) {}

    /** Why the project's repository can't be updated now, or null when it can. */
    public static function problem(Project $project): ?string
    {
        $account = $project->user->githubAccount;
        $owner = Str::before((string) $project->github_repo, '/');

        return match (true) {
            ! $project->github_repo => 'This project has no GitHub repository.',
            in_array($project->scaffold_status, ['pending', 'running', 'failed'], true) => 'Finish creating the repository first.',
            in_array($project->github_update['status'] ?? null, ['pending', 'running'], true) => 'An update is already under way.',
            ! ProjectZip::enabled() => 'This server is not set up to build project files.',
            ! $account => 'Connect GitHub in settings first.',
            ! collect(Installations::for($account) ?? [])->contains(fn (array $o) => strcasecmp($o['login'], $owner) === 0) => Installations::missingOn($owner),
            default => null,
        };
    }

    /** Queue an update. Check problem() first. */
    public static function start(Project $project): void
    {
        $project->update(['github_update' => ['status' => 'pending']]);
        self::dispatch($project);
    }

    public function handle(SetupUpdate $update): void
    {
        $this->project->update(['github_update' => ['status' => 'running']]);

        try {
            $result = $update->run($this->project->fresh());
        } catch (GitHubNotConnected|ProjectZipFailed $e) {
            $this->fail($e->getMessage());

            return;
        } catch (ValidationException $e) {
            $this->fail(collect($e->errors())->flatten()->first() ?? 'The project files could not be read.');

            return;
        } catch (RequestException $e) {
            $message = $e->response->json('message');
            $this->fail($e->response->status() === 403 && $message === 'Resource not accessible by integration'
                ? 'The Framework app on GitHub lacks the Pull requests permission. Approve its updated permissions in GitHub under Settings > Applications, then try again.'
                : 'GitHub returned an error: '.($message ?? $e->response->status()));

            return;
        }

        $this->project->update([
            'github_update' => $result + ['finished_at' => now()->toIso8601String()],
            'github_setup_applied_at' => now(),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        $this->fail('Updating the repository failed unexpectedly. Try again.');
    }

    private function fail(string $message): void
    {
        $this->project->update(['github_update' => ['status' => 'failed', 'error' => $message, 'finished_at' => now()->toIso8601String()]]);
    }
}
