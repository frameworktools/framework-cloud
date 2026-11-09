<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Report;
use App\Models\ShortLink;
use App\Models\User;
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
}
