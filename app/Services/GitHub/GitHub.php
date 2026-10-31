<?php

namespace App\Services\GitHub;

use App\Models\GitHubAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Calls the GitHub REST API as one user, through the Framework GitHub App.
 * A user token can do only what both the user and the app's installation
 * allow, so the app's permissions bound everything here.
 *
 * Without an account it calls anonymously (public repositories only).
 */
class GitHub
{
    public const API = 'https://api.github.com';

    public function __construct(private ?GitHubAccount $account = null) {}

    public static function for(?GitHubAccount $account): self
    {
        return new self($account);
    }

    /** The authenticated user's profile. */
    public function user(): array
    {
        return $this->get('/user')->json();
    }

    /**
     * Accounts where the app is installed and this user can act: their own
     * account and organizations.
     *
     * @return list<array{id: int, login: string, type: string, repository_selection: string}>
     */
    public function installations(): array
    {
        return collect($this->get('/user/installations', ['per_page' => 100])->json('installations', []))
            ->map(fn (array $i) => [
                'id' => $i['id'],
                'login' => $i['account']['login'],
                'type' => $i['account']['type'],
                'repository_selection' => $i['repository_selection'],
            ])
            ->values()->all();
    }

    /** Create a repository on the user's account, or on an organization when $org is given. */
    public function createRepository(?string $org, string $name, bool $private, ?string $description): array
    {
        return $this->send('post', $org ? "/orgs/{$org}/repos" : '/user/repos', [
            'name' => $name,
            'private' => $private,
            'description' => $description,
            'auto_init' => true,
        ])->json();
    }

    public function repository(string $repo): array
    {
        return $this->get("/repos/{$repo}")->json();
    }

    /** A file's content and blob sha, or null when it does not exist. */
    public function file(string $repo, string $path, ?string $ref = null): ?array
    {
        $response = $this->request()->get(self::API."/repos/{$repo}/contents/".$this->encodePath($path), array_filter(['ref' => $ref]));

        if ($response->status() === 404) {
            return null;
        }

        $data = $response->throw()->json();

        if (! is_array($data) || ($data['type'] ?? null) !== 'file') {
            return null;
        }

        return ['content' => base64_decode($data['content'] ?? ''), 'sha' => $data['sha'], 'size' => $data['size']];
    }

    /**
     * A directory's entries, or null when it does not exist.
     *
     * @return list<array{name: string, path: string, type: string, size: int}>|null
     */
    public function directory(string $repo, string $path, ?string $ref = null): ?array
    {
        $response = $this->request()->get(self::API."/repos/{$repo}/contents/".$this->encodePath($path), array_filter(['ref' => $ref]));

        if ($response->status() === 404) {
            return null;
        }

        $data = $response->throw()->json();

        return array_is_list($data ?? [])
            ? array_map(fn (array $e) => ['name' => $e['name'], 'path' => $e['path'], 'type' => $e['type'], 'size' => $e['size'] ?? 0], $data)
            : null;
    }

    /** Create or replace a file in one commit. */
    public function putFile(string $repo, string $path, string $content, string $message, ?string $branch = null): void
    {
        $existing = $this->file($repo, $path, $branch);

        $this->send('put', "/repos/{$repo}/contents/".$this->encodePath($path), array_filter([
            'message' => $message,
            'content' => base64_encode($content),
            'sha' => $existing['sha'] ?? null,
            'branch' => $branch,
        ]));
    }

    public function deleteFile(string $repo, string $path, string $message): void
    {
        $existing = $this->file($repo, $path);

        if ($existing) {
            $this->send('delete', "/repos/{$repo}/contents/".$this->encodePath($path), [
                'message' => $message,
                'sha' => $existing['sha'],
            ]);
        }
    }

    /** The newest run of a workflow, or null before it has run. */
    public function latestRun(string $repo, string $workflow): ?array
    {
        $response = $this->request()->get(self::API."/repos/{$repo}/actions/workflows/{$workflow}/runs", ['per_page' => 1]);

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json('workflow_runs.0');
    }

    public function dispatchWorkflow(string $repo, string $workflow, string $ref): void
    {
        $this->send('post', "/repos/{$repo}/actions/workflows/{$workflow}/dispatches", ['ref' => $ref]);
    }

    /** The repository's GitHub Pages site, or null when Pages is off. */
    public function pages(string $repo): ?array
    {
        $response = $this->request()->get(self::API."/repos/{$repo}/pages");

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json();
    }

    /** Turn Pages on, built by a GitHub Actions workflow, or switch an existing site to one. */
    public function enablePages(string $repo): array
    {
        if ($this->pages($repo)) {
            $this->send('put', "/repos/{$repo}/pages", ['build_type' => 'workflow']);
        } else {
            $this->send('post', "/repos/{$repo}/pages", ['build_type' => 'workflow']);
        }

        return $this->pages($repo) ?? [];
    }

    public function disablePages(string $repo): void
    {
        $response = $this->request()->delete(self::API."/repos/{$repo}/pages");

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->request()->get(self::API.$path, $query)->throw();
    }

    private function send(string $method, string $path, array $body): Response
    {
        return $this->request()->{$method}(self::API.$path, $body)->throw();
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->withUserAgent('Framework-Cloud')
            ->timeout(20);

        return $this->account ? $request->withToken(Tokens::fresh($this->account)) : $request;
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    }
}
