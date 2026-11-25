<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Views count readers, not the owner looking at their own artifact. */
class ArtifactViewCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owners_visits_and_previews_do_not_count(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $document = app(ArtifactPublisher::class)->publish(Project::factory()->for($owner)->create(), [
            'file' => UploadedFile::fake()->createWithContent('visits.csv', "site,visits\nNorth,12\n"), 'visibility' => 'unlisted',
        ])->document;

        $this->actingAs($owner)->get($document->publicUrl())->assertOk();
        $this->actingAs($owner)->get($document->publicUrl().'?embed=1')->assertOk();
        $this->assertSame(0, (int) $document->fresh()->views);

        $this->actingAs(User::factory()->create())->get($document->publicUrl())->assertOk();
        auth()->logout();
        $this->get($document->publicUrl().'?embed=1')->assertOk();
        $this->assertSame(2, (int) $document->fresh()->views);
    }
}
