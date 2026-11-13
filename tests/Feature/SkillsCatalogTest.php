<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\Layer;
use App\Support\Resolver;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillsCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function resolve(User $user, array $choices): array
    {
        $resolved = (new Resolver($user))->resolve($choices);
        $this->assertSame([], Layer::envelopeProblems($resolved['envelope']));

        return DefaultsPreview::merge($resolved['envelope']['layers']);
    }

    public function test_standard_defaults_follow_the_language_and_workflow(): void
    {
        $user = User::factory()->create();
        foreach (['r' => ['ggplot2-figures'], 'python' => ['matplotlib-figures'], 'stata' => ['stata-graphs']] as $language => $figures) {
            foreach ($language === 'stata' ? ['scripts'] : ['quarto', 'jupyter', 'scripts'] as $workflow) {
                $spec = $this->resolve($user, ['blueprint' => 'framework/project', 'language' => $language, 'workflow' => $workflow]);
                $skills = array_filter($spec['skills'] ?? []);
                foreach ([...$figures, 'writing', 'git-workflow'] as $key) {
                    $this->assertArrayHasKey($key, $skills);
                }
                $this->assertArrayNotHasKey('cleaning-data', $skills);
                $this->assertArrayNotHasKey('model-results', $skills);
                $this->assertArrayNotHasKey('framework-cache', $skills);
                $this->assertArrayNotHasKey('framework-reports', $skills);
                $this->assertSame($workflow === 'quarto', isset($skills['quarto-documents']));
                $this->assertSame($workflow === 'jupyter', isset($skills['jupyter-notebooks']));
                $this->assertSame($language === 'stata', isset($skills['framework-stata']));
                foreach ($skills as $entry) {
                    if (isset($entry['sync'])) {
                        $this->assertSame($language, $entry['sync']['variant']);
                    }
                }
                $this->assertCount($language === 'stata' ? 4 : ($workflow === 'scripts' ? 7 : 8), $skills);
            }
        }
    }

    public function test_sensitive_defaults_git_off_and_ai_off(): void
    {
        $user = User::factory()->create();
        foreach (['r', 'python', 'stata'] as $language) {
            $choices = ['blueprint' => 'framework/project_sensitive', 'language' => $language];
            $skills = array_filter($this->resolve($user, $choices)['skills'] ?? []);
            $this->assertArrayHasKey('framework-sensitive-data', $skills);
            $withoutGit = $this->resolve($user, $choices + ['settings' => ['git' => false]]);
            $this->assertArrayNotHasKey('git-workflow', array_filter($withoutGit['skills'] ?? []));
            $withoutAI = $this->resolve($user, $choices + ['skills' => ['framework/writing'], 'settings' => ['ai' => false]]);
            $this->assertEmpty(array_filter($withoutAI['skills'] ?? []));
        }
    }

    public function test_sets_list_only_members_for_the_requested_language_and_extras_are_inline(): void
    {
        $user = User::factory()->create();
        foreach (['r' => 'ggplot2', 'python' => 'matplotlib', 'stata' => 'stata-graphs'] as $language => $prefix) {
            $sets = $this->actingAs($user)->getJson('/api/v1/skill-sets?language='.$language)->assertOk()->json('skill_sets');
            $visualization = collect($sets)->firstWhere('key', 'framework/visualization');
            $this->assertCount(1, $visualization['skills']);
            $this->assertSame(match ($language) {
                'r' => ['ggplot2'], 'python' => ['matplotlib', 'seaborn'], 'stata' => [],
            }, $visualization['packages']);
            foreach ($visualization['skills'] as $key) {
                $this->assertStringStartsWith($prefix, $key);
            }
        }
        foreach (['r', 'python'] as $language) {
            $spec = $this->resolve($user, ['blueprint' => 'framework/project', 'language' => $language, 'skill_sets' => ['framework/framework-extras']]);
            foreach (['framework-cache', 'framework-databases', 'framework-publishing'] as $key) {
                $this->assertArrayNotHasKey('package', $spec['skills'][$key]);
                $this->assertSame($language, $spec['skills'][$key]['sync']['variant']);
            }
        }
        // ggplot2-figures is inline until a framework release ships it
        $spec = $this->resolve($user, ['blueprint' => 'framework/project', 'language' => 'r', 'skill_sets' => ['framework/ggplot2']]);
        $this->assertArrayNotHasKey('package', $spec['skills']['ggplot2-figures']);
        $this->assertSame('r', $spec['skills']['ggplot2-figures']['sync']['variant']);
    }

    public function test_reseeding_preserves_custom_blueprints_and_their_skill_keys_and_folders(): void
    {
        $user = User::factory()->create();
        $source = Blueprint::whereNull('user_id')->where('key', 'course')->sole()->latestVersion;
        $courseKeys = ['analysis-report', 'data-cleaning', 'ggplot-style', 'git', 'notebooks', 'outputs', 'packages', 'protected-data', 'regression-modeling', 'viz-house-style'];
        $skills = array_fill_keys($courseKeys, ['content' => "---\nname: course-skill\ndescription: Course instructions.\n---\n\nCourse instructions.\n"]);
        $course = Blueprint::create(['user_id' => $user->id, 'key' => 'yale-course', 'name' => 'Yale course', 'visibility' => 'private']);
        $course->publish($source->base, [], ['skills' => $skills]);
        $choices = ['blueprint' => $course->handle, 'language' => 'r'];
        $before = $this->resolve($user, $choices);
        $versions = Facet::whereNull('user_id')->where('kind', 'skill_set')->get()->mapWithKeys(fn ($set) => [$set->key => $set->latestVersion->id])->all();
        $this->seed(BlueprintSeeder::class);
        $after = $this->resolve($user, $choices);
        $this->assertSame($before['skills'], $after['skills']);
        $this->assertEqualsCanonicalizing($courseKeys, array_keys(array_filter($after['skills'])));
        $this->assertSame($before['directories'], $after['directories']);
        $this->assertSame($versions, Facet::whereNull('user_id')->where('kind', 'skill_set')->get()->mapWithKeys(fn ($set) => [$set->key => $set->latestVersion->id])->all());
    }

    public function test_copying_and_editing_a_set_preserves_other_languages(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $set = Facet::whereNull('user_id')->where('key', 'visualization')->sole();
        $this->actingAs($user)->post("/skill-sets/{$set->id}/fork", ['language' => 'python'])->assertSessionHasNoErrors();
        $copy = Facet::where('user_id', $user->id)->sole();
        $this->assertSame($set->latestVersion->language_layers, $copy->latestVersion->language_layers);
        $this->assertSame('visualization', $copy->category);
        $this->get("/skill-sets/{$copy->id}/edit?language=python")->assertInertia(fn ($page) => $page
            ->where('variant', 'python')
            ->where('values.skills.0.name', 'matplotlib-figures'));
        $before = $copy->latestVersion->language_layers;
        $this->put("/skill-sets/{$copy->id}", [
            'name' => $copy->name, 'visibility' => 'private', 'language' => 'python', 'variant' => 'python',
            'files' => [], 'skills' => [], 'packages' => '',
        ])->assertSessionHasNoErrors();
        $after = $copy->fresh()->latestVersion;
        $this->assertSame($before['r'], $after->language_layers['r']);
        $this->assertSame($before['stata'], $after->language_layers['stata']);
        $this->assertEmpty($after->catalogLayer('python')['skills'] ?? []);
        $this->assertSame(['r', 'python', 'stata'], $copy->fresh()->language);
    }
}
