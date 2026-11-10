<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Report;
use App\Models\ShortLink;
use App\Models\User;
use App\Support\Share;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** framework.pub/go: short links and QR codes for slides and projectors. */
class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    private function report(): Report
    {
        return Report::create([
            'project_id' => Project::factory()->for(User::factory())->create()->id, 'key' => Str::random(24), 'slug' => 'findings',
            'title' => 'Findings', 'visibility' => 'public', 'draft_blocks' => [],
        ]);
    }

    public function test_codes_avoid_look_alikes_and_stay_the_same(): void
    {
        $report = $this->report();
        $link = ShortLink::for($report);

        $this->assertMatchesRegularExpression('/^['.ShortLink::ALPHABET.']{4}$/', $link->code);
        $this->assertSame($link->code, ShortLink::for($report)->code);
    }

    public function test_a_code_opens_its_page_however_it_is_typed(): void
    {
        $report = $this->report();
        $link = ShortLink::for($report);
        $typed = strtoupper(substr($link->code, 0, 2).'-'.substr($link->code, 2));

        $this->get("/go/{$link->code}")->assertRedirect($report->shareUrl());
        $this->get('/go/'.rawurlencode($typed))->assertRedirect($report->shareUrl());
        $this->get('/go?code='.rawurlencode(' '.$typed.' '))->assertRedirect($report->shareUrl());
        $this->get("/go/{$link->code}/qr.svg")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_unknown_and_deleted_pages_do_not_open(): void
    {
        $this->get('/go')->assertOk()->assertSee('Type the code shown on screen.');
        $this->get('/go?code=zzzz')->assertOk()->assertSee('No page has the code');
        $this->get('/go/zzzz')->assertNotFound();

        $report = $this->report();
        $link = ShortLink::for($report);
        $report->delete();
        $this->get("/go/{$link->code}")->assertNotFound();
    }

    public function test_qr_codes_download_as_png_or_svg_for_slides(): void
    {
        $report = $this->report();
        $share = Share::for($report);

        $png = $this->get(parse_url($share['qr_png_download'], PHP_URL_PATH).'?download=1')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());
        $this->assertStringContainsString('findings-qr.png', $png->headers->get('Content-Disposition'));
        [$width] = getimagesizefromstring($png->getContent());
        $this->assertSame(1024, $width);

        $this->get(parse_url($share['qr_svg'], PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_private_things_have_no_qr_code_or_short_link(): void
    {
        $report = $this->report();
        $link = ShortLink::for($report);
        $report->update(['visibility' => 'private']);

        $this->assertNull(Share::for($report));
        $this->get("/go/{$link->code}")->assertNotFound();
        $this->get("/go/{$link->code}/qr.png")->assertNotFound();
    }

    public function test_the_share_dialog_makes_a_private_thing_shareable_first(): void
    {
        $report = $this->report();
        $report->update(['visibility' => 'private']);
        $owner = $report->project->user;

        $this->actingAs($owner)->getJson("/share/report/{$report->id}")->assertOk()->assertJsonPath('share', null);
        $this->actingAs($owner)->postJson("/share/report/{$report->id}", ['visibility' => 'private'])->assertStatus(422);

        $this->actingAs($owner)->postJson("/share/report/{$report->id}", ['visibility' => 'unlisted'])->assertOk()
            ->assertJsonPath('visibility', 'unlisted')->assertJsonPath('share.short', fn ($s) => str_contains($s, '/go/'));
        $this->assertSame('unlisted', $report->fresh()->visibility);

        // Someone else's
        $this->actingAs(User::factory()->create())->postJson("/share/report/{$report->id}", ['visibility' => 'public'])->assertNotFound();
    }
}
