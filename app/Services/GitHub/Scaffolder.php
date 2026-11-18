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
 * for R projects, framework-py for Python projects, and fw for Stata projects. The webhook (or a status check)
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
            'python' => preg_match('/^[0-9a-f]{40}$/', (string) config('services.github.framework_py_sha')) ? null : 'This server is not set up to scaffold Python projects on GitHub yet.',
            'stata' => filled(config('services.github.fw_version')) ? null : 'This server is not set up to scaffold Stata projects on GitHub yet.',
            default => 'Projects in this language cannot be scaffolded on GitHub yet.',
        };
    }

    /**
     * What an admin changes so GitHub can scaffold projects (in this
     * language), or null when nothing needs changing.
     */
    public static function setupHint(?string $language = null): ?string
    {
        if (blank(config('services.github.client_id'))) {
            return 'Create the GitHub App on the Server setup page.';
        }

        if (! self::enabled()) {
            return 'Remove GITHUB_FRAMEWORK_SHA from the environment, or set it to a full framework commit SHA.';
        }

        if ($language === null || self::unavailableFor($language) === null) {
            return null;
        }

        return match ($language) {
            'python' => 'Remove GITHUB_FRAMEWORK_PY_SHA from the environment, or set it to a full framework-py commit SHA.',
            'stata' => 'Remove GITHUB_FW_VERSION from the environment, or set it to an fw release such as 0.6.3.',
            default => null,
        };
    }

    /** The workflow file for a project's language, pinned to this deployment's tool version. */
    public static function workflow(string $language = 'r'): string
    {
        if ($language === 'stata') {
            return self::fwWorkflow();
        }

        if ($language === 'python') {
            $sha = config('services.github.framework_py_sha');

            if (! preg_match('/^[0-9a-f]{40}$/', (string) $sha)) {
                throw new RuntimeException('GITHUB_FRAMEWORK_PY_SHA must be a full 40-character commit SHA.');
            }

            return str_replace('{{framework_py_ref}}', "frameworktools/framework-py@{$sha}", File::get(resource_path('github/framework-scaffold-python.yml')));
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
            throw new RuntimeException('GITHUB_FW_VERSION must be a release version such as 0.3.0.');
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
                // The repository starts from the current setup
                'github_setup_applied_at' => now(),
            ]);

            // Layers first: the workflow commit is what starts the run
            $github->putFile($repo['full_name'], self::LAYERS_PATH, ProjectLayers::toJson($envelope), 'Add Framework project layers');
            $github->putFile($repo['full_name'], self::WORKFLOW_PATH, self::workflow($project->language ?? 'r'), 'Add Framework scaffold workflow');
        } catch (RequestException $e) {
            $this->fail($project, self::explain($e));
        }
    }

    /**
     * Record a finished run. On success the one-time workflow is removed. On
     * failure the error comes from the failed step's log, when GitHub has it.
     */
    public function finish(Project $project, string $conclusion, ?string $runUrl, ?int $runId = null): void
    {
        $succeeded = $conclusion === 'success';
        $runId ??= preg_match('#/actions/runs/(\d+)#', (string) $runUrl, $m) ? (int) $m[1] : null;

        $project->update([
            'scaffold_status' => $succeeded ? 'done' : 'failed',
            'scaffold_run_url' => $runUrl,
            'scaffold_error' => $succeeded ? null : ($this->failureReason($project, $conclusion, $runId)
                ?? "The scaffold workflow finished with \"{$conclusion}\". Its log on GitHub says why."),
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

    /** The failed step and its error lines, or null when GitHub can't say. */
    private function failureReason(Project $project, string $conclusion, ?int $runId): ?string
    {
        if ($conclusion !== 'failure' || ! $runId || ! $project->user->githubAccount || ! $project->github_repo) {
            return null;
        }

        try {
            $github = GitHub::for($project->user->githubAccount);
            $job = collect($github->runJobs($project->github_repo, $runId))->firstWhere('conclusion', 'failure');
            if (! $job) {
                return null;
            }
            $step = collect($job['steps'] ?? [])->firstWhere('conclusion', 'failure')['name'] ?? null;
            $lines = self::errorLines($github->jobLog($project->github_repo, (int) $job['id']));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $where = $step ? "the {$step} step" : 'a step';

        return $lines === []
            ? "The scaffold workflow failed in {$where}. Its log on GitHub says why."
            : "The scaffold workflow failed in {$where}:\n".implode("\n", $lines);
    }

    /**
     * The error lines from a failed job's log: the output of the failing
     * step just before GitHub's "Process completed with exit code" line,
     * from the first error line on, without timestamps or colour codes.
     *
     * @return list<string>
     */
    public static function errorLines(string $log): array
    {
        $lines = array_map(
            fn (string $line) => rtrim(preg_replace(['/^\d{4}-\d\d-\d\dT[\d:.]+Z ?/', '/\e\[[0-9;]*[A-Za-z]/'], '', $line)),
            preg_split('/\R/', $log),
        );

        $end = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^##\[error\]Process completed with exit code/', $line)) {
                $end = $i;
                break;
            }
        }
        if ($end === null) {
            return [];
        }

        // The failing step's output starts after its ##[endgroup]
        $start = 0;
        for ($i = $end - 1; $i >= 0; $i--) {
            if (str_starts_with($lines[$i], '##[endgroup]')) {
                $start = $i + 1;
                break;
            }
        }
        $output = array_values(array_filter(
            array_slice($lines, $start, $end - $start),
            fn (string $line) => trim($line) !== '' && $line !== 'Execution halted' && ! str_starts_with($line, '##[group]') && ! str_starts_with($line, '##[endgroup]'),
        ));

        $first = null;
        foreach ($output as $i => $line) {
            if (str_starts_with($line, '##[error]') || preg_match('/^(Error\b|Traceback|error:|fatal:|panic:)/i', $line)) {
                $first = $i;
                break;
            }
        }
        $output = $first === null ? array_slice($output, -8) : array_slice($output, $first, 12);
        $output = array_map(fn (string $line) => mb_strimwidth(preg_replace('/^##\[(error|warning)\]/', '', $line), 0, 300, '…'), $output);

        return array_values($output);
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
            $this->finish($project, (string) $run['conclusion'], $run['html_url'], $run['id'] ?? null);
        } else {
            $project->update(['scaffold_status' => 'running', 'scaffold_run_url' => $run['html_url']]);
        }
    }

    /**
     * Scaffold again after a failure. The layers and the workflow are
     * committed again, so the run uses this server's current package pin and
     * the layers as Cloud resolves them now. A changed workflow file starts
     * the run; an unchanged one is started by hand.
     */
    public function retry(Project $project): void
    {
        $github = GitHub::for($project->user->githubAccount ?? throw new GitHubNotConnected('Connect GitHub first.'));
        $language = $project->language ?? 'r';

        if ($reason = self::unavailableFor($language)) {
            $this->fail($project, $reason);

            return;
        }

        $envelope = ProjectLayers::for($project);
        if ($problems = Layer::envelopeProblems($envelope)) {
            $this->fail($project, 'The project layers are not valid: '.implode(' ', $problems));

            return;
        }

        $layers = ProjectLayers::toJson($envelope);
        $workflow = self::workflow($language);

        $project->update(['scaffold_status' => 'running', 'scaffold_error' => null]);

        if (($github->file($project->github_repo, self::LAYERS_PATH)['content'] ?? null) !== $layers) {
            $github->putFile($project->github_repo, self::LAYERS_PATH, $layers, 'Update Framework project layers');
        }

        if (($github->file($project->github_repo, self::WORKFLOW_PATH)['content'] ?? null) !== $workflow) {
            $github->putFile($project->github_repo, self::WORKFLOW_PATH, $workflow, 'Update Framework scaffold workflow');
        } else {
            $github->dispatchWorkflow($project->github_repo, self::WORKFLOW, $github->repository($project->github_repo)['default_branch']);
        }
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
