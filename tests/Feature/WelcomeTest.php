<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
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

    private function details(string $language = 'r', string $type = 'project'): array
    {
        return ['name' => 'Ada Researcher', 'email' => 'ada@example.org', 'orcid' => '0000-0002-1825-0097', 'url' => 'https://example.org', 'affiliations' => [['name' => 'Example University', 'department' => 'Statistics', 'city' => 'Boston', 'country' => 'United States']], 'language' => $language, 'blueprint_id' => Blueprint::whereNull('user_id')->where('key', $type)->sole()->id];
    }

    private function choices(string $language): array
    {
        return ['name' => 'My research setup', 'visibility' => 'public', 'language' => $language, 'workflow' => $language === 'stata' ? 'scripts' : 'jupyter', 'notebook_format' => $language === 'stata' ? null : 'jupyter', 'structure' => 'framework/project_sensitive', 'agents' => ['claude', 'codex'], 'tools' => $language === 'python' ? ['uv' => true] : [], 'package_sets' => [], 'skill_sets' => ['framework/framework'], 'skills' => [], 'settings' => ['git' => true, 'hooks' => ['data_security', 'check_sensitive_dirs']], 'ide' => $language === 'r' ? 'rstudio' : 'vscode'];
    }

    public function test_a_new_account_gets_an_introduction_and_private_author_email(): void
    {
        $user = $this->newcomer();
        $this->actingAs($user)->get('/projects')->assertRedirect('/welcome');
        $this->get('/welcome')->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('again', false)->where('author.name', $user->name)->where('author.email', '')->where('accountEmail', $user->email)->where('blueprints.0.key', 'project'));
        $this->get('/welcome/blueprint')->assertRedirect('/welcome');
        $this->get('/welcome/ready')->assertRedirect('/welcome');
    }

    public function test_details_are_a_session_draft_until_the_private_blueprint_is_saved(): void
    {
        foreach (['r', 'python', 'stata'] as $language) {
            $user = $this->newcomer();
            $this->actingAs($user)->post('/welcome', $this->details($language))->assertSessionHasNoErrors()->assertRedirect('/welcome/blueprint');
            $this->assertNull($user->fresh()->setup_completed_at);
            $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
            $this->get('/welcome/blueprint')->assertInertia(fn (Assert $page) => $page->component('Blueprints/Builder')->where('onboarding.language', $language)->where('start.language', $language)->where('onboarding.source', 'Standard'));
            $this->post('/welcome/complete', $this->choices($language))->assertSessionHasNoErrors()->assertRedirect('/welcome/ready');
            $user->refresh();
            $blueprint = Blueprint::where('user_id', $user->id)->sole();
            $this->assertSame('private', $blueprint->visibility);
            $this->assertSame($language, $blueprint->language);
            $this->assertSame($blueprint->id, ProjectDefaults::blueprint($user, $language)->id);
            $this->assertSame($language, ProjectDefaults::language($user));
            $this->assertNotNull($user->setup_completed_at);
            $author = $user->globalSettings()->document['author'];
            $this->assertSame('Ada Researcher', $author['name']);
            $this->assertSame('ada@example.org', $author['email']);
            $this->assertSame('Example University', $author['affiliation']);
            $this->assertSame('Statistics', $author['affiliations'][0]['department']);
            $resolved = (new Resolver($user))->resolve(['name' => 'Survey']);
            $this->assertSame($language, $resolved['language']);
            $this->assertSame($language === 'stata' ? 'scripts' : 'jupyter', $resolved['workflow']);
            $this->assertSame('project_sensitive', $resolved['structure']->facet->key);
            $envelope = ProjectLayers::envelope($user, 'Survey', [], null);
            $this->assertSame('ada@example.org', $envelope['author']['email']);
            $this->get('/welcome/ready')->assertInertia(fn (Assert $page) => $page->component('WelcomeReady')->where('language', $language)->where('blueprint.id', $blueprint->id));
            // A repeated submit cannot create a second default blueprint.
            $this->post('/welcome/complete', $this->choices($language))->assertRedirect('/welcome');
            $this->assertSame(1, Blueprint::where('user_id', $user->id)->count());
        }
    }

    public function test_invalid_details_and_incompatible_starters_do_not_save(): void
    {
        $user = $this->newcomer();
        $this->actingAs($user)->post('/welcome', array_replace($this->details(), ['email' => 'bad', 'orcid' => 'bad', 'affiliations' => [['name' => '']]]))->assertSessionHasErrors(['email', 'orcid', 'affiliations.0.name']);
        $this->post('/welcome', $this->details('stata', 'course'))->assertSessionHasErrors('blueprint_id');
        $other = Blueprint::create(['name' => 'Other account', 'key' => 'other', 'user_id' => User::factory()->create()->id, 'visibility' => 'private', 'is_system' => false]);
        $this->post('/welcome', array_replace($this->details(), ['blueprint_id' => $other->id]))->assertSessionHasErrors('blueprint_id');
        $this->assertNull($user->fresh()->setup_completed_at);
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
    }

    public function test_blueprint_errors_preserve_the_draft_and_do_not_complete_onboarding(): void
    {
        $user = $this->newcomer();
        $this->actingAs($user)->post('/welcome', $this->details());
        $this->post('/welcome/complete', array_replace($this->choices('r'), ['name' => '']))->assertSessionHasErrors('name');
        $this->post('/welcome/complete', $this->choices('python'))->assertSessionHasErrors('language');
        $this->get('/welcome')->assertInertia(fn (Assert $page) => $page->where('author.email', 'ada@example.org')->where('author.affiliations.0.name', 'Example University'));
        $this->assertNull($user->fresh()->setup_completed_at);
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
    }

    public function test_skipping_discards_the_draft_without_saving_author_details_or_a_blueprint(): void
    {
        $user = $this->newcomer();
        $this->actingAs($user)->post('/welcome', $this->details());
        $this->post('/welcome/skip')->assertRedirect('/dashboard');
        $this->assertNotNull($user->fresh()->setup_completed_at);
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
        $this->assertNotSame('ada@example.org', $user->globalSettings()->document['author']['email'] ?? '');
        $this->get('/welcome/blueprint')->assertRedirect('/welcome');
    }

    public function test_every_starter_populates_a_valid_single_language_blueprint(): void
    {
        foreach (Blueprint::systemTypes()->with('latestVersion')->get() as $source) {
            foreach ($source->latestVersion->compatibility()['languages'] as $language) {
                if (! ProjectDefaults::fits($source, $language)) {
                    continue;
                }
                $user = $this->newcomer();
                $this->actingAs($user)->post('/welcome', $this->details($language, $source->key))->assertSessionHasNoErrors();
                $response = $this->get('/welcome/blueprint')->assertOk();
                $start = json_decode(json_encode($response->getOriginalContent()->getData()['page']['props']['start']), true);
                $workflow = ((array) $start['default_workflow'])[$language];
                $format = $language === 'stata' || $start['notebook_format'] === 'scripts' ? null : $start['notebook_format'];
                $this->post('/welcome/complete', array_replace($start, ['name' => 'Starter test', 'visibility' => 'private', 'workflow' => $workflow, 'notebook_format' => $format]))->assertSessionHasNoErrors()->assertRedirect('/welcome/ready');
                $saved = ProjectDefaults::blueprint($user->fresh(), $language);
                $this->assertSame([$language], $saved->latestVersion->compatibility()['languages']);
                $this->assertSame($source->latestVersion->base_version_id, $saved->latestVersion->base_version_id);
            }
        }
    }
}
