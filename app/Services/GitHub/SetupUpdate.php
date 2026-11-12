<?php

namespace App\Services\GitHub;

use App\Models\Project;
use App\Services\Scaffolder\ProjectZip;
use Illuminate\Support\Str;

/**
 * Update on GitHub: the files Framework generates and owns, built again from
 * the project's current setup, proposed in a pull request. Only these files
 * are considered: the spec record (.framework/spec.json), the settings files
 * (settings.yml and settings/*.yml), and installed skills (.claude/skills/<key>
 * and .agents/skills/<key>). Notebooks, scripts, README, data, lock files,
 * .env, AGENTS.md, and Quarto files are never touched, nor anything the
 * repository's .gitignore matches.
 *
 * A file is Framework's to change only while the last commit that touched it
 * is Framework's own (the scaffold commit or an earlier update). Anything the
 * person edited or deleted since is left alone and listed for review. Nothing
 * is pushed to the default branch, and nothing is force-pushed: changes go
 * on a framework/update-* branch, reusing an open update's branch.
 */
class SetupUpdate
{
    public const TITLE = 'Update Framework setup';

    public const BRANCH_PREFIX = 'framework/update-';

    /** Commit messages Framework writes; a file whose last commit starts with one is unedited. */
    public const FRAMEWORK_COMMITS = ['Scaffold with Framework', self::TITLE];

    public function __construct(private ProjectZip $zips) {}

    /**
     * @return array{status: 'current'|'done', pr_url: ?string, pr_number: ?int, changed: list<string>, skipped: list<array{path: string, reason: string}>}
     */
    public function run(Project $project): array
    {
        $github = GitHub::for($project->user->githubAccount ?? throw new GitHubNotConnected('Connect GitHub first.'));
        $repo = $project->github_repo;
        $base = $github->repository($repo)['default_branch'];
        $current = $github->blobShas($repo, $base);

        $zip = $this->zips->build($project);
        try {
            $built = self::owned(ProjectFiles::unzip($zip, '', true));
        } finally {
            @unlink($zip);
        }

        $ignore = self::ignoreRules($github, $repo, $base, $current);
        $changed = [];
        $skipped = [];

        foreach (collect($built)->groupBy(fn ($bytes, string $path) => self::unit($path), true) as $unit => $files) {
            $files = $files->all();
            foreach (array_keys($files) as $path) {
                if (self::ignored($path, $ignore)) {
                    $skipped[] = ['path' => $path, 'reason' => 'matched by .gitignore'];
                    unset($files[$path]);
                }
            }
            $differ = array_filter($files, fn (string $bytes, string $path) => ($current[$path] ?? null) !== self::blobSha($bytes), ARRAY_FILTER_USE_BOTH);
            if ($differ === []) {
                continue;
            }

            if ($reason = self::editedReason($github->lastCommit($repo, $unit, $base))) {
                foreach (array_keys($differ) as $path) {
                    $skipped[] = ['path' => $path, 'reason' => $reason];
                }

                continue;
            }
            $changed += $differ;
        }

        ksort($changed);
        if ($changed === []) {
            return ['status' => 'current', 'pr_url' => null, 'pr_number' => null, 'changed' => [], 'skipped' => $skipped];
        }

        $body = $this->body($github, $repo, $base, $changed, $skipped);
        $open = collect($github->openPullRequests($repo))->first(fn (array $pr) => str_starts_with($pr['head']['ref'] ?? '', self::BRANCH_PREFIX)
            && ($pr['head']['repo']['full_name'] ?? null) === $repo);

        if ($open) {
            $github->commitFiles($repo, $changed, [], self::TITLE, $open['head']['ref']);
            $pr = $github->updatePullRequest($repo, $open['number'], $body);
        } else {
            $branch = self::BRANCH_PREFIX.substr(sha1($repo.json_encode(array_map(self::blobSha(...), $changed)).microtime()), 0, 7);
            $github->createBranch($repo, $branch, $github->branchSha($repo, $base));
            $github->commitFiles($repo, $changed, [], self::TITLE, $branch);
            $pr = $github->createPullRequest($repo, $branch, $base, self::TITLE, $body);
        }

        return ['status' => 'done', 'pr_url' => $pr['html_url'] ?? null, 'pr_number' => $pr['number'] ?? null, 'changed' => array_keys($changed), 'skipped' => $skipped];
    }

    /**
     * The built project's files that Framework owns.
     *
     * @param  array<string, string>  $files
     * @return array<string, string>
     */
    public static function owned(array $files): array
    {
        return array_filter($files, fn (string $path) => $path === '.framework/spec.json'
            || $path === 'settings.yml'
            || preg_match('#^settings/[^/]+\.ya?ml$#', $path)
            || preg_match('#^\.(claude|agents)/skills/[^/]+/.+#', $path), ARRAY_FILTER_USE_KEY);
    }

    /** What is checked for edits as one piece: a skill's folder, or a single file. */
    public static function unit(string $path): string
    {
        return preg_match('#^(\.(claude|agents)/skills/[^/]+)/#', $path, $m) ? $m[1] : $path;
    }

    /** Git's sha for a blob with these bytes, as the tree API reports it. */
    public static function blobSha(string $bytes): string
    {
        return sha1('blob '.strlen($bytes)."\0".$bytes);
    }

    /** Why a path is the person's now, or null when Framework may change it. */
    private static function editedReason(?array $commit): ?string
    {
        if ($commit === null) {
            return null; // new to the repository
        }
        $message = (string) ($commit['commit']['message'] ?? '');

        return collect(self::FRAMEWORK_COMMITS)->contains(fn (string $prefix) => str_starts_with($message, $prefix))
            ? null
            : 'changed in the repository since Framework wrote it';
    }

    /**
     * The repository's .gitignore rules that can cover Framework's files: the
     * root's and those in .framework/, settings/, and the skills folders.
     *
     * @param  array<string, string>  $current
     * @return list<array{base: string, pattern: string, negate: bool, dir: bool, anchored: bool}>
     */
    private static function ignoreRules(GitHub $github, string $repo, string $base, array $current): array
    {
        $rules = [];
        foreach (array_keys($current) as $path) {
            if (basename($path) !== '.gitignore' || ! preg_match('#^(\.gitignore|\.framework/|settings/|\.(claude|agents)/)#', $path)) {
                continue;
            }
            $dir = dirname($path) === '.' ? '' : dirname($path);
            foreach (self::parseIgnore((string) ($github->file($repo, $path, $base)['content'] ?? ''), $dir) as $rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /** @return list<array{base: string, pattern: string, negate: bool, dir: bool, anchored: bool}> */
    public static function parseIgnore(string $text, string $base = ''): array
    {
        $rules = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = rtrim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $negate = str_starts_with($line, '!');
            $line = ltrim($negate ? substr($line, 1) : $line);
            $dir = str_ends_with($line, '/');
            $line = trim($line, '/');
            if ($line === '') {
                continue;
            }
            $rules[] = ['base' => $base, 'pattern' => $line, 'negate' => $negate, 'dir' => $dir, 'anchored' => str_contains($line, '/')];
        }

        return $rules;
    }

    /** Whether .gitignore rules ignore a file; the last matching rule wins, as in git. */
    public static function ignored(string $path, array $rules): bool
    {
        $ignored = false;
        foreach ($rules as $rule) {
            if ($rule['base'] !== '' && ! str_starts_with($path, $rule['base'].'/')) {
                continue;
            }
            $relative = $rule['base'] === '' ? $path : substr($path, strlen($rule['base']) + 1);
            $glob = str_replace(['\*\*', '\*', '\?'], ['.*', '[^/]*', '[^/]'], preg_quote($rule['pattern'], '#'));
            $regex = '#'.($rule['anchored'] ? '^' : '(^|/)').$glob.($rule['dir'] ? '/.+$' : '(/.+)?$').'#';
            if (preg_match($regex, $relative)) {
                $ignored = ! $rule['negate'];
            }
        }

        return $ignored;
    }

    /** The pull request's description: what changed in the setup, the files, and those left alone. */
    private function body(GitHub $github, string $repo, string $base, array $changed, array $skipped): string
    {
        $lines = ['Framework Cloud built the files it manages again from this project\'s current setup.', ''];

        if (isset($changed['.framework/spec.json'])) {
            $before = json_decode((string) ($github->file($repo, '.framework/spec.json', $base)['content'] ?? '{}'), true) ?: [];
            $after = json_decode($changed['.framework/spec.json'], true) ?: [];
            $keys = collect(array_unique([...array_keys($before), ...array_keys($after)]))
                ->reject(fn (string $key) => $key === 'source')
                ->filter(fn (string $key) => json_encode($before[$key] ?? null) !== json_encode($after[$key] ?? null))
                ->map(fn (string $key) => Str::of($key)->replace('_', ' ')->toString())->values();
            if ($keys->isNotEmpty()) {
                $lines[] = '**Setup changes:** '.$keys->implode(', ').'.';
                $lines[] = '';
            }
        }

        $lines[] = '**Changed files**';
        foreach (array_keys($changed) as $path) {
            $lines[] = "- `{$path}`";
        }
        if ($skipped !== []) {
            $lines[] = '';
            $lines[] = '**Skipped files** (review by hand)';
            foreach ($skipped as $skip) {
                $lines[] = "- `{$skip['path']}`: {$skip['reason']}";
            }
        }
        $lines[] = '';
        $lines[] = 'Notebooks, scripts, data, README, lock files, AGENTS.md, and `.env` are never changed. Merging this pull request applies the update.';

        return implode("\n", $lines)."\n";
    }
}
