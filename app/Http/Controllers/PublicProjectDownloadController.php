<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Scaffolder\ProjectZip;
use App\Services\Scaffolder\ProjectZipFailed;
use App\Support\ContentWorker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /** The zip in the bucket, rebuilt when the kept one is over five minutes old. */
    private function fromBucket(Project $project, string $key, ProjectZip $zips): RedirectResponse
    {
        $object = Project::publicDownloadObject($key);
        $built = "public-download-built:{$key}";
        if (! Cache::has($built)) {
            Cache::lock("public-download:{$key}", 180)->block(170, function () use ($project, $zips, $object, $built) {
                if (Cache::has($built)) {
                    return;
                }
                try {
                    $path = $zips->buildPublic($project);
                } catch (ProjectZipFailed $e) {
                    abort(503, $e->getMessage());
                }
                $stream = fopen($path, 'r');
                try {
                    abort_unless(Storage::disk(ContentWorker::disk())->writeStream($object, $stream), 503, 'The download could not be stored. Try again.');
                } finally {
                    fclose($stream);
                    @unlink($path);
                }
                Cache::put($built, true, now()->addMinutes(5));
            });
        }

        return redirect()->away(ContentWorker::download($object, "{$project->slug}.zip", 'application/zip'))->header('Cache-Control', 'no-store');
    }
}
