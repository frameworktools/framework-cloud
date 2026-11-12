<?php

namespace Database\Seeders;

use App\Console\Commands\HomepageExampleCommand;
use App\Http\Controllers\App\ProjectsController;
use App\Models\Blueprint;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Facet;
use App\Models\Project;
use App\Models\User;
use App\Support\BlueprintInput;
use App\Support\Catalog;
use App\Support\Library;
use App\Support\ProjectData;
use App\Support\ProjectDefaults;
use App\Support\Resolver;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The demo account the homepage's Framework Cloud screenshots show: Ada
 * Lovelace's penguin study, with a personal blueprint, a data registry, the
 * published model, data, table, and figure, and a collection of them.
 * Local only, and never part of ProductionSeeder. Each run starts the
 * account over, so the screenshots are the same every time.
 *
 *   php artisan db:seed --class=Database\\Seeders\\LandingDemoSeeder
 *
 * scripts/landing-screenshots runs it, then captures the pages.
 */
class LandingDemoSeeder extends Seeder
{
    public const EMAIL = 'ada@example.org';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The landing demo account is for local screenshots only.');
        }

        $this->call(BlueprintSeeder::class);

        User::where('email', self::EMAIL)->first()?->delete();
        $user = User::create(['name' => 'Ada Lovelace', 'email' => self::EMAIL, 'password' => null]);
        $user->forceFill([
            'handle' => 'ada',
            'is_admin' => false,
            'can_publish' => true,
            'email_verified_at' => now(),
            'setup_completed_at' => now(),
        ])->save();

        $blueprint = $this->blueprint($user);
        ProjectDefaults::setLanguage($user, 'r');
        ProjectDefaults::setBlueprint($user, 'r', $blueprint);
        ProjectDefaults::setProjectsDir($user, '~/research');

        $project = $this->project($user, $blueprint, 'Penguin study', 'penguin-study', 'Body mass and flipper length in the Palmer penguins.', 'r', [
            'inputs.raw.penguins' => ['path' => 'inputs/raw/penguins.csv', 'type' => 'csv', 'locked' => true, 'description' => 'Palmer penguins measurements, 344 penguins'],
            'inputs.raw.islands' => ['path' => 'inputs/raw/islands.csv', 'type' => 'csv', 'locked' => true, 'description' => 'Island coordinates and survey years'],
            'inputs.intermediate.penguins' => ['path' => 'inputs/intermediate/penguins.rds', 'type' => 'rds', 'description' => 'Complete cases with species as a factor'],
            'outputs.tables.mass_by_species' => ['path' => 'outputs/tables/mass_by_species.csv', 'type' => 'csv', 'description' => 'Mean body mass for each species'],
        ]);
        // A computer has pulled the project and sent its settings back
        $project->issuePullToken('ada-laptop');
        $project->forceFill([
            'spec' => json_encode(['name' => 'penguin-study', 'language' => 'r', 'data' => array_keys(ProjectData::for($project)['entries'])]),
            'spec_received_at' => now()->subHours(2),
        ])->save();

        $system = Blueprint::where('is_system', true)->where('key', 'project')->firstOrFail();
        $this->project($user, $system, 'Seabird counts', 'seabird-counts', 'Colony counts from the coastal seabird survey.', 'python', [
            'inputs.raw.counts' => ['path' => 'inputs/raw/counts.csv', 'type' => 'csv', 'locked' => true],
        ]);

        // The model (two versions), data, table, and figure, published into penguin-study
        Artisan::call('fw:homepage-example', ['--email' => self::EMAIL]);

        $documents = Document::where('project_id', $project->id)->get()->keyBy('slug');
        $collection = Collection::create([
            'project_id' => $project->id,
            'title' => 'Penguin study results',
            'description' => 'The data, summary table, model, and figure from the penguin study.',
            'key' => Str::lower(Str::random(24)),
            'slug' => 'penguin-study-results-'.Str::lower(Str::random(8)),
            'visibility' => 'public',
        ]);
        foreach (['penguins', 'mass-by-species', HomepageExampleCommand::SLUG, 'flipper-and-mass'] as $position => $slug) {
            $collection->items()->create(['document_id' => $documents[$slug]->id, 'position' => $position, 'share_unlisted' => false]);
        }

        // Ada's copy of the Standard structure, open in the structure editor
        $structure = Facet::where('kind', 'structure')->where('key', 'project')->whereNull('user_id')->firstOrFail();
        $copy = Facet::create([
            'user_id' => $user->id,
            'key' => Library::uniqueKey(Facet::query(), 'Field study', $user->id),
            'kind' => 'structure',
            'name' => 'Field study',
            'description' => 'The standard folders, copied for field data projects.',
            'requirements' => $structure->requirements,
            'category' => $structure->category,
            'language' => $structure->language,
            'visibility' => 'private',
            'forked_from_id' => $structure->latestVersion->id,
        ]);
        $copy->publishVersion(['layer' => $structure->latestVersion->layer, 'language_layers' => $structure->latestVersion->language_layers, 'manual_packages' => $structure->latestVersion->manual_packages, 'notes' => "Copied from {$structure->handle} v{$structure->latestVersion->version}"]);

        $this->command?->info('Landing demo account ready: '.self::EMAIL);
    }

    /**
     * Ada's own blueprint, saved as the blueprint editor saves one: Framework's
     * Standard blueprint with Claude, data safety hooks, Quarto settings, and
     * a database and storage bucket whose secrets stay in .env.
     */
    private function blueprint(User $user): Blueprint
    {
        $source = Blueprint::with(Library::BLUEPRINT_WITH)->where('is_system', true)->where('key', 'project')->firstOrFail();
        $start = BlueprintInput::start($source->latestVersion, Catalog::choices($source->latestVersion, $user), $user);

        $input = array_replace($start, [
            'name' => 'Penguin lab',
            'description' => 'Field data projects with a study database and a shared storage bucket.',
            'visibility' => 'private',
            'language' => 'r',
            'workflow' => 'quarto',
            'notebook_format' => 'quarto',
            'agents' => ['claude'],
            'ide' => 'rstudio',
            'publish_destination' => 'cloud',
            'scaffold' => array_replace($start['scaffold'], ['set_theme_on_scaffold' => true, 'ggplot_theme' => 'theme_minimal', 'seed' => '2026']),
            'quarto' => array_replace_recursive($start['quarto'], ['html' => ['theme' => 'cosmo', 'code_fold' => true, 'code_tools' => true]]),
            'settings' => ['ai' => true, 'git' => true, 'hooks' => ProjectDefaults::HOOKS],
            // The skills the editor adds for Git and Quarto, as saving there would keep them
            'skills' => ['framework/git-workflow', 'framework/quarto-documents'],
            'package_overrides' => $start['package_overrides'] + ['broom' => true, 'janitor' => true, 'gt' => true],
            'package_auto_attach' => $start['package_auto_attach'] + ['broom' => true, 'janitor' => false, 'gt' => false],
            'package_definitions' => ['palmerpenguins' => ['source' => 'cran', 'auto_attach' => false]],
            'connections' => [
                'default_database' => 'study_db',
                'default_storage_bucket' => 'field_data',
                'databases' => [[
                    'name' => 'study_db', 'driver' => 'postgres',
                    'host' => 'env("STUDY_DB_HOST")', 'port' => '5432', 'database' => 'env("STUDY_DB_NAME")',
                    'schema' => 'public', 'user' => 'env("STUDY_DB_USER")', 'password' => 'env("STUDY_DB_PASSWORD")',
                ]],
                'storage_buckets' => [[
                    'name' => 'field_data', 'driver' => 's3',
                    'bucket' => 'penguin-field-data', 'region' => 'us-east-1', 'prefix' => 'surveys/',
                    'access_key_id' => 'env("FIELD_DATA_KEY_ID")', 'secret_access_key' => 'env("FIELD_DATA_SECRET")',
                ]],
                'variables' => [['name' => 'SURVEY_SEASON', 'value' => '2026']],
            ],
            'notes' => 'Adds the study database and field data bucket.',
        ]);
        unset($input['languages'], $input['workflows'], $input['default_workflow']);

        $request = Request::create('/blueprints', 'POST', $input);
        $request->setUserResolver(fn () => $user);
        [$meta, $base, $facetVersionIds, $layer, $notes, $compatibility] = BlueprintInput::validated($request);

        return DB::transaction(function () use ($user, $meta, $base, $facetVersionIds, $layer, $notes, $compatibility) {
            $blueprint = Blueprint::create($meta + [
                'user_id' => $user->id,
                'key' => Library::uniqueKey(Blueprint::query(), $meta['name'], $user->id),
                'is_system' => false,
            ]);
            $blueprint->publish($base, $facetVersionIds, $layer, $notes, $compatibility);

            return $blueprint;
        });
    }

    /** A project made from a blueprint, as New project makes one, with its data registry. */
    private function project(User $user, Blueprint $blueprint, string $name, string $slug, string $description, string $language, array $data): Project
    {
        $validated = ['name' => $name, 'blueprint_id' => $blueprint->id, 'language' => $language, 'customized' => false, 'data_entries' => $data];
        $resolver = new Resolver($user);
        $resolved = $resolver->resolve(ProjectsController::choicesFrom($validated, $blueprint));

        return $resolver->createProject($name, $resolved, $description, [
            'slug' => $slug,
            'choices' => ProjectsController::storedChoices($validated),
        ]);
    }
}
