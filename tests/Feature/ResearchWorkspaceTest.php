<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function outputs(Project $project): array
    {
        $artifact = Document::create([
            'project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'effects', 'title' => 'Estimated effects',
            'content_type' => 'image/png', 'kind' => 'image', 'disk_path' => 'effects.png', 'size_bytes' => 32,
            'visibility' => 'unlisted', 'password_hash' => Hash::make('artifact-password'),
        ]);
        $collection = Collection::create([
            'project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'figures', 'title' => 'Manuscript figures', 'visibility' => 'private',
        ]);
        $collection->items()->create(['document_id' => $artifact->id]);
        $report = Report::create([
            'project_id' => $project->id, 'key' => Str::random(24), 'slug' => 'findings', 'title' => 'Pilot findings',
            'visibility' => 'private', 'draft_blocks' => [],
        ]);

        return compact('artifact', 'collection', 'report');
    }

    public function test_research_indexes_span_owned_projects_and_exclude_other_accounts_and_deleted_projects(): void
    {
        $user = User::factory()->create();
        $first = Project::factory()->for($user)->create();
        $second = Project::factory()->for($user)->create();
        $this->outputs($first);
        $this->outputs($second);
        $other = Project::factory()->create();
        $this->outputs($other);
        $deleted = Project::factory()->for($user)->create();
        $this->outputs($deleted);
        $deleted->delete();

        foreach (['collections' => 'Collections/Index', 'reports' => 'Reports/Index'] as $path => $component) {
            $this->actingAs($user)->get('/'.$path)->assertInertia(fn (Assert $page) => $page
                ->component($component)->where('project', null)->has('projects', 2)->has($path, 2)
                ->where($path.'.0.project.id', fn ($id) => in_array($id, [$first->id, $second->id])));
            $this->get('/projects/'.$first->id.'/'.$path)->assertInertia(fn (Assert $page) => $page
                ->where('project.id', $first->id)->has($path, 1));
            $this->get('/projects/'.$other->id.'/'.$path)->assertNotFound();
        }
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('counts.projects', 2)->where('counts.artifacts', 2)
            ->where('counts.collections', 2)->where('counts.reports', 2)
            ->has('recentArtifacts', 2)->has('recentCollections', 2)->has('recentReports', 2));
        $this->get('/projects')->assertInertia(fn (Assert $page) => $page
            ->where('nav', function ($nav) {
                $links = collect($nav)->keyBy('label');

                return $links->has('Default settings') && ! $links->has('Your artifacts')
                    && collect(['Artifacts', 'Collections', 'Reports'])->every(fn ($label) => $links[$label]['section'] === 'Your research');
            }));
    }

    public function test_empty_research_indexes_are_available_without_a_project(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['collections', 'reports'] as $path) {
            $this->get('/'.$path)->assertInertia(fn (Assert $page) => $page->where('project', null)->has('projects', 0)->has($path, 0));
        }
    }

    public function test_workspace_lists_own_outputs_reports_and_collections_without_changing_projects_schema(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $items = $this->outputs($project);
        $items['report']->versions()->create(['number' => 1, 'title' => 'Published findings', 'blocks' => []]);
        $this->outputs(Project::factory()->create());
        $deleted = Project::factory()->for($user)->create();
        $this->outputs($deleted);
        $deleted->delete();

        $this->actingAs($user)->get('/projects')->assertInertia(fn (Assert $page) => $page
            ->component('Projects')->has('projects', 1)->where('projects.0.slug', $project->slug)
            ->has('recentArtifacts', 1)->where('recentArtifacts.0.id', $items['artifact']->id)
            ->where('recentArtifacts.0.project.id', $project->id)->where('recentArtifacts.0.kind', 'image')
            ->where('recentArtifacts.0.visibility', 'unlisted')->where('recentArtifacts.0.password_protected', true)
            ->where('recentArtifacts.0.version_count', 1)->where('recentArtifacts.0.management_url', '/artifacts/'.$items['artifact']->id)
            ->missing('recentArtifacts.0.password_hash')
            ->has('recentCollections', 1)->where('recentCollections.0.id', $items['collection']->id)
            ->where('recentCollections.0.item_count', 1)->where('recentCollections.0.visibility', 'private')
            ->where('recentCollections.0.management_url', '/collections/'.$items['collection']->id.'/edit')
            ->has('recentReports', 1)->where('recentReports.0.id', $items['report']->id)
            ->where('recentReports.0.version_count', 1)->where('recentReports.0.visibility', 'private')
            ->where('recentReports.0.management_url', '/reports/'.$items['report']->id.'/edit')
            ->where('recentReports.0.url', $items['report']->shareUrl()));
    }

    public function test_empty_workspace_has_no_outputs_and_draft_reports_have_no_reader_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/projects')->assertInertia(fn (Assert $page) => $page
            ->has('projects', 0)->has('recentArtifacts', 0)->has('recentReports', 0)->has('recentCollections', 0));
        $items = $this->outputs(Project::factory()->for($user)->create());
        $this->get('/projects')->assertInertia(fn (Assert $page) => $page
            ->where('recentReports.0.id', $items['report']->id)->where('recentReports.0.version_count', 0)
            ->where('recentReports.0.url', null));
    }

    public function test_recent_lists_are_bounded_and_sorted_without_limiting_project_list(): void
    {
        $user = User::factory()->create();
        $newest = null;
        for ($index = 0; $index < 7; $index++) {
            $project = Project::factory()->for($user)->create();
            $items = $this->outputs($project);
            foreach ($items as $item) {
                $item->update(['updated_at' => now()->addMinutes($index)]);
            }
            $newest = $items;
        }
        $this->actingAs($user)->get('/projects')->assertInertia(fn (Assert $page) => $page
            ->has('projects', 7)->has('recentArtifacts', 6)->has('recentReports', 4)->has('recentCollections', 4)
            ->where('recentArtifacts.0.id', $newest['artifact']->id)
            ->where('recentReports.0.id', $newest['report']->id)
            ->where('recentCollections.0.id', $newest['collection']->id));
    }
}
