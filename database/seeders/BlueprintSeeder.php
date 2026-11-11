<?php

namespace Database\Seeders;

use App\Console\Commands\SyncPiecesCommand;
use App\Models\Blueprint;
use App\Models\Facet;
use App\Models\Skill;
use App\Models\SkillTag;
use App\Support\GitignoreTemplate;
use App\Support\SkillTags;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
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

        SkillTags::seed();
        $this->seedSkills("$mastersDir/skills");
        $this->seedPieces('tool', $this->fromR('features', self::TOOL_ORDER));
        $this->seedPieces('setting', $this->json("$mastersDir/settings.json"));
        $this->seedPieces('skill_set', $this->fromR('sets/skills'));
        $this->seedPieces('package_set', $this->fromR('sets/packages', self::PACKAGE_SET_ORDER));
        // Workflows key as workflow-<id>; the id stays in category, as the spec names it
        $this->seedPieces('workflow', collect($this->fromR('workflows', ['quarto', 'jupyter', 'scripts']))
            ->mapWithKeys(fn (array $w, string $id) => ["workflow-{$id}" => $w + ['category' => $id]])->all());
        $this->seedBlueprints($mastersDir);
    }

    /** Short names for Framework's structures and blueprints, as the catalogs list them */
    private const SHORT_NAMES = [
        'project' => 'Standard',
        'project_sensitive' => 'Privacy sensitive',
        'presentation' => 'Presentation',
        'course' => 'Course',
        'shiny' => 'Shiny app',
        'plumber' => 'Plumber API',
        'shiny_python' => 'Shiny for Python',
        'fastapi' => 'FastAPI app',
        'bare' => 'Bare',
    ];

    /**
     * What each system blueprint supports. Presentation and Course are built
     * on Quarto, so they need it; the rest take any workflow their languages
     * have. Stata has only Scripts only.
     */
    private const COMPATIBILITY = [
        'project' => ['languages' => ['r', 'python', 'stata'], 'workflows' => ['quarto', 'jupyter', 'scripts']],
        'project_sensitive' => ['languages' => ['r', 'python', 'stata'], 'workflows' => ['quarto', 'jupyter', 'scripts']],
        'presentation' => ['languages' => ['r', 'python'], 'workflows' => ['quarto'], 'requires_tools' => ['quarto']],
        'course' => ['languages' => ['r', 'python'], 'workflows' => ['quarto'], 'requires_tools' => ['quarto']],
        'shiny' => ['languages' => ['r'], 'workflows' => ['quarto', 'jupyter', 'scripts']],
        'plumber' => ['languages' => ['r'], 'workflows' => ['quarto', 'jupyter', 'scripts']],
        'shiny_python' => ['languages' => ['python'], 'workflows' => ['quarto', 'jupyter', 'scripts']],
        'fastapi' => ['languages' => ['python'], 'workflows' => ['quarto', 'jupyter', 'scripts']],
        'bare' => ['languages' => ['r', 'python', 'stata'], 'workflows' => ['quarto', 'jupyter', 'scripts']],
    ];

    /**
     * How the catalogs list Framework's package sets: the general sets for
     * each language from small to large, then sets for a field or a kind of
     * work, then the app sets. Any others follow.
     */
    private const PACKAGE_SET_ORDER = [
        'tidy-core', 'tidyverse-analysis', 'tidyverse-extended',
        'python-data-analysis', 'polars-python',
        'tidy-database', 'large-data-r', 'tables-r', 'regression-r', 'easystats-r', 'tidymodels', 'bayes-r',
        'causal-inference-r', 'survival-r', 'survey-r', 'spatial-r', 'text-r', 'tidy-omop',
        'python-database', 'python-viz', 'python-ml', 'python-bayes', 'python-spatial',
        'shiny-r', 'plumber-r', 'shiny-python', 'fastapi-python',
    ];

    /** How the builder lists Framework's tools; any others follow */
    private const TOOL_ORDER = ['quarto', 'quarto_manuscript', 'quarto_slides', 'jupyter', 'targets', 'renv', 'uv'];

    /**
     * Definitions copied from the framework R package (fw:sync-pieces), keyed
     * by id. A tool's own tools.<id> key is left out of the stored layer: the
     * cloud adds it when the spec's tools key is on (FW_SPEC_TOOLS), since
     * older clients reject it.
     *
     * @param  list<string>  $order
     * @return array<string, array<string, mixed>>
     */
    private function fromR(string $folder, array $order = []): array
    {
        $definitions = collect(File::files(SyncPiecesCommand::dir()."/{$folder}"))
            ->map(fn ($file) => $this->json($file->getPathname()))
            ->map(function (array $d) {
                if (isset($d['layer']['tools'])) {
                    unset($d['layer']['tools']);
                }

                return $d;
            })
            ->keyBy('id');

        return $definitions->sortBy(fn ($d, $id) => [array_search($id, $order, true) === false ? count($order) : array_search($id, $order, true), $id])->all();
    }

    private function json(string $path): array
    {
        return json_decode(File::get($path), true);
    }

    /**
     * Framework's skills: R's text from the framework R package, and for
     * the ones framework-py ships too, Python's own text as a variant
     * (masters/skills/python, copied at the commit in its lock.json). A
     * skill is for the languages that have its text.
     */
    /**
     * Skills written for one language only. Their SKILL.md is that language's
     * text, so they offer no R default.
     */
    private const SINGLE_LANGUAGE_SKILLS = ['matplotlib-exploring' => 'python', 'stata-graphs' => 'stata'];

    /** Skills that work the same in every language, such as the reports API one. */
    private const EVERY_LANGUAGE_SKILLS = ['framework-reports', 'analysis-writing'];

    private function seedSkills(string $dir): void
    {
        $registry = File::exists(dirname($dir).'/skill-bundles.json') ? $this->json(dirname($dir).'/skill-bundles.json') : [];
        $sources = [];
        foreach (File::files($dir) as $file) {
            $content = File::get($file->getPathname());
            $key = Skill::frontmatter($content, 'content')['key'];
            $sources[$key] = $content;
        }
        foreach ($registry as $key => $variants) {
            $default = $variants['r'] ?? reset($variants);
            $sources[$key] = base64_decode(collect($default['bundle'])->firstWhere('path', 'SKILL.md')['content']);
        }
        foreach ($sources as $key => $content) {
            $frontmatter = Skill::frontmatter($content, 'content');
            $texts = [];
            $files = [];
            foreach ($registry[$key] ?? [] as $variant => $snapshot) {
                $texts[$variant] = base64_decode(collect($snapshot['bundle'])->firstWhere('path', 'SKILL.md')['content']);
                $files[$variant] = array_values(array_filter($snapshot['bundle'], fn ($f) => $f['path'] !== 'SKILL.md'));
            }
            if (! $texts && File::exists($path = "{$dir}/python/{$key}.md")) {
                $texts['python'] = File::get($path);
            }
            $skill = Skill::updateOrCreate(['user_id' => null, 'key' => $key], [
                'name' => $frontmatter['title'], 'description' => $frontmatter['description'], 'summary' => $frontmatter['summary'],
                'language' => match (true) {
                    isset($registry[$key]) => array_keys($registry[$key]),
                    isset(self::SINGLE_LANGUAGE_SKILLS[$key]) => [self::SINGLE_LANGUAGE_SKILLS[$key]],
                    in_array($key, self::EVERY_LANGUAGE_SKILLS, true) => ['r', 'python', 'stata'],
                    (bool) $texts => array_keys($texts + ['r' => $content]),
                    default => ['r'],
                }, 'visibility' => 'public',
            ]);
            if ($skill->wasRecentlyCreated) {
                $skill->tags()->sync(SkillTag::whereIn('key', SkillTags::SKILLS[$key] ?? [])->pluck('id'));
            }
            unset($texts['r']);
            $skill->publishVersion(['content' => $content, 'variants' => $texts ?: null, 'files' => [], 'bundle_files' => $files ?: null]);
        }
    }

    private function seedPieces(string $kind, array $definitions): void
    {
        $position = 0;

        foreach ($definitions as $key => $definition) {
            $facet = Facet::updateOrCreate(['user_id' => null, 'key' => $key], [
                'kind' => $kind,
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'requirements' => $definition['requirements'] ?? null,
                'category' => $definition['category'] ?? null,
                'language' => $definition['language'] ?? null,
                'requires' => $definition['requires'] ?? null,
                'conflicts' => ($definition['conflicts'] ?? []) ?: null,
                'visibility' => 'public',
                'position' => $position++,
            ]);

            $facet->publishVersion(['layer' => $definition['layer'], 'language_layers' => ($definition['language_layers'] ?? []) ?: null]);
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

        // Bare is the least opinionated, so it comes last
        if (isset($definitions['bare'])) {
            $definitions = Arr::except($definitions, 'bare') + ['bare' => $definitions['bare']];
        }

        $latest = fn (string $key) => Facet::whereNull('user_id')->where('key', $key)->firstOrFail()->latestVersion;
        $setSkills = collect($this->fromR('sets/skills'))->map(fn (array $set) => array_keys($set['layer']['skills'] ?? []));
        $position = 0;

        foreach ($definitions as $key => $definition) {
            // A top-level null (bare's quarto) has nothing beneath it to remove
            $layer = ['type' => $key] + array_filter($definition['structure'] ?? [], fn ($value) => $value !== null);
            $layer['git']['gitignore_content'] ??= GitignoreTemplate::for($key);

            // R keys its sensitive-project behavior on this flag
            if ($key === 'project_sensitive') {
                $layer['security'] = ['sensitive' => true];
            }

            // No AGENTS.md: the packages generate it from the project's
            // settings and skills. A structure's own AGENTS.md would replace it.

            $attributes = [
                'name' => self::SHORT_NAMES[$key] ?? $definition['name'],
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

            // uv for Python projects (left out of R and Stata ones). The
            // workflow brings Quarto or Jupyter, so the blueprint doesn't.
            // App blueprints bring the package set their starter files use.
            $pieces = collect(['uv'])->concat($sets)->concat($definition['package_sets'] ?? [])->map($latest);

            $blueprint = Blueprint::updateOrCreate(['user_id' => null, 'key' => $key], $attributes + ['is_system' => true]);
            $blueprint->publish(
                $structureVersion,
                $pieces->pluck('id')->all(),
                $single ? ['skills' => array_fill_keys($single, ['package' => true])] : [],
                null,
                self::COMPATIBILITY[$key] ?? [],
            );
        }
    }
}
