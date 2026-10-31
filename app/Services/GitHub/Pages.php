<?php

namespace App\Services\GitHub;

use App\Models\Project;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Publishes a project's notebooks with GitHub Pages, free of charge. The
 * cloud only sets the repository up: it adds a workflow that deploys the
 * published/ folder and turns Pages on. Publishing itself is a git push from
 * the person's computer, so documents never pass through the cloud.
 */
class Pages
{
    public const WORKFLOW = 'framework-pages.yml';

    public const WORKFLOW_PATH = '.github/workflows/'.self::WORKFLOW;

    public static function workflow(string $branch): string
    {
        if (! preg_match('/^[A-Za-z0-9._\/-]+$/', $branch)) {
            throw new RuntimeException("The default branch name {$branch} can't be used in a workflow.");
        }

        return str_replace('{{branch}}', $branch, File::get(resource_path('github/'.self::WORKFLOW)));
    }

    /**
     * Turn Pages on, then add the workflow. Committing the workflow starts
     * its first run, which deploys a front page, so the site works before
     * anything is published.
     *
     * @throws PagesFailed
     */
    public function enable(Project $project): void
    {
        $github = $this->github($project);

        try {
            $branch = $github->repository($project->github_repo)['default_branch'] ?? 'main';
            $site = $github->enablePages($project->github_repo);
            $github->putFile($project->github_repo, self::WORKFLOW_PATH, self::workflow($branch), 'Publish the published/ folder with GitHub Pages');
        } catch (RequestException $e) {
            throw new PagesFailed(self::explain($e, $project), previous: $e);
        }

        $project->update(['pages_url' => $site['html_url'] ?? null]);
    }

    /** Turn Pages off and remove the workflow. The published/ folder stays in the repository. */
    public function disable(Project $project): void
    {
        $github = $this->github($project);

        try {
            $github->disablePages($project->github_repo);
            $github->deleteFile($project->github_repo, self::WORKFLOW_PATH, 'Stop publishing with GitHub Pages');
        } catch (RequestException $e) {
            throw new PagesFailed(self::explain($e, $project), previous: $e);
        }

        $project->update(['pages_url' => null]);
    }

    private function github(Project $project): GitHub
    {
        return GitHub::for($project->user->githubAccount ?? throw new GitHubNotConnected('Connect GitHub first.'));
    }

    private static function explain(RequestException $e, Project $project): string
    {
        $message = $e->response->json('message') ?? 'GitHub returned an error.';

        return match (true) {
            $e->response->status() === 401 => 'GitHub did not accept the connection. Connect GitHub again.',
            $e->response->status() === 403 => 'The Framework app is not allowed to manage GitHub Pages on this repository. On GitHub, accept the app\'s request for the Pages permission, then try again.',
            $e->response->status() === 422 && $project->github_private => 'GitHub Pages on a private repository needs a paid GitHub plan. Make the repository public, or upgrade the account or organization that owns it.',
            default => "GitHub returned an error: {$message}",
        };
    }
}
