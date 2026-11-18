<?php

namespace Tests\Feature;

use App\Console\Commands\SyncPiecesCommand;
use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\GitHubAccount;
use App\Models\Project;
use App\Models\User;
use App\Services\GitHub\Scaffolder;
use App\Support\Layer;
use App\Support\PackageSkills;
use App\Support\ProjectDefaults;
use App\Support\Resolver;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cloud offers only package skills that the pinned packages ship. The
 * record of shipped skills must describe the pins, and every project Cloud
 * can resolve must scaffold with them.
 */
class PackageSkillsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The pins services.php ships with, whatever a local .env says
        config([
            'services.github.framework_sha' => PackageSkills::defaultRef('r'),
            'services.github.framework_py_sha' => PackageSkills::defaultRef('python'),
            'services.github.fw_version' => PackageSkills::defaultRef('stata'),
        ]);
    }

    public function test_the_record_describes_the_pins_services_ships_with(): void
    {
        foreach (['r', 'python', 'stata'] as $language) {
            $this->assertSame(PackageSkills::defaultRef($language), PackageSkills::record()[$language]['ref'] ?? null,
                "package-skills.json describes another {$language} pin than config/services.php. Run php artisan fw:sync-package-skills.");
        }
    }

    public function test_every_copy_of_a_pin_agrees(): void
    {
        $dockerfile = File::get(base_path('docker/scaffolder/Dockerfile'));
        $arg = fn (string $name) => preg_match("/^ARG {$name}=(\\S+)/m", $dockerfile, $m) ? $m[1] : null;

        $this->assertSame(PackageSkills::defaultRef('r'), $arg('FRAMEWORK_REF'), 'The scaffolder image installs another framework commit.');
        $this->assertSame(PackageSkills::defaultRef('python'), $arg('FRAMEWORK_PY_REF'), 'The scaffolder image installs another framework-py commit.');
        $this->assertSame(PackageSkills::defaultRef('stata'), $arg('FW_VERSION'), 'The scaffolder image installs another fw release.');
        $this->assertSame(PackageSkills::record()['stata']['commit'], $arg('FW_REF'), 'The scaffolder image builds fw from another commit than its release tag.');
        $this->assertSame(PackageSkills::defaultRef('r'), File::json(SyncPiecesCommand::dir().'/lock.json')['commit'],
            'The pieces were copied from another framework commit than the scaffold pin. Run fw:sync-pieces at the pin.');
    }

    public function test_every_package_skill_cloud_names_ships_with_its_pin(): void
    {
        // skill-bundles.json decides which skills Cloud sends as {package: true}
        foreach (File::json(database_path('seeders/masters/skill-bundles.json')) as $key => $variants) {
            foreach (array_keys($variants) as $language) {
                $this->assertContains($key, PackageSkills::record()[$language]['skills'],
                    "skill-bundles.json offers {$key} for {$language}, but ".PackageSkills::packageName($language).' does not ship it.');
            }
        }

        // Pieces copied from the package and Cloud's own sets
        $files = [...File::allFiles(SyncPiecesCommand::dir()), ...File::files(database_path('seeders/masters/sets/skills'))];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'json' || str_contains($file->getPathname(), 'conformance') || $file->getFilename() === 'lock.json') {
                continue;
            }
            $definition = File::json($file->getPathname());
            $layers = ['' => $definition['layer'] ?? []] + ($definition['language_layers'] ?? []);
            foreach ($layers as $language => $layer) {
                foreach ($language ? [$language] : ($definition['language'] ?? ['r', 'python', 'stata']) as $target) {
                    foreach ($layer['skills'] ?? [] as $key => $skill) {
                        if (($skill['package'] ?? false) === true) {
                            $this->assertContains($key, PackageSkills::record()[$target]['skills'], "{$file->getFilename()} names {$key} for {$target}, which its pin does not ship.");
                        }
                    }
                }
            }
        }
    }

    public function test_every_project_framework_blueprints_resolve_to_scaffolds_with_the_pins(): void
    {
        $this->seed(BlueprintSeeder::class);
        $user = User::factory()->create();
        $count = 0;

        foreach (Blueprint::whereNull('user_id')->with('latestVersion')->get() as $blueprint) {
            $compatibility = $blueprint->latestVersion->compatibility();
            foreach ($compatibility['languages'] ?? Project::LANGUAGES as $language) {
                $sets = Facet::whereNull('user_id')->where('kind', 'skill_set')->get()
                    ->filter(fn (Facet $set) => ! $set->language || in_array($language, $set->language, true))
                    ->map(fn (Facet $set) => $set->handle)->values()->all();
                foreach ($compatibility['workflows'] ?? ProjectDefaults::WORKFLOW_KEYS as $workflow) {
                    if (! ProjectDefaults::workflowFits($workflow, $language)) {
                        continue;
                    }
                    // Framework's defaults, and every skill set for the language
                    foreach ([[], ['skill_sets' => $sets]] as $extra) {
                        $resolved = (new Resolver($user))->resolve(['name' => 'Check', 'blueprint' => $blueprint->handle, 'language' => $language, 'workflow' => $workflow] + $extra);
                        $this->assertSame([], PackageSkills::problems($resolved['envelope']), "{$blueprint->handle} {$language} {$workflow}");
                        $count++;
                    }
                }
            }
        }

        $this->assertGreaterThan(20, $count);
    }

    public function test_a_retired_skill_becomes_its_successor(): void
    {
        $envelope = ['spec_version' => 1, 'name' => 'Old', 'layers' => [
            ['kind' => 'facet', 'key' => 'framework/visualization-r', 'version' => 1, 'layer' => ['skills' => ['ggplot2-exploring' => ['package' => true], 'ggplot2-publishing' => ['package' => true]]]],
            // Resolved when ggplot2-figures was not among the pieces, so removed
            ['kind' => 'project', 'key' => 'old choices', 'layer' => ['skills' => ['framework-data' => null, 'ggplot2-figures' => null]]],
            ['kind' => 'project', 'key' => 'old', 'layer' => ['language' => 'r']],
        ]];

        $this->assertNotSame([], PackageSkills::problems($envelope));

        $upgraded = PackageSkills::upgrade($envelope);

        $this->assertSame(['ggplot2-figures' => ['package' => true]], $upgraded['layers'][0]['layer']['skills']);
        $this->assertSame(['framework-data' => null], $upgraded['layers'][1]['layer']['skills']);
        $this->assertSame([], PackageSkills::problems($upgraded));
    }

    public function test_a_piece_from_before_a_retirement_resolves_to_the_successor(): void
    {
        $this->seed(BlueprintSeeder::class);
        $user = User::factory()->create();
        // The visualization set as it was before framework retired the two ggplot2 skills
        Facet::whereNull('user_id')->where('key', 'visualization')->firstOrFail()->publishVersion([
            'layer' => ['skills' => []],
            'language_layers' => ['r' => ['skills' => ['ggplot2-exploring' => ['package' => true], 'ggplot2-publishing' => ['package' => true]]]],
        ]);

        $resolved = (new Resolver($user))->resolve(['name' => 'Figures', 'blueprint' => 'framework/project', 'language' => 'r', 'workflow' => 'quarto', 'skill_sets' => ['framework/visualization']]);

        $skills = [];
        foreach ($resolved['envelope']['layers'] as $entry) {
            foreach ($entry['layer']['skills'] ?? [] as $key => $skill) {
                $skills[$key] = $skill;
            }
        }
        $this->assertSame(['package' => true], $skills['ggplot2-figures'] ?? null);
        $this->assertArrayNotHasKey('ggplot2-exploring', $skills);
        $this->assertArrayNotHasKey('ggplot2-publishing', $skills);
    }

    public function test_a_skill_the_pin_lacks_is_a_layers_problem(): void
    {
        $problems = Layer::envelopeProblems(['spec_version' => 1, 'name' => 'Bad', 'layers' => [
            ['kind' => 'base', 'key' => 'framework/project', 'layer' => ['type' => 'project']],
            ['kind' => 'blueprint', 'key' => 'mine', 'layer' => ['skills' => ['plotting-later' => ['package' => true], 'removed-later' => ['package' => true]]]],
            ['kind' => 'project', 'key' => 'bad', 'layer' => ['language' => 'r', 'skills' => ['removed-later' => null]]],
        ]]);

        $this->assertContains('The skill plotting-later does not ship with framework '.PackageSkills::record()['r']['version'].', which builds R projects here. Remove it from the blueprint or skill set that adds it.', $problems);
        // A layer above removed it, so it never reaches the package
        $this->assertEmpty(array_filter($problems, fn ($p) => str_contains($p, 'removed-later')));
    }

    public function test_a_server_pinned_elsewhere_skips_the_check(): void
    {
        config(['services.github.framework_sha' => str_repeat('a', 40)]);

        $this->assertNull(PackageSkills::shipped('r'));
        $this->assertSame([], PackageSkills::problems(['layers' => [['layer' => ['skills' => ['plotting-later' => ['package' => true]]]]]]));
    }

    public function test_scaffolding_a_project_with_a_missing_skill_creates_no_repository(): void
    {
        config(['services.github.client_id' => 'Iv1.test']);
        Http::fake();
        $user = User::factory()->create();
        (new GitHubAccount(['user_id' => $user->id, 'github_id' => 42, 'login' => 'ada']))
            ->fillTokens('ghu_access', 'ghr_refresh', 28800, 15897600)->save();
        $envelope = ['spec_version' => 1, 'name' => 'Bad', 'author' => ['name' => 'Ada'], 'layers' => [
            ['kind' => 'base', 'key' => 'framework/project', 'layer' => ['type' => 'project']],
            ['kind' => 'facet', 'key' => 'ada/figures', 'layer' => ['skills' => ['plotting-later' => ['package' => true]]]],
            ['kind' => 'project', 'key' => 'bad', 'layer' => ['language' => 'r']],
        ]];
        $project = $user->projects()->create(['name' => 'Bad', 'slug' => 'bad', 'language' => 'r', 'resolved' => json_encode($envelope)]);

        app(Scaffolder::class)->start($project->fresh(), null, 'bad', true);

        $project->refresh();
        $this->assertSame('failed', $project->scaffold_status);
        $this->assertStringContainsString('The skill plotting-later does not ship with framework', $project->scaffold_error);
        Http::assertNothingSent();
    }
}
