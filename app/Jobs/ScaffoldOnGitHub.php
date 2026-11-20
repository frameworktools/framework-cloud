<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\Scaffolder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ScaffoldOnGitHub implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public Project $project,
        public ?string $org,
        public string $repoName,
        public bool $private,
    ) {}

    /**
     * Queue a scaffold into a new repository. The repository it will create
     * is recorded first, so the project reports it before the job runs.
     *
     * @param  array{org: ?string, name: string, private: bool, repository: string}  $target  from RepositoryTarget::for()
     */
    public static function start(Project $project, array $target): void
    {
        $project->update(['scaffold_status' => 'pending', 'scaffold_error' => null, 'scaffold_run_url' => null, 'scaffold_repo' => $target['repository']]);
        self::dispatch($project, $target['org'], $target['name'], $target['private']);
    }

    public function handle(Scaffolder $scaffolder): void
    {
        try {
            $scaffolder->start($this->project, $this->org, $this->repoName, $this->private);
        } catch (GitHubNotConnected $e) {
            $this->project->update(['scaffold_status' => 'failed', 'scaffold_error' => $e->getMessage()]);
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->project->update([
            'scaffold_status' => 'failed',
            'scaffold_error' => 'Creating the repository failed unexpectedly. Try again.',
        ]);
    }
}
