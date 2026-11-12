<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\User;
use App\Support\LayerSummary;
use App\Support\ProjectLayers;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

/**
 * The Connections tab: databases and storage buckets in the layer's
 * connections key, secrets as env(), and the variables they read in env.
 */
class ConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BlueprintSeeder::class);
    }

    private function connections(array $overrides = []): array
    {
        return $overrides + [
            'default_database' => 'study_db',
            'default_storage_bucket' => 'reports',
            'databases' => [[
                'name' => 'study_db', 'driver' => 'postgres',
                'host' => 'env("STUDY_DB_HOST")', 'port' => '5432', 'database' => 'env("STUDY_DB_NAME")', 'schema' => '',
                'user' => 'env("STUDY_DB_USER")', 'password' => 'env("STUDY_DB_PASSWORD")',
            ]],
            'storage_buckets' => [[
                'name' => 'reports', 'driver' => 's3',
                'bucket' => 'study-reports', 'region' => 'env("REPORTS_REGION", "us-east-1")', 'endpoint' => '', 'prefix' => '',
                'access_key_id' => 'env("REPORTS_ACCESS_KEY_ID")', 'secret_access_key' => 'env("REPORTS_SECRET_ACCESS_KEY")', 'session_token' => '',
            ]],
        ];
    }

    private function blueprintChoices(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Warehouse study', 'visibility' => 'private', 'language' => 'r', 'workflow' => 'quarto', 'notebook_format' => 'quarto',
            'structure' => 'framework/project', 'tools' => ['quarto' => true], 'package_sets' => [], 'skill_sets' => [], 'skills' => [],
            'settings' => ['sensitive' => false, 'ai' => true, 'git' => true],
            'connections' => $this->connections(),
        ];
    }

    private function projectChoices(string $language = 'r'): array
    {
        return [
            'blueprint_id' => Blueprint::whereNull('user_id')->where('key', 'project')->firstOrFail()->id,
            'language' => $language, 'workflow' => $language === 'python' ? 'jupyter' : 'quarto', 'customized' => true,
            'structure' => 'framework/project', 'tools' => $language === 'python' ? ['jupyter' => true, 'quarto' => true] : ['quarto' => true],
            'package_sets' => [], 'skill_sets' => [], 'skills' => [], 'excluded_skills' => [],
            'settings' => ['sensitive' => false, 'git' => true, 'hooks' => [], 'ai' => ['enabled' => true, 'assistants' => ['claude' => true]]],
            'connections' => $this->connections(),
        ];
    }

    public function test_the_blueprint_editor_saves_connections_and_reopens_on_them(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices())->assertSessionHasNoErrors();
        $version = Blueprint::where('user_id', $user->id)->sole()->latestVersion;

        $this->assertSame([
            'databases' => ['study_db' => [
                'driver' => 'postgres', 'host' => 'env("STUDY_DB_HOST")', 'port' => 5432, 'database' => 'env("STUDY_DB_NAME")',
                'user' => 'env("STUDY_DB_USER")', 'password' => 'env("STUDY_DB_PASSWORD")',
            ]],
            'storage_buckets' => ['reports' => [
                'driver' => 's3', 'bucket' => 'study-reports', 'region' => 'env("REPORTS_REGION", "us-east-1")',
                'access_key_id' => 'env("REPORTS_ACCESS_KEY_ID")', 'secret_access_key' => 'env("REPORTS_SECRET_ACCESS_KEY")',
            ]],
            'default_database' => 'study_db',
            'default_storage_bucket' => 'reports',
        ], $version->layer['connections']);

        $this->get("/blueprints/{$version->blueprint_id}/edit?tab=connections")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('section', 'connections')
            ->where('start.connections.default_database', 'study_db')
            ->where('start.connections.databases.0.name', 'study_db')
            ->where('start.connections.databases.0.port', '5432')
            ->where('start.connections.databases.0.password', 'env("STUDY_DB_PASSWORD")')
            ->where('start.connections.storage_buckets.0.bucket', 'study-reports')
            ->where('start.extra_yaml', fn ($yaml) => ! str_contains((string) $yaml, 'connections') && ! str_contains((string) $yaml, 'STUDY_DB')));

        // Saving the reopened choices again keeps the same layer
        $this->put("/blueprints/{$version->blueprint_id}", $this->blueprintChoices(['notes' => 'Again']))->assertSessionHasNoErrors();
        $this->assertSame($version->layer['connections'], Blueprint::where('user_id', $user->id)->sole()->latestVersion->layer['connections']);
    }

    public function test_every_variable_a_connection_reads_goes_into_env_without_a_secret_value(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices())->assertSessionHasNoErrors();

        $this->assertSame([
            'REPORTS_ACCESS_KEY_ID' => '', 'REPORTS_REGION' => 'us-east-1', 'REPORTS_SECRET_ACCESS_KEY' => '',
            'STUDY_DB_HOST' => '', 'STUDY_DB_NAME' => '', 'STUDY_DB_PASSWORD' => '', 'STUDY_DB_USER' => '',
        ], Blueprint::where('user_id', $user->id)->sole()->latestVersion->layer['env']);
    }

    public function test_a_literal_secret_is_refused_with_the_env_message(): void
    {
        $user = User::factory()->create();
        $connections = $this->connections();
        $connections['databases'][0]['password'] = 'hunter2';
        $connections['storage_buckets'][0]['secret_access_key'] = 'env("REPORTS_SECRET", "abc123")';

        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['connections' => $connections]))
            ->assertSessionHasErrors([
                'connections.databases.0.password' => 'Password looks like a credential. Use env("VARIABLE") and keep the value in the project\'s .env.',
                'connections.storage_buckets.0.secret_access_key',
            ]);
        $this->actingAs($user)->postJson('/blueprints/preview', ['workflow' => 'quarto'] + $this->blueprintChoices(['connections' => $connections]))
            ->assertStatus(422)->assertJsonPath('problem', fn ($problem) => str_contains($problem, 'env("VARIABLE")'));
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());
    }

    public function test_names_drivers_required_fields_and_defaults_are_checked(): void
    {
        $user = User::factory()->create();
        $bad = $this->connections(['default_database' => 'elsewhere']);
        $bad['databases'][] = ['name' => 'Study DB', 'driver' => 'postgres'];
        $bad['databases'][] = ['name' => 'framework', 'driver' => 'sqlite', 'database' => 'framework.db'];
        $bad['databases'][] = ['name' => 'cache', 'driver' => 'oracle'];
        $bad['databases'][] = ['name' => 'local', 'driver' => 'duckdb', 'database' => ''];
        $bad['databases'][] = ['name' => 'warehouse', 'driver' => 'mysql', 'host' => 'db', 'port' => 'eighty', 'database' => 'w', 'user' => 'u', 'password' => 'env("W")'];

        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['connections' => $bad]))->assertSessionHasErrors([
            'connections.default_database', 'connections.databases.1.name', 'connections.databases.2.name',
            'connections.databases.3.driver', 'connections.databases.4.database', 'connections.databases.5.port',
        ]);
    }

    public function test_drivers_follow_the_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['name' => 'Python study', 'language' => 'python', 'workflow' => 'jupyter', 'notebook_format' => 'jupyter', 'tools' => ['jupyter' => true, 'quarto' => true]]))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['name' => 'Stata study', 'language' => 'stata', 'workflow' => 'scripts', 'notebook_format' => null, 'tools' => []]))
            ->assertSessionHasErrors(['connections' => 'Stata projects have no database or storage bucket connections.']);
        // A Stata blueprint with no connections saves
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['name' => 'Stata study', 'language' => 'stata', 'workflow' => 'scripts', 'notebook_format' => null, 'tools' => [], 'connections' => ['databases' => [], 'storage_buckets' => []]]))
            ->assertSessionHasNoErrors();
        // Aliases the packages accept are stored by their main name
        $alias = $this->connections();
        $alias['databases'][0]['driver'] = 'postgresql';
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices(['name' => 'Alias study', 'connections' => $alias]))->assertSessionHasNoErrors();
        $this->assertSame('postgres', Blueprint::where('name', 'Alias study')->sole()->latestVersion->layer['connections']['databases']['study_db']['driver']);
    }

    public function test_new_project_and_edit_project_carry_connections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/projects/preview', $this->projectChoices())->assertOk()
            ->assertJsonPath('tree', fn ($tree) => collect($tree)->firstWhere('path', 'settings/connections.yml')['note'] === 'connections: study_db (postgres), reports (s3)'
                && str_contains(collect($tree)->firstWhere('path', '.env')['note'], 'STUDY_DB_PASSWORD'));
        $this->actingAs($user)->post('/projects', $this->projectChoices() + ['name' => 'Survey'])->assertSessionHasNoErrors();
        $project = $user->projects()->sole();

        $layer = collect(ProjectLayers::for($project)['layers'])->firstWhere('kind', 'project')['layer'];
        $this->assertSame('study_db', $layer['connections']['default_database']);
        $this->assertSame('env("STUDY_DB_PASSWORD")', $layer['connections']['databases']['study_db']['password']);
        $this->assertSame('', $layer['env']['STUDY_DB_PASSWORD']);
        $this->assertSame('study_db', $project->choices['connections']['databases'][0]['name']);

        // Edit project: the form opens on them, and a change resolves again
        $this->get("/projects/{$project->slug}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('editing.choices.connections.databases.0.name', 'study_db'));
        $choices = $this->projectChoices();
        $choices['connections']['databases'][0] = ['name' => 'local', 'driver' => 'duckdb', 'database' => 'data/study.duckdb'];
        $choices['connections']['default_database'] = 'local';
        $this->put("/projects/{$project->id}", $choices + ['name' => 'Survey', 'update_setup' => true])->assertSessionHasNoErrors();

        $layer = collect(ProjectLayers::for($project->fresh())['layers'])->firstWhere('kind', 'project')['layer'];
        $this->assertSame(['local' => ['driver' => 'duckdb', 'database' => 'data/study.duckdb']], $layer['connections']['databases']);
        $this->assertArrayNotHasKey('STUDY_DB_PASSWORD', $layer['env']);
    }

    public function test_new_project_offers_a_blueprints_connections_and_stata_refuses_them(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/blueprints', $this->blueprintChoices())->assertSessionHasNoErrors();
        $blueprint = Blueprint::where('user_id', $user->id)->sole();

        $this->get('/projects/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('blueprints', fn ($blueprints) => collect($blueprints)->firstWhere('id', $blueprint->id)['choices']['connections']['databases'][0]['name'] === 'study_db'));

        $this->post('/projects', array_replace($this->projectChoices('stata'), ['name' => 'Stata', 'workflow' => 'scripts', 'tools' => []]))
            ->assertSessionHasErrors('connections');
    }

    public function test_the_blueprint_summary_names_connections_and_their_file(): void
    {
        $sections = collect(LayerSummary::of([
            'connections' => ['default_database' => 'study_db', 'databases' => ['study_db' => ['driver' => 'postgres']], 'storage_buckets' => ['reports' => ['driver' => 's3']]],
            'env' => ['STUDY_DB_PASSWORD' => ''],
        ]))->pluck('items', 'label');

        $this->assertSame(['study_db (postgres)'], $sections['Databases']);
        $this->assertSame(['reports (s3)'], $sections['Storage buckets']);
        $this->assertSame(['study_db'], $sections['Default database']);
        $this->assertStringStartsWith('settings/connections.yml', $sections['Connections file'][0]);
        $this->assertSame(['STUDY_DB_PASSWORD'], $sections['.env variables']);
    }

    public function test_the_zip_has_the_connections_and_a_valueless_env_example(): void
    {
        config(['services.scaffolder.url' => 'http://scaffolder:8000', 'framework.client_url' => 'https://cloud.example.org']);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/projects', $this->projectChoices() + ['name' => 'Survey', 'destination' => 'download'])->assertSessionHasNoErrors();
        $project = $user->projects()->sole();

        $path = tempnam(sys_get_temp_dir(), 'fake-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('survey/settings/connections.yml', "connections:\n  databases:\n    study_db:\n      password: env(\"STUDY_DB_PASSWORD\")\n");
        $zip->addFromString('survey/.env', "# Environment for this project.\nPOSTGRES_PORT=5432\nSTUDY_DB_HOST=\nSTUDY_DB_PASSWORD=\nREPORTS_REGION=us-east-1\n");
        $zip->addFromString('survey/.gitignore', ".env\n");
        $zip->close();
        Http::fake(['scaffolder:8000/build' => Http::response(file_get_contents($path), 200, ['Content-Type' => 'application/zip'])]);
        unlink($path);

        $response = $this->get("/projects/{$project->id}/download")->assertOk();

        // The scaffolder gets the connections and their variables, with no secret values
        Http::assertSent(function (HttpRequest $request) {
            $layer = collect(json_decode($request->body(), true)['layers']['layers'])->firstWhere('kind', 'project')['layer'];

            return $layer['connections']['databases']['study_db']['password'] === 'env("STUDY_DB_PASSWORD")'
                && $layer['env']['STUDY_DB_PASSWORD'] === '';
        });
        $out = new ZipArchive;
        $out->open($response->baseResponse->getFile()->getPathname());
        $this->assertNotFalse($out->getFromName('survey/settings/connections.yml'));
        $example = $out->getFromName('survey/.env.example');
        $out->close();
        $this->assertStringContainsString("STUDY_DB_HOST=\nSTUDY_DB_PASSWORD=\n", $example);
        $this->assertStringContainsString("POSTGRES_PORT=\n", $example);
        $this->assertDoesNotMatchRegularExpression('/^[A-Z_]+=.+$/m', $example);
        $this->assertStringNotContainsString('FW_CLOUD_URL', $example);
    }
}
