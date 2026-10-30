<?php

namespace App\Services\GitHub;

use App\Models\Project;
use App\Support\Layer;
use App\Support\ProjectLayers;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Scaffolds a project on GitHub without the cloud writing any project files:
 * it creates the repository, commits the project's layers and a one-time
 * workflow, and the workflow generates the project: the framework R package
 * for R projects, and fw for Stata projects. The webhook (or a status check)
 * records how the run went.
 */
class Scaffolder
{
    public const WORKFLOW = 'framework-scaffold.yml';

    public const WORKFLOW_PATH = '.github/workflows/'.self::WORKFLOW;

    public const LAYERS_PATH = '.framework/layers.json';

    public static function enabled(): bool
    {
        return filled(config('services.github.client_id')) && filled(config('services.github.framework_sha'));
    }

    /** Why a project in this language can't be scaffolded here, or null when it can. */
    public static function unavailableFor(string $language): ?string
    {
        return match ($language) {
            'r' => null,
            'stata' => filled(config('services.github.fw_version')) ? null : 'This server is not set up to scaffold Stata projects on GitHub yet.',
            default => 'Projects in this language cannot be scaffolded on GitHub yet.',
        };
    }

    /** The workflow file for a project's language, pinned to this deployment's tool version. */
    public static function workflow(string $language = 'r'): string
    {
        if ($language === 'stata') {
            return self::fwWorkflow();
        }

        $sha = config('services.github.framework_sha');

        if (! preg_match('/^[0-9a-f]{40}$/', (string) $sha)) {
            throw new RuntimeException('GITHUB_FRAMEWORK_SHA must be a full 40-character commit SHA.');
        }

        return str_replace('{{framework_ref}}', "frameworktools/framework@{$sha}", File::get(resource_path('github/'.self::WORKFLOW)));
    }

    private static function fwWorkflow(): string
    {
        $version = ltrim((string) config('services.github.fw_version'), 'v');
        $sha256 = strtolower((string) config('services.github.fw_sha256'));

        if (! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', $version)) {
            throw new RuntimeException('GITHUB_FW_VERSION must be a release version such as 0.1.0.');
        }

        if ($sha256 !== '' && ! preg_match('/^[0-9a-f]{64}$/', $sha256)) {
            throw new RuntimeException('GITHUB_FW_SHA256 must be a 64-character SHA-256 checksum.');
        }

        return strtr(File::get(resource_path('github/framework-scaffold-stata.yml')), [
            '{{fw_version}}' => $version,
            '{{fw_sha256}}' => $sha256,
        ]);
    }

    /**
     * @param  string|null  $org  an organization login, or null for the user's own account
     */
    public function start(Project $project, ?string $org, string $repoName, bool $private): void
    {
        $account = $project->user->githubAccount ?? throw new GitHubNotConnected('Connect GitHub first.');
        $github = GitHub::for($account);

        if ($reason = self::unavailableFor($project->language ?? 'r')) {
            $this->fail($project, $reason);

            return;
        }

        $envelope = ProjectLayers::for($project);

        if ($problems = Layer::envelopeProblems($envelope)) {
            $this->fail($project, 'The project layers are not valid: '.implode(' ', $problems));

            return;
        }

        try {
            $repo = $github->createRepository($org, $repoName, $private, $project->description);

            $project->update([
                'github_repo' => $repo['full_name'],
                'github_repo_id' => $repo['id'],
                'github_private' => $repo['private'],
                'scaffold_status' => 'running',
                'scaffold_error' => null,
            ]);

            // Layers first: the workflow commit is what starts the run
            $github->putFile($repo['full_name'], self::LAYERS_PATH, ProjectLayers::toJson($envelope), 'Add Framework project layers');
            $github->putFile($repo['full_name'], self::WORKFLOW_PATH, self::workflow($project->language ?? 'r'), 'Add Framework scaffold workflow');
        } catch (RequestException $e) {
            $this->fail($project, self::explain($e));
        }
    }

    /** Record a finished run. On success the one-time workflow is removed. */
    public function finish(Project $project, string $conclusion, ?string $runUrl): void
    {
        $succeeded = $conclusion === 'success';

        $project->update([
            'scaffold_status' => $succeeded ? 'done' : 'failed',
            'scaffold_run_url' => $runUrl,
            'scaffold_error' => $succeeded ? null : "The scaffold workflow finished with \"{$conclusion}\". Its log on GitHub says why.",
        ]);

        if ($succeeded && $project->user->githubAccount) {
            try {
                GitHub::for($project->user->githubAccount)
                    ->deleteFile($project->github_repo, self::WORKFLOW_PATH, 'Remove Framework scaffold workflow');
            } catch (RequestException|GitHubNotConnected) {
                // Harmless to leave: the workflow skips itself once the layers file is gone
            }
        }
    }

    /** Ask GitHub how the newest run went, for when a webhook was missed. */
    public function refresh(Project $project): void
    {
        $run = GitHub::for($project->user->githubAccount ?? throw new GitHubNotConnected('Connect GitHub first.'))
            ->latestRun($project->github_repo, self::WORKFLOW);

        if (! $run) {
            return;
        }

        if ($run['status'] === 'completed') {
            $this->finish($project, (string) $run['conclusion'], $run['html_url']);
        } else {
            $project->update(['scaffold_status' => 'running', 'scaffold_run_url' => $run['html_url']]);
        }
    }

    /** Run the workflow again after a failure (the layers file is still in the repository). */
    public function retry(Project $project): void
    {
        $github = GitHub::for($project->user->githubAccount ?? throw new GitHubNotConnected('Connect GitHub first.'));
        $repo = $github->repository($project->github_repo);

        $github->dispatchWorkflow($project->github_repo, self::WORKFLOW, $repo['default_branch']);
        $project->update(['scaffold_status' => 'running', 'scaffold_error' => null]);
    }

    private function fail(Project $project, string $message): void
    {
        $project->update(['scaffold_status' => 'failed', 'scaffold_error' => $message]);
    }

    private static function explain(RequestException $e): string
    {
        $message = $e->response->json('message') ?? 'GitHub returned an error.';
        $details = collect($e->response->json('errors', []))->pluck('message')->filter()->implode(' ');

        return match ($e->response->status()) {
            401 => 'GitHub did not accept the connection. Connect GitHub again.',
            403, 404 => 'The Framework app cannot create repositories there. Install it on that account, or allow it access to all repositories.',
            422 => trim("GitHub refused the repository: {$message}. {$details}"),
            default => "GitHub returned an error: {$message}",
        };
    }
}
