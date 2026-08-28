<?php

namespace Database\Seeders;

use App\Models\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the system blueprints from master files extracted from the framework
 * R package (database/seeders/masters/). Idempotent: updates content in place
 * by key, so re-running refreshes masters without duplicating rows.
 */
class BlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $mastersDir = database_path('seeders/masters');
        $definitions = json_decode(File::get("$mastersDir/blueprints.json"), true);

        // Bare (no blueprint) leads: it's the default; the rest are opinionated offerings
        if (isset($definitions['bare'])) {
            $definitions = ['bare' => $definitions['bare']] + $definitions;
        }

        $position = 0;

        foreach ($definitions as $key => $definition) {
            $agentsPath = "$mastersDir/agents/$key.md";
            $agentsMd = File::exists($agentsPath) ? File::get($agentsPath) : null;

            $skills = [];
            foreach ($definition['skills'] ?? [] as $skill) {
                $skillPath = "$mastersDir/skills/$skill.md";
                if (File::exists($skillPath)) {
                    $skills[$skill] = File::get($skillPath);
                }
            }

            Blueprint::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'structure' => $definition['structure'] ?? [],
                    'agents_md' => $agentsMd,
                    'skills' => $skills,
                    'is_system' => true,
                    'position' => $position++,
                ],
            );
        }
    }
}
