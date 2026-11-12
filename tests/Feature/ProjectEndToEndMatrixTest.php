<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Project;
use App\Models\User;
use App\Support\DefaultsPreview;
use App\Support\Layer;
use App\Support\ProjectDefaults;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectEndToEndMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_supported_project_can_be_previewed_created_and_exported(): void
    {
        $this->seed(BlueprintSeeder::class);
        $this->actingAs($user = User::factory()->create(['email_verified_at' => now()]));
        $directory = getenv('FW_E2E_EXPORT_DIR') ?: null;
        $cases = [];
        foreach (Blueprint::whereNull('user_id')->with('latestVersion.facetVersions.facet')->orderBy('position')->get() as $blueprint) {
            $compatibility = $blueprint->latestVersion->compatibility();
            foreach ($compatibility['languages'] as $language) {
                $workflows = $language === 'stata' ? ['scripts'] : $compatibility['workflows'];
                foreach ($workflows as $workflow) {
                    $formats = $language === 'r' && $workflow === 'quarto' ? ['quarto', 'rmarkdown'] : [$workflow];
                    foreach ($formats as $format) {
                        $id = $language.'-'.$blueprint->key.'-'.$format;
                        $initial = $this->postJson('/projects/preview', ['blueprint_id' => $blueprint->id, 'language' => $language, 'workflow' => $workflow])->assertOk()->json();
                        $choices = [
                            'name' => 'E2E '.$id, 'blueprint_id' => $blueprint->id, 'language' => $language,
                            'workflow' => $workflow, 'customized' => true, 'structure' => $initial['structure'],
                            'notebook_format' => $workflow === 'scripts' ? null : $format,
                            'tools' => array_map(fn ($tool) => $tool['on'], (array) $initial['tools']),
                            'package_sets' => array_column($initial['package_sets'], 'key'),
                            'skill_sets' => array_column($initial['skill_sets'], 'key'), 'skills' => $initial['skills'],
                            'settings' => ['git' => true, 'hooks' => ProjectDefaults::HOOKS,
                                'ai' => ['enabled' => true, 'assistants' => ['claude' => true, 'codex' => true, 'copilot' => true]]],
                        ];
                        $preview = $this->postJson('/projects/preview', $choices)->assertOk()->json();
                        $this->post('/projects', $choices)->assertSessionHasNoErrors()->assertRedirect();
                        $project = Project::where('user_id', $user->id)->where('name', $choices['name'])->sole();
                        $envelope = ProjectLayers::for($project);
                        $this->assertSame([], Layer::envelopeProblems($envelope), $id);
                        $spec = DefaultsPreview::merge($envelope['layers']);
                        $this->assertSame($language, $project->language, $id);
                        $this->assertSame($workflow, $spec['scaffold']['workflow'] ?? null, $id);
                        $case = ['id' => $id, 'type' => $blueprint->key, 'language' => $language, 'format' => $format, 'preview' => $preview, 'spec' => $spec];
                        $cases[] = $case;
                        if ($directory) {
                            @mkdir($directory.'/cases', 0777, true);
                            file_put_contents($directory.'/cases/'.$id.'.json', json_encode(Layer::toJsonValue($envelope), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        }
                    }
                }
            }
        }
        $this->assertCount(44, $cases);
        if ($directory) {
            file_put_contents($directory.'/matrix.json', json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
