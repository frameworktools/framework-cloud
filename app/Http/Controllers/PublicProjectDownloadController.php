<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\GitHub\GitHub;
use App\Services\Scaffolder\ProjectZip;
use App\Services\Scaffolder\ProjectZipFailed;
use App\Support\ContentWorker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * GET /downloads/{key}: a project as a zip for anyone with the link, such
 * as students in a course. The owner turns the link on and off; sensitive
 * projects never get one. A build is kept for a few minutes, so a class
 * downloading at once builds it once.
 */
class PublicProjectDownloadController extends Controller
{
    public function __invoke(string $key, ProjectZip $zips): BinaryFileResponse|RedirectResponse
    {
        $project = Project::where('public_download_key', $key)->firstOrFail();
        Project::whereKey($project->id)->toBase()->increment('public_downloads');

        // With the content Worker, the zip is kept in the bucket and the Worker sends it
        if (ContentWorker::on()) {
            return $this->fromBucket($project, $key, $zips);
        }

        $dir = storage_path('app/public-downloads');
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$key}.zip";

        $fresh = is_file($path) && filemtime($path) > now()->subMinutes(5)->getTimestamp();
        if (! $fresh) {
            Cache::lock("public-download:{$key}", 180)->block(170, function () use ($path, $project, $zips) {
                if (is_file($path) && filemtime($path) > now()->subMinutes(5)->getTimestamp()) {
                    return;
                }
                try {
                    $built = $zips->buildPublic($project);
                } catch (ProjectZipFailed $e) {
                    abort(503, $e->getMessage());
                }
                rename($built, $path);
            });
        }

        return response()->download($path, "{$project->slug}.zip", ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store']);
    }

    /**
     * The zip kept in the bucket for the repository's current commit, built
     * once per commit. Without a repository (or when GitHub can't say), it is
     * rebuilt when over five minutes old.
     */
    private function fromBucket(Project $project, string $key, ProjectZip $zips): RedirectResponse
    {
        $sha = $project->github_repo ? Cache::remember("public-download-head:{$key}", 60, function () use ($project) {
            try {
                return GitHub::for($project->user->githubAccount)->headSha($project->github_repo);
            } catch (Throwable) {
                return null;
            }
        }) : null;
        $object = Project::publicDownloadFolder($key).'/'.($sha && preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : 'latest').'.zip';
        $built = "public-download-built:{$key}";

        if (Cache::get($built) !== $object) {
            Cache::lock("public-download:{$key}", 180)->block(170, function () use ($project, $zips, $object, $built, $sha) {
                if (Cache::get($built) === $object) {
                    return;
                }
                try {
                    $path = $zips->buildPublic($project, $sha);
                } catch (ProjectZipFailed $e) {
                    abort(503, $e->getMessage());
                }
                $disk = Storage::disk(ContentWorker::disk());
                $stream = fopen($path, 'r');
                try {
                    abort_unless($disk->writeStream($object, $stream), 503, 'The download could not be stored. Try again.');
                } finally {
                    fclose($stream);
                    @unlink($path);
                }
                $previous = Cache::get($built);
                if (is_string($previous) && $previous !== $object) {
                    $disk->delete($previous);
                }
                str_ends_with($object, '/latest.zip')
                    ? Cache::put($built, $object, now()->addMinutes(5))
                    : Cache::forever($built, $object);
            });
        }

        return redirect()->away(ContentWorker::download($object, "{$project->slug}.zip", 'application/zip'))->header('Cache-Control', 'no-store');
    }
}
