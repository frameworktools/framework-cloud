<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Facet;
use App\Models\Skill;
use App\Services\GitHub\GitHub;
use App\Services\GitHub\GitHubNotConnected;
use App\Services\GitHub\LibraryRepo;
use App\Support\Layer;
use App\Support\Library;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Import skills and facets from GitHub, and publish them to a repository.
 * Imports land as private items that record where they came from.
 */
class GitHubLibraryController extends Controller
{
    public function importSkill(Request $request): RedirectResponse
    {
        $user = $request->user();
        $url = $request->validate(['url' => ['required', 'string', 'max:500']])['url'];

        return $this->onGitHub(function () use ($request, $user, $url) {
            $read = $this->repo($request)->readSkill($url);
            ['key' => $key, 'description' => $description, 'title' => $title] = Skill::frontmatter($read['content'], 'url');

            if (Skill::where('user_id', $user->id)->where('key', $key)->exists()) {
                throw ValidationException::withMessages(['url' => "You already have a skill named {$key}."]);
            }

            $layerCheck = Layer::problems(['skills' => [$key => ['content' => $read['content'], 'files' => $read['files']]]], 'facet');
            if ($layerCheck) {
                throw ValidationException::withMessages(['url' => implode("\n", $layerCheck)]);
            }

            $skill = Skill::create([
                'user_id' => $user->id,
                'key' => $key,
                'name' => $title,
                'description' => $description,
                'visibility' => 'private',
                'source_url' => $url,
            ]);
            $skill->publishVersion([
                'content' => $read['content'],
                'files' => $read['files'],
                'notes' => 'Imported from GitHub'.($read['skipped'] ? '; skipped '.implode(', ', $read['skipped']) : ''),
            ]);

            return to_route('skills.edit', $skill)->with('status', 'skill-imported');
        });
    }

    public function importFacet(Request $request): RedirectResponse
    {
        $user = $request->user();
        $url = $request->validate(['url' => ['required', 'string', 'max:500']])['url'];

        return $this->onGitHub(function () use ($request, $user, $url) {
            $read = $this->repo($request)->readFacet($url);

            if ($problems = Layer::problems($read['layer'], $read['kind'])) {
                throw ValidationException::withMessages(['url' => implode("\n", $problems)]);
            }

            $facet = Facet::create([
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Facet::query(), $read['name'], $user->id),
                'kind' => $read['kind'],
                'name' => mb_substr($read['name'], 0, 100),
                'description' => $read['description'],
                'category' => $read['category'],
                'question' => $read['question'],
                'visibility' => 'private',
                'source_url' => $url,
            ]);
            $facet->publishVersion(['layer' => $read['layer'], 'notes' => 'Imported from GitHub']);

            return to_route('facets.edit', $facet)->with('status', 'facet-imported');
        });
    }

    public function publishSkill(Request $request, Skill $skill): RedirectResponse
    {
        abort_unless($skill->isVisibleTo($request->user()), 404);
        $repo = $this->targetRepo($request);

        return $this->onGitHub(fn () => back()->with([
            'status' => 'published',
            'url' => $this->repo($request, required: true)->publishSkill($repo, $skill->load('latestVersion')),
        ]));
    }

    public function publishFacet(Request $request, Facet $facet): RedirectResponse
    {
        abort_unless($facet->isVisibleTo($request->user()), 404);
        $repo = $this->targetRepo($request);

        return $this->onGitHub(fn () => back()->with([
            'status' => 'published',
            'url' => $this->repo($request, required: true)->publishFacet($repo, $facet->load('latestVersion')),
        ]));
    }

    private function targetRepo(Request $request): string
    {
        return $request->validate(
            ['repo' => ['required', 'string', 'max:140', 'regex:#^[A-Za-z0-9-]+/[A-Za-z0-9._-]+$#']],
            ['repo.regex' => 'Write the repository as owner/name.'],
        )['repo'];
    }

    private function repo(Request $request, bool $required = false): LibraryRepo
    {
        $account = $request->user()->githubAccount;

        if ($required && ! $account) {
            throw new GitHubNotConnected('Connect GitHub in settings first.');
        }

        return new LibraryRepo(GitHub::for($account));
    }

    private function onGitHub(Closure $call): RedirectResponse
    {
        try {
            return $call();
        } catch (GitHubNotConnected $e) {
            return back()->withErrors(['github' => $e->getMessage()]);
        } catch (ConnectionException) {
            return back()->withErrors(['github' => 'GitHub could not be reached. Try again.']);
        } catch (RequestException $e) {
            return back()->withErrors(['github' => match ($e->response->status()) {
                403, 404 => 'The Framework app cannot reach that repository. Check the name, or install the app on it.',
                default => 'GitHub returned an error: '.($e->response->json('message') ?? $e->response->status()),
            }]);
        }
    }
}
