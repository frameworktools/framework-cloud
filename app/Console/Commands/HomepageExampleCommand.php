<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Support\ArtifactPublisher;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Publish the homepage's worked example: a model fitted to the Palmer penguins
 * data, published twice so readers see a version history. The account and
 * project are created once and reused. Run it once per server, then set
 * FW_HOMEPAGE_EXAMPLE_URL to the printed link.
 */
class HomepageExampleCommand extends Command
{
    protected $signature = 'fw:homepage-example {--email=examples@framework.invalid : The example account}';

    protected $description = 'Publish the penguin study model shown on the homepage';

    public const SLUG = 'penguin-body-mass';

    public function handle(ArtifactPublisher $publisher): int
    {
        $user = User::firstOrCreate(['email' => $this->option('email')], ['name' => 'Framework examples', 'password' => Str::random(48)]);
        $user->forceFill(['can_publish' => true, 'handle' => $user->handle ?? 'examples', 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $project = Project::firstOrCreate(
            ['user_id' => $user->id, 'slug' => 'penguin-study'],
            ['name' => 'Penguin study', 'project_type' => 'project', 'language' => 'r', 'payload' => [],
                'description' => 'A worked example with the Palmer penguins data.'],
        );

        $document = Document::where('project_id', $project->id)->where('slug', self::SLUG)->first();
        $have = $document?->versions()->count() ?? 0;
        foreach ([1, 2] as $version) {
            if ($version <= $have) {
                continue;
            }
            $source = resource_path("examples/homepage/penguin-model-v{$version}.json");
            $copy = tempnam(sys_get_temp_dir(), 'fw-example');
            copy($source, $copy);
            $publisher->publish($project, [
                'file' => new UploadedFile($copy, 'model.json', 'application/json', null, true),
                'slug' => self::SLUG,
                'title' => 'Penguin body mass model',
                'description' => $version === 1
                    ? 'Linear model of body mass on flipper length. Palmer penguins data, 342 penguins with complete measurements.'
                    : 'Adds species to the model of body mass on flipper length. Palmer penguins data, 342 penguins with complete measurements.',
                'visibility' => 'public',
                'kind' => 'model',
            ]);
        }

        // The data, summary table, and figure from the same analysis, one version each.
        foreach ([
            ['penguins', 'penguins.csv', 'text/csv', 'data', 'Palmer penguins data', 'Measurements of 344 penguins from three islands in the Palmer Archipelago, Antarctica.'],
            ['mass-by-species', 'mass-by-species.csv', 'text/csv', 'table', 'Body mass by species', 'Number of penguins and mean body mass for each species.'],
            ['flipper-and-mass', 'flipper-and-mass.svg', 'image/svg+xml', 'image', 'Flipper length and body mass', 'Body mass against flipper length for 342 penguins, coloured by species.'],
        ] as [$slug, $file, $type, $kind, $title, $description]) {
            if (Document::where('project_id', $project->id)->where('slug', $slug)->exists()) {
                continue;
            }
            $copy = tempnam(sys_get_temp_dir(), 'fw-example');
            copy(resource_path("examples/homepage/$file"), $copy);
            $publisher->publish($project, [
                'file' => new UploadedFile($copy, $file, $type, null, true),
                'slug' => $slug, 'title' => $title, 'description' => $description,
                'visibility' => 'public', 'kind' => $kind,
            ]);
        }

        $document = Document::where('project_id', $project->id)->where('slug', self::SLUG)->firstOrFail();
        $this->info('Published '.$document->versions()->count().' versions.');
        $this->line('FW_HOMEPAGE_EXAMPLE_URL='.$document->shareUrl());

        return self::SUCCESS;
    }
}
