<?php

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the system library from master files extracted from the framework R
 * package (database/seeders/masters/): its skills, one base facet and one
 * blueprint per project type, and the stock facets. Idempotent: rows are
 * matched by key, and a new version is added only when a master changed.
 */
class BlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $mastersDir = database_path('seeders/masters');

        $this->seedSkills("$mastersDir/skills");
        $this->seedBases($mastersDir);
        $this->seedFacets(json_decode(File::get("$mastersDir/facets.json"), true));
    }

    private function seedSkills(string $dir): void
    {
        foreach (File::files($dir) as $position => $file) {
            $content = File::get($file->getPathname());
            preg_match('/^description:\s*(.+)$/m', $content, $description);

            $skill = Skill::updateOrCreate(
                ['user_id' => null, 'key' => $file->getFilenameWithoutExtension()],
                [
                    'name' => $file->getFilenameWithoutExtension(),
                    'description' => $description[1] ?? null,
                    'visibility' => 'public',
                    'position' => $position,
                ],
            );

            $skill->publishVersion(['content' => $content, 'files' => []]);
        }
    }

    private function seedBases(string $mastersDir): void
    {
        $definitions = json_decode(File::get("$mastersDir/blueprints.json"), true);

        // Bare (no blueprint) leads: it's the default; the rest are opinionated offerings
        if (isset($definitions['bare'])) {
            $definitions = ['bare' => $definitions['bare']] + $definitions;
        }

        $position = 0;

        foreach ($definitions as $key => $definition) {
            // A top-level null (bare's quarto) has nothing beneath it to remove
            $layer = ['type' => $key] + array_filter($definition['structure'] ?? [], fn ($value) => $value !== null);

            // R keys its sensitive-project behavior on this flag, so the
            // restricted-data facet can turn it on for any project type
            if ($key === 'project_sensitive') {
                $layer['security'] = ['sensitive' => true];
            }

            $agentsPath = "$mastersDir/agents/$key.md";
            if (File::exists($agentsPath)) {
                $layer['files'] = ['AGENTS.md' => ['content' => File::get($agentsPath), 'template' => true]];
            }

            if (! empty($definition['skills'])) {
                $layer['skills'] = array_fill_keys($definition['skills'], ['package' => true]);
            }

            $attributes = [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'visibility' => 'public',
                'position' => $position++,
            ];

            $base = Facet::updateOrCreate(['user_id' => null, 'key' => $key], $attributes + ['kind' => 'base']);
            $baseVersion = $base->publishVersion(['layer' => $layer]);

            $blueprint = Blueprint::updateOrCreate(['user_id' => null, 'key' => $key], $attributes + ['is_system' => true]);
            $blueprint->publish($baseVersion, []);
        }
    }

    private function seedFacets(array $definitions): void
    {
        $position = 0;

        foreach ($definitions as $key => $definition) {
            $facet = Facet::updateOrCreate(['user_id' => null, 'key' => $key], [
                'kind' => 'facet',
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'category' => $definition['category'] ?? null,
                'question' => $definition['question'] ?? null,
                'visibility' => 'public',
                'position' => $position++,
            ]);

            $facet->publishVersion(['layer' => $definition['layer']]);
        }
    }
}
