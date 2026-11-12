<?php

namespace App\Services\Scaffolder;

use App\Models\Project;
use App\Support\Layer;
use App\Support\ProjectLayers;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * A project as a zip to download. The cloud still writes no project files:
 * the scaffolder container (docker/scaffolder) runs fw and the language
 * packages on the project's layers, exactly as GitHub or a person's own
 * computer would. The cloud only adds the lines that let the folder connect
 * back to this server later.
 */
class ProjectZip
{
    public static function enabled(): bool
    {
        return filled(config('services.scaffolder.url'));
    }

    /**
     * Build the zip into a temporary file. The caller sends it and deletes it.
     *
     * @throws ProjectZipFailed
     */
    public function build(Project $project): string
    {
        if (! self::enabled()) {
            throw new ProjectZipFailed('This server is not set up to build downloads.');
        }

        $envelope = ProjectLayers::for($project);

        if ($problems = Layer::envelopeProblems($envelope)) {
            throw new ProjectZipFailed('The project layers are not valid: '.implode(' ', $problems));
        }

        $request = Http::timeout((int) config('services.scaffolder.timeout', 200))->acceptJson();
        if (filled($token = config('services.scaffolder.token'))) {
            $request = $request->withToken($token);
        }

        try {
            // Layer::toJsonValue keeps empty maps as {}, which the layers schema requires
            $body = json_encode(['layers' => Layer::toJsonValue($envelope), 'folder' => $project->slug], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $response = $request->withBody($body, 'application/json')
                ->post(rtrim((string) config('services.scaffolder.url'), '/').'/build');
        } catch (ConnectionException $e) {
            Log::warning('Scaffolder unreachable', ['project' => $project->id, 'error' => $e->getMessage()]);

            throw new ProjectZipFailed('The project could not be built right now. Try again in a minute.');
        }

        if (! $response->successful() || $response->header('Content-Type') !== 'application/zip') {
            Log::warning('Scaffolder build failed', [
                'project' => $project->id,
                'status' => $response->status(),
                'error' => $response->json('error'),
                'log' => $response->json('log'),
            ]);

            throw new ProjectZipFailed($response->status() === 422
                ? ($response->json('error') ?: 'The project could not be built.').' The details are in the server log.'
                : 'The project could not be built right now. Try again in a minute.');
        }

        $path = tempnam(sys_get_temp_dir(), 'project-zip-');
        file_put_contents($path, $response->body());
        $this->addConnection($path, $project);

        return $path;
    }

    /**
     * The zip a public download link serves: the repository as it is now
     * when the project has a public one (so content added after the
     * scaffold comes along), else the scaffolder's build. The folder inside
     * is named for the project.
     *
     * @throws ProjectZipFailed
     */
    /** $ref: the commit to package, the default branch's head when null. */
    public function buildPublic(Project $project, ?string $ref = null): string
    {
        if (! $project->github_repo) {
            return $this->build($project);
        }

        $response = Http::timeout(120)->withUserAgent('Framework-Cloud')
            ->withOptions(['allow_redirects' => true])
            ->get("https://github.com/{$project->github_repo}/archive/".($ref ?? 'HEAD').'.zip');

        if (! $response->successful()) {
            Log::warning('Public download zipball failed', ['project' => $project->id, 'status' => $response->status()]);

            throw new ProjectZipFailed($response->status() === 404
                ? 'The project\'s repository is private or gone, so it cannot be downloaded here.'
                : 'The project could not be downloaded right now. Try again in a minute.');
        }

        $in = tempnam(sys_get_temp_dir(), 'project-zipball-');
        file_put_contents($in, $response->body());
        $out = tempnam(sys_get_temp_dir(), 'project-zip-');

        try {
            $source = new ZipArchive;
            $target = new ZipArchive;
            if ($source->open($in) !== true || $target->open($out, ZipArchive::OVERWRITE) !== true) {
                throw new ProjectZipFailed('The project could not be downloaded: GitHub sent an unreadable zip.');
            }

            // GitHub names the folder owner-repo-sha; students get the project's name
            for ($i = 0; $i < $source->numFiles; $i++) {
                $name = $source->getNameIndex($i);
                $relative = str_contains($name, '/') ? substr($name, strpos($name, '/') + 1) : '';
                // Never a private/ folder, whatever the repository holds
                if ($relative === '' || preg_match('#(^|/)private(/|$)#', $relative)) {
                    continue;
                }
                $path = "{$project->slug}/{$relative}";
                str_ends_with($relative, '/') ? $target->addEmptyDir(rtrim($path, '/')) : $target->addFromString($path, (string) $source->getFromIndex($i));
            }
            $source->close();
            $target->close();
        } finally {
            @unlink($in);
        }

        return $out;
    }

    /**
     * The server address, and where to get a token, in the project's .env.
     * No token: a zip gets passed around, and a token in it would go along.
     * R, Python, and fw all read FW_PROJECT_TOKEN and FW_CLOUD_URL from .env.
     */
    private function addConnection(string $path, Project $project): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ProjectZipFailed('The project could not be built: the scaffolder sent an unreadable zip.');
        }

        $folder = $project->slug;
        $url = config('framework.client_url');
        $page = "{$url}/projects/{$project->slug}/computers";

        $env = $zip->getFromName("{$folder}/.env");
        // The variables to fill in on each computer, without values, for git and for bootstrap()
        if ($env !== false && $zip->locateName("{$folder}/.env.example") === false) {
            $zip->addFromString("{$folder}/.env.example", self::example($env));
        }
        $env = $env === false ? '' : rtrim($env, "\n")."\n\n";
        $env .= self::connectionLines($url, $page);
        $zip->addFromString("{$folder}/.env", $env);

        // .env holds the token once it's added, so git must never take it
        $gitignore = $zip->getFromName("{$folder}/.gitignore");
        if ($gitignore === false || ! self::ignoresEnv($gitignore)) {
            $gitignore = $gitignore === false || $gitignore === '' ? '' : rtrim($gitignore, "\n")."\n\n";
            $zip->addFromString("{$folder}/.gitignore", $gitignore."# Secrets, including the Framework Cloud project token\n.env\n");
        }

        $zip->close();
    }

    /** A .env as a template: each variable's name, with no value. */
    public static function example(string $env): string
    {
        preg_match_all('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/m', $env, $matches);

        return "# Copy to .env and fill in the values on each computer. Never commit .env.\n"
            .implode('', array_map(fn (string $name) => "{$name}=\n", array_values(array_unique($matches[1]))));
    }

    public static function connectionLines(string $url, string $page): string
    {
        return <<<ENV
        # Framework Cloud. To connect this project, get a project token at
        # {$page}
        # then remove the # in front of FW_PROJECT_TOKEN below and paste it after the =.
        FW_CLOUD_URL={$url}
        # FW_PROJECT_TOKEN=

        ENV;
    }

    /** Whether the last .gitignore line about .env ignores it (a later !.env would undo it). */
    public static function ignoresEnv(string $gitignore): bool
    {
        $last = null;
        foreach (preg_split('/\R/', $gitignore) as $line) {
            $line = trim($line);
            if (preg_match('#^!?/?\.env\*?$#', $line)) {
                $last = $line;
            }
        }

        return $last !== null && ! str_starts_with($last, '!');
    }
}
