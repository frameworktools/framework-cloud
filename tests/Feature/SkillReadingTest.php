<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\Resolver;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SkillReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function system(string $key): Skill
    {
        return Skill::whereNull('user_id')->where('key', $key)->firstOrFail();
    }

    public function test_framework_skills_carry_each_languages_own_text(): void
    {
        $workflow = $this->system('framework-workflow');
        $this->assertSame(['r', 'python'], $workflow->language);
        $this->assertStringContainsString('fw.scaffold()', $workflow->latestVersion->contentFor('python'));
        $this->assertStringNotContainsString('fw.scaffold()', $workflow->latestVersion->contentFor('r'));
        // The Python text is framework-py's own copy
        $this->assertSame(file_get_contents(database_path('seeders/masters/skills/python/framework-workflow.md')), $workflow->latestVersion->contentFor('python'));
        // ggplot2 is R's alone
        $this->assertSame(['r'], $this->system('ggplot2-exploring')->language);
    }

    public function test_the_read_view_renders_the_whole_skill_for_the_chosen_language(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['language' => 'python']]);
        $workflow = $this->system('framework-workflow');

        $this->actingAs($user)->get("/skills/{$workflow->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Skills/Show')
            ->where('language', 'python')
            ->where('variants', ['r', 'python'])
            ->where('source', $workflow->latestVersion->contentFor('python'))
            ->where('html', fn (string $html) => str_contains($html, '<pre><code class="language-python">') && ! str_contains($html, 'name: framework-workflow')));

        $this->actingAs($user)->get("/skills/{$workflow->id}?language=r&from=skill-sets")->assertInertia(fn (Assert $page) => $page
            ->where('language', 'r')->where('from', 'skill-sets')
            ->where('source', $workflow->latestVersion->content));

        // An R-only skill shows R even when Python is asked for
        $this->actingAs($user)->get('/skills/'.$this->system('ggplot2-exploring')->id.'?language=python')->assertInertia(fn (Assert $page) => $page->where('language', 'r')->where('variants', ['r']));

        // Reading someone else's skill doesn't open the editor
        $this->actingAs($user)->get("/skills/{$workflow->id}/edit")->assertRedirect("/skills/{$workflow->id}");
    }

    public function test_imported_markdown_is_rendered_as_data(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/skills', ['visibility' => 'private', 'language' => 'python', 'content' => "---\nname: risky\ndescription: Use when testing.\n---\n\n# Risky\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))\n"])->assertSessionHasNoErrors();
        $skill = Skill::where('user_id', $user->id)->sole();
        $this->assertSame(['python'], $skill->language);

        $this->actingAs($user)->get("/skills/{$skill->id}")->assertInertia(fn (Assert $page) => $page
            ->where('html', fn (string $html) => ! str_contains($html, '<script') && ! str_contains($html, 'javascript:')));
    }

    public function test_catalogs_start_at_the_preferred_language_and_sets_list_their_skills(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'project_defaults' => ['language' => 'python']]);

        $this->actingAs($user)->get('/skills')->assertInertia(fn (Assert $page) => $page->where('language', 'python')->where('skills.0.language', ['r', 'python']));
        $this->actingAs($user)->get('/skill-sets')->assertInertia(fn (Assert $page) => $page
            ->component('Pieces/SkillSets')
            ->where('language', 'python')
            ->where('sets.0.skills', fn ($skills) => collect($skills)->pluck('name')->contains('Reading and saving data'))
            // Each language's description is its own
            ->where('sets.0.skills', fn ($skills) => str_contains(collect($skills)->firstWhere('key', 'framework-workflow')['descriptions']['python'], 'Python'))
            ->where('sets.0.skills.0.language', ['r', 'python'])
            ->where('sets.1.packages.0.name', 'ggplot2'));
    }

    public function test_a_skill_for_another_language_isnt_delivered_and_the_right_text_is(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/skills', ['visibility' => 'private', 'language' => 'r', 'content' => "---\nname: tables\ndescription: Use when making tables.\n---\n\n# Tables\n"])->assertSessionHasNoErrors();
        $handle = Skill::where('user_id', $user->id)->sole()->handle;

        $python = (new Resolver($user))->resolve(['name' => 'S', 'language' => 'python', 'skills' => [$handle]]);
        $this->assertArrayNotHasKey('tables', DefaultsPreview::merge($python['envelope']['layers'])['skills'] ?? []);
        $this->assertStringContainsString('so this Python project leaves it out', implode(' ', $python['notices']));

        $r = (new Resolver($user))->resolve(['name' => 'S', 'language' => 'r', 'skills' => [$handle]]);
        $this->assertStringContainsString('# Tables', DefaultsPreview::merge($r['envelope']['layers'])['skills']['tables']['content']);
        // Framework's skills travel as the package's own copy, which is the language's text
        $this->assertSame(['package' => true], $this->system('framework-data')->layerEntry('python'));
    }
}
