<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $user->forceFill(['handle' => 'ada', 'profile_public' => true])->save();

        return $user;
    }

    private function project(User $user): Project
    {
        return $user->projects()->create(['name' => 'Engine', 'slug' => 'engine', 'project_type' => 'project']);
    }

    private function artifact(Project $project, string $title, string $visibility, ?string $password = null): Document
    {
        return Document::create([
            'project_id' => $project->id, 'title' => $title, 'slug' => Str::slug($title), 'key' => Document::newKey(),
            'content_type' => 'text/csv', 'disk_path' => 'artifacts/p/content', 'size_bytes' => 4, 'kind' => 'table',
            'visibility' => $visibility, 'password_hash' => $password ? bcrypt($password) : null,
            'description' => $title.' description',
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function graph(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m, 'The page has no JSON-LD.');

        $json = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['@context', '@graph'], array_keys($json));
        $this->assertSame('https://schema.org', $json['@context']);

        return $json['@graph'];
    }

    public function test_robots_names_the_sitemap_of_its_own_host(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_the_sitemap_lists_public_pages_and_only_public_work_with_no_password(): void
    {
        $project = $this->project($this->owner());
        $this->artifact($project, 'Shown table', 'public');
        $this->artifact($project, 'Unlisted table', 'unlisted');
        $this->artifact($project, 'Locked table', 'public', 'a-password-1');
        $public = Collection::create(['project_id' => $project->id, 'title' => 'Open', 'slug' => 'open', 'key' => Str::random(24), 'visibility' => 'public']);
        $unlisted = Collection::create(['project_id' => $project->id, 'title' => 'Quiet', 'slug' => 'quiet', 'key' => Str::random(24), 'visibility' => 'unlisted']);
        $report = Report::create(['project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'findings', 'title' => 'Findings', 'visibility' => 'public', 'draft_blocks' => []]);
        $report->versions()->create(['number' => 1, 'title' => 'Findings', 'blocks' => []]);
        $draft = Report::create(['project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'draft', 'title' => 'Draft', 'visibility' => 'public', 'draft_blocks' => []]);
        $hidden = User::factory()->create();
        $hidden->forceFill(['handle' => 'hidden', 'profile_public' => false])->save();

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<loc>'.route('home').'</loc>', false)
            ->assertSee('<loc>'.route('self-hosting').'</loc>', false)
            ->assertSee('<loc>'.url('/docs/getting-started').'</loc>', false)
            ->assertSee('<loc>'.route('profile', 'ada').'</loc>', false)
            ->assertDontSee(route('profile', 'hidden'), false)
            ->assertSee('<loc>'.url('/@ada/engine/shown-table').'</loc>', false)
            ->assertDontSee('unlisted-table')
            ->assertDontSee('locked-table')
            ->assertSee($public->shareUrl(), false)
            ->assertDontSee($unlisted->shareUrl(), false)
            ->assertSee($report->shareUrl(), false)
            ->assertDontSee($draft->shareUrl(), false);
    }

    public function test_the_home_page_describes_the_site_and_the_software(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">', false)
            ->assertSee('<meta name="description"', false);

        $types = array_column($this->graph($response->getContent()), '@type');
        $this->assertSame(['Organization', 'WebSite', 'SoftwareApplication'], $types);
    }

    public function test_a_public_artifact_is_indexed_at_its_readable_link(): void
    {
        $document = $this->artifact($this->project($this->owner()), 'Shown table', 'public');

        $response = $this->get($document->publicUrl())->assertOk()
            ->assertDontSee('content="noindex"', false)
            ->assertSee('<link rel="canonical" href="'.url('/@ada/engine/shown-table').'">', false)
            ->assertSee('<meta property="og:title" content="Shown table · Framework">', false)
            ->assertSee('<meta name="description" content="Shown table description">', false);

        $dataset = collect($this->graph($response->getContent()))->firstWhere('@type', 'Dataset');
        $this->assertSame('Shown table', $dataset['name']);
        $this->assertSame('Ada Lovelace', $dataset['author']['name']);
        $this->assertSame(route('documents.download', $document->key), $dataset['distribution'][0]['contentUrl']);
    }

    public function test_unlisted_and_embedded_artifacts_keep_link_previews_but_are_not_indexed(): void
    {
        $project = $this->project($this->owner());
        $unlisted = $this->artifact($project, 'Unlisted table', 'unlisted');
        $public = $this->artifact($project, 'Shown table', 'public');

        $this->get($unlisted->publicUrl())->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertSee('<meta property="og:title" content="Unlisted table · Framework">', false);
        $this->get($public->publicUrl().'?embed=1')->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_the_profile_is_a_profile_page_for_its_person(): void
    {
        $this->artifact($this->project($this->owner()), 'Shown table', 'public');

        $response = $this->get('/@ada')->assertOk()
            ->assertSee('<meta property="og:type" content="profile">', false);

        $page = collect($this->graph($response->getContent()))->firstWhere('@type', 'ProfilePage');
        $this->assertSame('Ada Lovelace', $page['mainEntity']['name']);
        $this->assertSame('@ada', $page['mainEntity']['alternateName']);
        $this->assertSame(url('/@ada/engine/shown-table'), $page['hasPart'][0]['url']);
    }

    public function test_collections_and_reports_have_previews_and_structured_data(): void
    {
        $project = $this->project($this->owner());
        $collection = Collection::create(['project_id' => $project->id, 'title' => 'Open', 'slug' => 'open', 'key' => Str::random(24), 'visibility' => 'public', 'description' => 'Pilot materials']);
        $report = Report::create(['project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'findings', 'title' => 'Findings', 'visibility' => 'public', 'draft_blocks' => []]);
        $report->versions()->create(['number' => 1, 'title' => 'Findings', 'description' => 'What the pilot found', 'blocks' => []]);

        $page = $this->get($collection->shareUrl())->assertOk()
            ->assertSee('<meta property="og:description" content="Pilot materials">', false)
            ->assertDontSee('content="noindex"', false);
        $this->assertSame('CollectionPage', collect($this->graph($page->getContent()))->pluck('@type')->last());

        $page = $this->get($report->shareUrl())->assertOk()
            ->assertSee('<meta property="og:description" content="What the pilot found">', false)
            ->assertSee('<link rel="canonical" href="'.$report->shareUrl().'">', false);
        $this->assertSame('Findings', collect($this->graph($page->getContent()))->firstWhere('@type', 'Report')['headline']);
    }

    public function test_structured_data_cannot_close_its_script_tag(): void
    {
        $project = $this->project($this->owner());
        $document = $this->artifact($project, 'Table', 'public');
        $document->update(['title' => '</script><script>alert(1)</script>']);

        $html = $this->get($document->publicUrl())->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertSame('</script><script>alert(1)</script>', collect($this->graph($html))->firstWhere('@type', 'Dataset')['name']);
    }
}
