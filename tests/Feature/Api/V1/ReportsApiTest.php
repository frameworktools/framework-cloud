<?php

namespace Tests\Feature\Api\V1;

use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        return Project::factory()->for(User::factory()->create(['email_verified_at' => now(), 'can_publish' => true]))->create(['slug' => 'penguin-study']);
    }

    private function figure(Project $project): Document
    {
        return app(ArtifactPublisher::class)->publish($project, ['slug' => 'flipper-and-mass', 'visibility' => 'public',
            'file' => UploadedFile::fake()->createWithContent('figure.html', '<html>FIGURE</html>')])->document;
    }

    public function test_a_script_builds_and_publishes_a_notebook_from_markdown_html_and_an_artifact(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $this->figure($project);
        Sanctum::actingAs($project->user, ['*']);

        $response = $this->postJson('/api/v1/projects/penguin-study/reports', [
            'title' => 'Penguin notebook', 'slug' => 'penguin-notebook', 'visibility' => 'public', 'publish' => true,
            'blocks' => [
                ['type' => 'text', 'text' => "# Body mass\n\nFlipper length predicts **body mass**.\n\n<script>alert(1)</script>"],
                ['type' => 'artifact', 'artifact' => 'flipper-and-mass', 'caption' => 'Flipper length and body mass'],
                ['type' => 'text', 'format' => 'html', 'text' => '<p>Species adds <em>more</em>.</p><iframe src="x"></iframe>'],
            ],
        ])->assertCreated()
            ->assertJsonPath('slug', 'penguin-notebook')
            ->assertJsonPath('latest_version', 1)
            ->assertJsonPath('blocks.0.id', 'b1')
            ->assertJsonPath('blocks.1.type', 'artifact');

        $this->get($response->json('url'))->assertOk()
            ->assertSee('Flipper length predicts', false)->assertSee('<strong>body mass</strong>', false)
            ->assertSee('Species adds', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('src="x"', false);
    }

    public function test_markdown_alone_makes_a_one_block_draft_and_patch_republishes_at_the_same_link(): void
    {
        $project = $this->project();
        Sanctum::actingAs($project->user, ['*']);

        $created = $this->postJson('/api/v1/projects/penguin-study/reports', ['title' => 'Notes', 'markdown' => '## First draft'])
            ->assertCreated()->assertJsonPath('latest_version', null)->assertJsonCount(1, 'blocks');
        $slug = $created->json('slug');

        $this->patchJson("/api/v1/projects/penguin-study/reports/$slug", ['markdown' => '## Second draft', 'visibility' => 'unlisted', 'publish' => true])
            ->assertOk()->assertJsonPath('latest_version', 1)->assertJsonPath('url', $created->json('url'));
        $this->postJson("/api/v1/projects/penguin-study/reports/$slug/publish")->assertOk()->assertJsonPath('latest_version', 2)->assertJsonCount(2, 'versions');
        $this->getJson('/api/v1/projects/penguin-study/reports')->assertOk()->assertJsonPath('reports.0.slug', $slug);
    }

    public function test_project_tokens_work_on_their_own_project_only(): void
    {
        $project = $this->project();
        $other = Project::factory()->for($project->user)->create(['slug' => 'other-study']);
        $token = ['Authorization' => 'Bearer '.$project->issuePullToken()];

        $this->postJson('/api/v1/projects/penguin-study/reports', ['title' => 'From the project token', 'markdown' => 'Hello'], $token)->assertCreated();
        $this->postJson('/api/v1/projects/other-study/reports', ['title' => 'Elsewhere', 'markdown' => 'Hello'], $token)->assertForbidden();
        $this->assertSame(0, Report::where('project_id', $other->id)->count());
    }

    public function test_bad_input_is_rejected_with_clear_messages(): void
    {
        $project = $this->project();
        Sanctum::actingAs($project->user, ['*']);

        $this->postJson('/api/v1/projects/penguin-study/reports', ['title' => 'Both', 'markdown' => 'x', 'blocks' => []])->assertJsonValidationErrors('markdown');
        $this->postJson('/api/v1/projects/penguin-study/reports', ['title' => 'Missing', 'blocks' => [['type' => 'artifact', 'artifact' => 'nope']]])->assertJsonValidationErrors('blocks.0.artifact');
        $this->getJson('/api/v1/projects/someone-else/reports')->assertNotFound();
    }
}
