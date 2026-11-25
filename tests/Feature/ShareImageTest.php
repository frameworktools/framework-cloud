<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use App\Support\ShareImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Link previews show a picture of the artifact itself, for anyone who may open it. */
class ShareImageTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Ada Lovelace']);
        $user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();
        $this->project = Project::factory()->for($user)->create(['slug' => 'smoking', 'name' => 'Smoking study']);
    }

    private function publish(string $name, string $content, string $visibility = 'unlisted'): Document
    {
        return app(ArtifactPublisher::class)->publish($this->project, [
            'slug' => pathinfo($name, PATHINFO_FILENAME), 'title' => 'Smoking and cancer',
            'file' => UploadedFile::fake()->createWithContent($name, $content), 'visibility' => $visibility,
        ])->document->fresh();
    }

    public function test_a_shared_dag_has_its_own_png_and_says_what_it_shows(): void
    {
        $document = $this->publish('smoking.dagitty', "dag {\nsmoking [exposure]\ncancer [outcome]\nage -> smoking -> cancer\nage -> cancer\n}");
        $url = ShareImage::url($document->latestVersion());

        $this->get($document->publicUrl())->assertOk()
            ->assertSee('<meta property="og:image" content="'.e($url).'">', false)
            ->assertSee('<meta property="og:image:alt" content="Smoking and cancer, DAG on', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('Causal diagram of 3 variables and 3 arrows. Exposure: smoking. Outcome: cancer. DAG from Smoking study by Ada Lovelace', false);

        $png = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
        $this->assertStringStartsWith("\x89PNG", $png);
        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame([1200, 630], [$width, $height]);
        // Built once, then served from the disk
        Storage::disk('local')->assertExists('share-images/'.$document->latestVersion()->id.'-'.ShareImage::VERSION.'.png');
    }

    public function test_every_kind_gets_an_image(): void
    {
        foreach ([
            ['visits.csv', "site,visits\nNorth,12\nSouth,9\n"],
            ['report.html', '<html><body>Hi</body></html>'],
            ['structure.json', json_encode(['schema' => 'framework.artifact/v1', 'kind' => 'tree', 'root' => 'p', 'nodes' => [['path' => 'R/clean.R', 'type' => 'file', 'note' => 'Start here']]])],
            ['figure.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')],
        ] as [$name, $content]) {
            $document = $this->publish($name, $content);
            $png = $this->get(ShareImage::url($document->latestVersion()))->assertOk()->getContent();
            $this->assertSame([1200, 630], array_slice(getimagesizefromstring($png), 0, 2), $name);
        }
    }

    public function test_private_and_password_protected_artifacts_have_none(): void
    {
        $private = $this->publish('private.dagitty', 'dag { a -> b }', 'private');
        $this->get(ShareImage::url($private->latestVersion()))->assertNotFound();

        $locked = $this->publish('locked.dagitty', 'dag { a -> b }');
        $locked->forceFill(['password_hash' => bcrypt('long enough password')])->save();
        $this->get(ShareImage::url($locked->latestVersion()))->assertNotFound();
        $this->assertFalse(ShareImage::available($locked));
    }
}
