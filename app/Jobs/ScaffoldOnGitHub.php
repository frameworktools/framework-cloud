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
