<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use App\Services\Scaffolder\ProjectZip;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class ProjectDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.scaffolder.url' => 'http://scaffolder:8000',
            'services.scaffolder.token' => 'scaffold-secret',
            'framework.client_url' => 'https://cloud.example.org',
        ]);

        $this->seed(BlueprintSeeder::class);
    }

    /** A zip like the scaffolder's: the project under its folder. */
    private static function scaffoldedZip(string $folder, array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fake-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addEmptyDir("{$folder}/inputs/raw");
        foreach ($files as $name => $content) {
            $zip->addFromString("{$folder}/{$name}", $content);
        }
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private function project(User $user, string $language = 'r'): Project
    {
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();

        $this->actingAs($user)->post('/projects', [
            'name' => 'NHANES Study',
            'blueprint_id' => $blueprint->id,
            'language' => $language,
            'destination' => 'download',
        ])->assertRedirect('/projects/nhanes-study')->assertSessionHas('status', 'project-download');

        return Project::firstOrFail();
    }

    private function unzip(string $path): array
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $files[$name] = $zip->getFromIndex($i);
        }
        $zip->close();

        return $files;
    }

    public function test_choosing_download_creates_the_project_and_sends_you_to_its_page(): void
    {
        Http::fake();
        $project = $this->project(User::factory()->create());

        $this->assertSame('nhanes-study', $project->slug);
        $this->assertSame(0, $project->tokens()->count());
        Http::assertNothingSent();
    }

    public function test_the_download_is_built_by_the_scaffolder_from_the_project_layers(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        Http::fake(['scaffolder:8000/build' => Http::response(
            self::scaffoldedZip('nhanes-study', ['.env' => "POSTGRES_HOST=\n", '.gitignore' => ".env\n", 'settings.yml' => "default:\n"]),
            200, ['Content-Type' => 'application/zip'],
        )]);

        $response = $this->actingAs($user)->get("/projects/{$project->id}/download");

        $response->assertOk()->assertDownload('nhanes-study.zip');
        Http::assertSent(function (HttpRequest $request) use ($project) {
            $body = json_decode($request->body());

            return $request->url() === 'http://scaffolder:8000/build'
                && $request->hasHeader('Authorization', 'Bearer scaffold-secret')
                && $body->folder === 'nhanes-study'
                && $body->layers->name === 'NHANES Study'
                && json_encode($body->layers) === json_encode(json_decode(ProjectLayers::toJson(ProjectLayers::for($project))));
        });

        $files = $this->unzip($response->baseResponse->getFile()->getPathname());
        $this->assertArrayHasKey('nhanes-study/inputs/raw/', $files);
        $this->assertSame("default:\n", $files['nhanes-study/settings.yml']);

        // The folder can connect back to this server, but carries no token
        $env = $files['nhanes-study/.env'];
        $this->assertStringStartsWith("POSTGRES_HOST=\n", $env);
        $this->assertStringContainsString("FW_CLOUD_URL=https://cloud.example.org\n", $env);
        $this->assertStringContainsString('https://cloud.example.org/projects/nhanes-study/computers', $env);
        $this->assertStringContainsString("# FW_PROJECT_TOKEN=\n", $env);
        $this->assertDoesNotMatchRegularExpression('/^FW_PROJECT_TOKEN=/m', $env);
        $this->assertSame(".env\n", $files['nhanes-study/.gitignore']);
    }

    public function test_a_project_without_an_ignored_env_gets_one_added_to_gitignore(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        Http::fake(['scaffolder:8000/build' => Http::response(
            self::scaffoldedZip('nhanes-study', ['.gitignore' => "outputs/\n.env\n!.env\n"]),
            200, ['Content-Type' => 'application/zip'],
        )]);

        $response = $this->actingAs($user)->get("/projects/{$project->id}/download")->assertOk();

        $files = $this->unzip($response->baseResponse->getFile()->getPathname());
        $this->assertStringEndsWith("\n.env\n", $files['nhanes-study/.gitignore']);
        $this->assertStringStartsWith("outputs/\n.env\n!.env\n", $files['nhanes-study/.gitignore']);
        $this->assertStringContainsString('FW_CLOUD_URL=', $files['nhanes-study/.env']);
    }

    public function test_a_failed_build_goes_back_to_the_project_with_the_reason(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        Http::fake(['scaffolder:8000/build' => Http::response(['error' => 'The project could not be built.', 'log' => 'R said no'], 422)]);

        $this->actingAs($user)->get("/projects/{$project->id}/download")
            ->assertRedirect('/projects/nhanes-study')
            ->assertSessionHasErrors(['download' => 'The project could not be built. The details are in the server log.']);
    }

    public function test_an_unreachable_scaffolder_asks_you_to_try_again(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->actingAs($user)->get("/projects/{$project->id}/download")
            ->assertSessionHasErrors(['download' => 'The project could not be built right now. Try again in a minute.']);
    }

    public function test_only_the_owner_can_download(): void
    {
        $project = $this->project(User::factory()->create());
        Http::fake();

        $this->actingAs(User::factory()->create())->get("/projects/{$project->id}/download")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_download_is_refused_before_creating_anything_when_the_server_has_no_scaffolder(): void
    {
        config(['services.scaffolder.url' => null]);
        $blueprint = Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail();

        $this->actingAs(User::factory()->create())->post('/projects', [
            'name' => 'NHANES Study',
            'blueprint_id' => $blueprint->id,
            'destination' => 'download',
        ])->assertSessionHasErrors('destination');

        $this->assertSame(0, Project::count());
    }

    public function test_the_pages_say_whether_downloads_are_on(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);

        config(['services.github.fw_version' => null]);
        $this->actingAs($user)->get('/projects/create')->assertInertia(fn ($page) => $page->where('downloads', true)
            ->where('githubUnavailable.stata', fn ($reason) => filled($reason))->missing('githubUnavailable.r'));
        $this->actingAs($user)->get('/projects/nhanes-study')->assertInertia(fn ($page) => $page->where('downloads', true));

        config(['services.scaffolder.url' => null]);
        $this->actingAs($user)->get('/projects/create')->assertInertia(fn ($page) => $page->where('downloads', false));
        $this->assertFalse(ProjectZip::enabled());
    }

    public function test_gitignore_check_follows_the_last_line_about_env(): void
    {
        $this->assertTrue(ProjectZip::ignoresEnv(".env\n"));
        $this->assertTrue(ProjectZip::ignoresEnv("/.env\n"));
        $this->assertTrue(ProjectZip::ignoresEnv(".env*\n"));
        $this->assertFalse(ProjectZip::ignoresEnv(".env\n!.env\n"));
        $this->assertFalse(ProjectZip::ignoresEnv(".env.local\n"));
        $this->assertFalse(ProjectZip::ignoresEnv(''));
    }
}
