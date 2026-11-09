<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Scaffolder\ProjectZip;
use App\Services\Scaffolder\ProjectZipFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /downloads/{key}: a project as a zip for anyone with the link, such
 * as students in a course. The owner turns the link on and off; sensitive
 * projects never get one. A build is kept for a few minutes, so a class
 * downloading at once builds it once.
 */
class PublicProjectDownloadController extends Controller
{
    public function __invoke(string $key, ProjectZip $zips): BinaryFileResponse
    {
        $project = Project::where('public_download_key', $key)->firstOrFail();

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
}
