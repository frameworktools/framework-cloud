<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\SetupUpdate;
use App\Services\Scaffolder\ProjectZipFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Update on GitHub: the pull request with Framework's regenerated files, built off the request. */
class UpdateOnGitHub implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Project $project) {}

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
            $this->fail('GitHub returned an error: '.($e->response->json('message') ?? $e->response->status()));

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
