<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoveArtifactsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_artifacts_move_to_another_disk_and_still_open(): void
    {
        Storage::fake('local');
        Storage::fake('r2');
        config(['framework.artifacts_disk' => 'local']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $project = Project::factory()->for($user)->create();
        $version = app(ArtifactPublisher::class)->publish($project, ['slug' => 'mass', 'visibility' => 'unlisted',
            'file' => UploadedFile::fake()->createWithContent('mass.csv', "species,mass\nAdelie,3700\n")]);
        $bundle = tempnam(sys_get_temp_dir(), 'site');
        $zip = new \ZipArchive;
        $zip->open($bundle, \ZipArchive::OVERWRITE);
        $zip->addFromString('index.html', '<html>Site</html>');
        $zip->close();
        $site = app(ArtifactPublisher::class)->publish($project, ['slug' => 'site', 'visibility' => 'unlisted', 'entrypoint' => 'index.html',
            'bundle' => new UploadedFile($bundle, 'site.zip', null, null, true)]);

        $this->artisan('fw:move-artifacts', ['--to' => 'r2', '--delete-source' => true])->assertSuccessful();

        $this->assertSame('r2', $version->fresh()->storage_disk);
        $this->assertSame('r2', $version->document->fresh()->storage_disk);
        Storage::disk('r2')->assertExists($version->disk_path);
        Storage::disk('local')->assertMissing($version->disk_path);
        Storage::disk('r2')->assertExists($site->disk_path.'/index.html');
        $this->get(route('documents.raw', ['key' => $version->document->key]))->assertOk()->assertStreamedContent("species,mass\nAdelie,3700\n");

        // Nothing left to move
        $this->artisan('fw:move-artifacts', ['--to' => 'r2'])->expectsOutputToContain('0 version(s) to move')->assertSuccessful();
    }
}
