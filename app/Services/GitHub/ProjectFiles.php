<?php

namespace App\Services\GitHub;

use App\Models\Project;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Writes files into a project's GitHub repository, in one commit: data,
 * notebooks, a README, anything the blueprint does not make. The repository
 * must be scaffolded (or linked) first, so the commit never races the
 * scaffold workflow.
 */
class ProjectFiles
{
    public const MAX_FILES = 1000;

    /** What a zip may expand to. */
    public const MAX_EXPANDED_BYTES = 200 * 1024 * 1024;

    /** The repository to write to, or a validation error saying why it can't be written yet. */
    public static function repository(Project $project): string
    {
        if (! $project->github_repo) {
            throw ValidationException::withMessages(['project' => 'This project has no GitHub repository. Create one with projects_scaffold, or link one with projects_github_link.']);
        }
        if (in_array($project->scaffold_status, ['pending', 'running'], true)) {
            throw ValidationException::withMessages(['project' => 'The repository is still being scaffolded. Write files once projects_show reports scaffold status "done".']);
        }
        if ($project->scaffold_status === 'failed') {
            throw ValidationException::withMessages(['project' => 'Scaffolding failed: '.$project->scaffold_error.' Retry it with projects_scaffold first.']);
        }

        return $project->github_repo;
    }

    /**
     * @param  array<string, string>  $files  path => bytes
     * @param  list<string>  $delete
     * @return array{repository: string, commit: string, url: string, branch: string, written: list<string>, deleted: list<string>}
     */
    public function commit(Project $project, array $files, array $delete, ?string $message): array
    {
        $repo = self::repository($project);
        $files = collect($files)->mapWithKeys(fn ($bytes, $path) => [self::path($path) => $bytes])->all();
        $delete = array_values(array_unique(array_map(self::path(...), $delete)));

        if ($files === [] && $delete === []) {
            throw ValidationException::withMessages(['files' => 'Send at least one file to write or delete.']);
        }
        if (count($files) + count($delete) > self::MAX_FILES) {
            throw ValidationException::withMessages(['files' => 'One commit can change at most '.self::MAX_FILES.' files.']);
        }

        $github = GitHub::for($project->user->githubAccount ?? throw new GitHubNotConnected('Connect GitHub first.'));
        $message = trim((string) $message) ?: (count($files) === 1 && $delete === [] ? 'Add '.array_key_first($files) : 'Add project files');

        try {
            $commit = $github->commitFiles($repo, $files, $delete, $message);
        } catch (RequestException $e) {
            throw ValidationException::withMessages(['github' => self::explain($e)]);
        }

        return [
            'repository' => $repo,
            'commit' => $commit['sha'],
            'url' => $commit['url'],
            'branch' => $commit['branch'],
            // True when the files already matched: nothing was committed, and commit is the current head
            'unchanged' => $commit['unchanged'],
            'written' => array_keys($files),
            'deleted' => $delete,
        ];
    }

    /**
     * A zip's files as path => bytes, under $prefix. Folders and macOS
     * clutter are skipped. With $stripTopFolder, a single folder that holds
     * everything (as in GitHub's Download ZIP) is dropped from the paths.
     *
     * @return array<string, string>
     */
    public static function unzip(string $zipPath, string $prefix = '', bool $stripTopFolder = false): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw ValidationException::withMessages(['file' => 'The file is not a zip archive.']);
        }

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', $stat['name']);
            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store') {
                continue;
            }
            $names[$i] = $name;
        }

        if ($stripTopFolder && $names !== []) {
            $tops = array_unique(array_map(fn ($n) => str_contains($n, '/') ? strstr($n, '/', true) : '', $names));
            if (count($tops) === 1 && $tops[array_key_first($tops)] !== '') {
                $top = $tops[array_key_first($tops)].'/';
                $names = array_map(fn ($n) => substr($n, strlen($top)), $names);
            }
        }

        if (count($names) > self::MAX_FILES) {
            throw ValidationException::withMessages(['file' => 'The zip holds more than '.self::MAX_FILES.' files.']);
        }

        $prefix = trim($prefix, '/');
        $files = [];
        $total = 0;
        foreach ($names as $i => $name) {
            $total += $zip->statIndex($i)['size'];
            if ($total > self::MAX_EXPANDED_BYTES) {
                throw ValidationException::withMessages(['file' => 'The zip expands to more than 200 MB.']);
            }
            $files[self::path(($prefix === '' ? '' : $prefix.'/').$name)] = (string) $zip->getFromIndex($i);
        }
        $zip->close();

        return $files;
    }

    /** A safe repository path: relative, no .. or .git, forward slashes. */
    public static function path(string $path): string
    {
        $clean = trim(str_replace('\\', '/', $path), '/');
        $segments = explode('/', $clean);

        if ($clean === '' || strlen($clean) > 400 || in_array('..', $segments, true) || in_array('.', $segments, true)
            || in_array('', $segments, true) || $segments[0] === '.git' || preg_match('/[\x00-\x1f]/', $clean)) {
            throw ValidationException::withMessages(['path' => "\"{$path}\" is not a usable file path. Use a relative path such as data/survey.csv."]);
        }

        return $clean;
    }

    private static function explain(RequestException $e): string
    {
        return match ($e->response->status()) {
            401 => 'GitHub did not accept the connection. Connect GitHub again.',
            403, 404 => 'The Framework app cannot write to that repository. Install it on the repository\'s owner and give it access to the repository.',
            409 => 'The repository is empty or its branch moved during the commit. Try again.',
            default => 'GitHub returned an error: '.($e->response->json('message') ?? $e->response->status()),
        };
    }
}
