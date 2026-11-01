<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Resolver;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function newcomer(): User
    {
        return User::factory()->create(['setup_completed_at' => null]);
    }

    public function test_a_new_account_starts_with_guided_setup_once(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->get('/projects')->assertRedirect('/welcome');
        $this->actingAs($user)->get('/welcome')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('again', false)
            ->where('values.workflows.python', 'jupyter')
            ->where('blueprints.0.key', 'project'));

        $this->actingAs($user)->post('/welcome/skip')->assertRedirect('/projects');
        $this->assertNotNull($user->fresh()->setup_completed_at);
        $this->actingAs($user)->get('/projects')->assertOk();
    }

    public function test_the_answers_become_defaults_every_creation_path_uses(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->post('/welcome', [
            'language' => 'python', 'project_type' => 'project_sensitive', 'workflow' => 'scripts',
            'editors' => ['vscode'], 'git' => false, 'ai' => true, 'agents' => ['claude', 'codex'],
        ])->assertRedirect('/projects');

        $user->refresh();
        $doc = $user->globalSettings()->document;
        $this->assertNotNull($user->setup_completed_at);
        $this->assertSame('python', $user->project_defaults['language']);
        $this->assertSame('project_sensitive', $doc['defaults']['project_type']);
        $this->assertSame(['r' => 'quarto', 'python' => 'scripts', 'stata' => 'scripts'], $doc['defaults']['workflows']);
        $this->assertSame('vscode', $doc['defaults']['ide']);
        $this->assertFalse($doc['defaults']['use_git']);
        $this->assertSame(['claude', 'codex'], $doc['ai']['assistants']);

        // The same thing R, Python, and fw get when they ask for the defaults
        $resolved = (new Resolver($user))->resolve(['name' => 'Survey']);
        $this->assertSame('python', $resolved['language']);
        $this->assertSame('scripts', $resolved['workflow']);
        $this->assertSame('project_sensitive', $resolved['structure']->facet->key);

        // Running it again shows the saved answers
        $this->actingAs($user)->get('/welcome')->assertInertia(fn (Assert $page) => $page->where('again', true)->where('values.language', 'python'));
    }

    public function test_answers_the_project_type_cant_take_are_refused(): void
    {
        $user = $this->newcomer();
        $answers = ['editors' => [], 'git' => true, 'ai' => false, 'agents' => []];

        // Course is built on Quarto
        $this->actingAs($user)->post('/welcome', $answers + ['language' => 'r', 'project_type' => 'course', 'workflow' => 'jupyter'])->assertSessionHasErrors('workflow');
        // Course has no Stata
        $this->actingAs($user)->post('/welcome', $answers + ['language' => 'stata', 'project_type' => 'course', 'workflow' => 'scripts'])->assertSessionHasErrors('project_type');
        // Stata has only Scripts only
        $this->actingAs($user)->post('/welcome', $answers + ['language' => 'stata', 'project_type' => 'project', 'workflow' => 'quarto'])->assertSessionHasErrors('workflow');
        $this->assertNull($user->fresh()->setup_completed_at);
    }

    public function test_r_markdown_is_an_answer_and_setup_starts_from_what_was_saved(): void
    {
        $user = $this->newcomer();
        $this->actingAs($user)->get('/welcome')->assertInertia(fn (Assert $page) => $page
            ->where('saved.any', false)->where('saved.language', false)->where('saved.workflows', []));

        $answers = ['project_type' => 'project', 'editors' => ['vscode'], 'git' => true, 'ai' => false, 'agents' => []];
        $this->actingAs($user)->post('/welcome', $answers + ['language' => 'r', 'workflow' => 'rmarkdown'])->assertSessionHasNoErrors();
        $doc = $user->fresh()->globalSettings()->document;
        $this->assertSame('quarto', $doc['defaults']['workflows']['r']);
        $this->assertSame('rmarkdown', $doc['defaults']['notebook_format']);
        $this->assertSame('vscode', $doc['defaults']['ide']);

        // Rerunning starts from the saved answers, editors included
        $this->actingAs($user)->get('/welcome')->assertInertia(fn (Assert $page) => $page
            ->where('saved.any', true)->where('saved.language', true)->where('saved.workflows', ['r', 'python', 'stata'])
            ->where('values.ide', 'vscode')->where('values.rmarkdown', true));

        // A Python answer leaves R's R Markdown alone
        $this->actingAs($user)->post('/welcome', $answers + ['language' => 'python', 'workflow' => 'jupyter'])->assertSessionHasNoErrors();
        $this->assertSame('rmarkdown', $user->fresh()->globalSettings()->document['defaults']['notebook_format']);

        // R Markdown is R's alone
        $this->actingAs($user)->post('/welcome', $answers + ['language' => 'python', 'workflow' => 'rmarkdown'])->assertSessionHasErrors('workflow');
    }

    public function test_skipping_saves_nothing_but_the_skip(): void
    {
        $user = $this->newcomer();
        $before = $user->globalSettings()->document;

        $this->actingAs($user)->post('/welcome/skip')->assertRedirect('/projects');

        $this->assertSame($before, $user->fresh()->globalSettings()->document);
        $this->assertNull($user->fresh()->project_defaults['language'] ?? null);
    }
}
