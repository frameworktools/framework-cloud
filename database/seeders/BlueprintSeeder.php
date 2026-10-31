<?php

namespace Database\Seeders;

use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds Framework's own pieces from master files (database/seeders/masters/):
 * skills, one project structure per project type, tools, the sensitive-data
 * setting layer, skill sets, package sets, and one blueprint per project type
 * built from them. Structures and skills come from the framework R package.
 * Idempotent: rows are matched by key, and a new version is added only when a
 * master changed.
 */
class BlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $mastersDir = database_path('seeders/masters');

        $this->seedSkills("$mastersDir/skills");
        $this->seedPieces('tool', $this->json("$mastersDir/tools.json"));
        $this->seedPieces('setting', $this->json("$mastersDir/settings.json"));
        $this->seedPieces('skill_set', array_map(fn (array $set) => $set + ['layer' => self::skillSetLayer($set)], $this->json("$mastersDir/skill_sets.json")));
        $this->seedPieces('package_set', array_map(fn (array $set) => $set + ['layer' => ['packages' => ['default_packages' => $set['packages']]]], $this->json("$mastersDir/package_sets.json")));
        $this->seedBlueprints($mastersDir);
    }

    private function json(string $path): array
    {
        return json_decode(File::get($path), true);
    }

    private function seedSkills(string $dir): void
    {
        foreach (File::files($dir) as $position => $file) {
            $content = File::get($file->getPathname());
            $frontmatter = Skill::frontmatter($content, 'content');

            $skill = Skill::updateOrCreate(
                ['user_id' => null, 'key' => $frontmatter['key']],
                [
                    'name' => $frontmatter['title'],
                    'description' => $frontmatter['description'],
                    'visibility' => 'public',
                    'position' => $position,
                ],
            );

            $skill->publishVersion(['content' => $content, 'files' => []]);
        }
    }

    /** System skills travel by reference: R installs its own copy. */
    private static function skillSetLayer(array $set): array
    {
        return array_filter([
            'skills' => array_fill_keys($set['skills'], ['package' => true]),
            'packages' => $set['packages'] ? ['default_packages' => $set['packages']] : null,
        ]);
    }

    private function seedPieces(string $kind, array $definitions): void
    {
        $position = 0;

        foreach ($definitions as $key => $definition) {
            $facet = Facet::updateOrCreate(['user_id' => null, 'key' => $key], [
                'kind' => $kind,
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'category' => $definition['category'] ?? null,
                'language' => $definition['language'] ?? null,
                'requires' => $definition['requires'] ?? null,
                'visibility' => 'public',
                'position' => $position++,
            ]);

            $facet->publishVersion(['layer' => $definition['layer']]);
        }
    }

    /**
     * One structure and one blueprint per project type. The blueprint stacks
     * the structure, Quarto when the structure renders with it, the skill
     * sets, and any single skills.
     */
    private function seedBlueprints(string $mastersDir): void
    {
        $definitions = $this->json("$mastersDir/blueprints.json");

        // Bare (no blueprint) leads: it's the default; the rest are opinionated offerings
        if (isset($definitions['bare'])) {
            $definitions = ['bare' => $definitions['bare']] + $definitions;
        }

        $latest = fn (string $key) => Facet::whereNull('user_id')->where('key', $key)->firstOrFail()->latestVersion;
        $setSkills = collect($this->json("$mastersDir/skill_sets.json"))->map(fn (array $set) => $set['skills']);
        $position = 0;

        foreach ($definitions as $key => $definition) {
            // A top-level null (bare's quarto) has nothing beneath it to remove
            $layer = ['type' => $key] + array_filter($definition['structure'] ?? [], fn ($value) => $value !== null);

            // R keys its sensitive-project behavior on this flag
            if ($key === 'project_sensitive') {
                $layer['security'] = ['sensitive' => true];
            }

            $agentsPath = "$mastersDir/agents/$key.md";
            if (File::exists($agentsPath)) {
                $layer['files'] = ['AGENTS.md' => ['content' => File::get($agentsPath), 'template' => true]];
            }

            $attributes = [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'visibility' => 'public',
                'position' => $position++,
            ];

            $structure = Facet::updateOrCreate(['user_id' => null, 'key' => $key], $attributes + ['kind' => 'structure']);
            $structureVersion = $structure->publishVersion(['layer' => $layer]);

            // The structure's skills become skill sets, plus any skill no set covers
            $skills = $definition['skills'] ?? [];
            $sets = $setSkills->filter(fn (array $members) => $skills && ! array_diff($members, $skills))->keys();
            $single = array_values(array_diff($skills, $sets->flatMap(fn ($set) => $setSkills[$set])->all()));

            // Figures come with every type that has the Framework skills
            if ($sets->contains('framework')) {
                $sets->push('ggplot2');
            }

            $pieces = collect(isset($layer['quarto']) ? ['quarto'] : [])->concat($sets)->map($latest);

            $blueprint = Blueprint::updateOrCreate(['user_id' => null, 'key' => $key], $attributes + ['is_system' => true]);
            $blueprint->publish(
                $structureVersion,
                $pieces->pluck('id')->all(),
                $single ? ['skills' => array_fill_keys($single, ['package' => true])] : [],
            );
        }
    }
}
