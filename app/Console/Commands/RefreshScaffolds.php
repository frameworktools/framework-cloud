<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\Scaffolder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

/**
 * Asks GitHub how running scaffolds went, for when the workflow_run webhook
 * doesn't arrive. Runs every minute; a run older than a day is left alone.
 */
class RefreshScaffolds extends Command
{
    protected $signature = 'fw:refresh-scaffolds';

    protected $description = 'Record finished GitHub scaffold runs whose webhook was missed';

    public function handle(Scaffolder $scaffolder): int
    {
        Project::where('scaffold_status', 'running')->whereNotNull('github_repo')
            ->where('updated_at', '<', now()->subMinute())->where('updated_at', '>', now()->subDay())
            ->with('user.githubAccount')->each(function (Project $project) use ($scaffolder) {
                try {
                    $scaffolder->refresh($project);
                } catch (RequestException|GitHubNotConnected) {
                    // Next minute, or projects_show, tries again
                }
            });

        return self::SUCCESS;
    }
}
