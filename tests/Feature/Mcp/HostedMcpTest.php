<?php

namespace Tests\Feature\Mcp;

use App\Http\Controllers\OAuth\RegisterMcpClientController;
use App\Models\Blueprint;
use App\Models\Document;
use App\Models\McpApprovedApp;
use App\Models\McpConnection;
use App\Models\McpToolCall;
use App\Models\Project;
use App\Models\Report;
use App\Models\Skill;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\ArtifactPublisher;
use Database\Seeders\BlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The hosted MCP server end to end: an AI app registers, the owner approves it
 * at an access level, the app trades its code for a token, and its tool calls
 * run through /api/v1 with the connection's own token.
 */
class HostedMcpTest extends TestCase
{
    use RefreshDatabase;

    private static ?array $keys = null;

    private ?string $lastRefreshToken = null;

    private ?string $lastClientId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (self::$keys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$keys = [$private, openssl_pkey_get_details($key)['key']];
        }

        config(['passport.private_key' => self::$keys[0], 'passport.public_key' => self::$keys[1]]);
    }

    private function owner(): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'can_publish' => true]);
        Project::factory()->for($user)->create(['slug' => 'penguin-study', 'name' => 'Penguin study']);

        return $user;
    }

    private function figure(User $user): Document
    {
        return app(ArtifactPublisher::class)->publish($user->projects()->first(), ['slug' => 'mass-model', 'visibility' => 'private',
            'file' => UploadedFile::fake()->createWithContent('model.json', '{"estimate": 4207.1}')])->document;
    }

    /** Register a client, approve it as the owner, and return its access token. */
    private function connect(User $user, string $access, ?string $clientId = null, string $redirect = 'http://localhost:33418/callback'): string
    {
        $clientId ??= $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [$redirect],
        ])->assertCreated()->json('client_id');

        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state-1',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('OAuth/Authorize')->where('client.name', 'Claude'));

        $approved = $this->post('/oauth/authorize', ['auth_token' => session('authToken'), 'access' => $access])->assertRedirect();

        parse_str((string) parse_url($approved->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('state-1', $query['state']);

        $issued = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ])->assertOk();
        $token = $issued->json('access_token');
        $this->lastRefreshToken = $issued->json('refresh_token');
        $this->lastClientId = $clientId;

        // Not logout(): Passport's controller keeps the guard it was built with across a test's requests
        app('auth')->forgetGuards();

        return $token;
    }

    private function mcp(string $token, string $method, array $params = []): array
    {
        app('auth')->forgetGuards();

        return $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])
            ->assertOk()
            ->json();
    }

    private function tool(string $token, string $tool, array $arguments = []): array
    {
        $result = $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments]);

        $this->assertArrayHasKey('result', $result, json_encode($result));
        $text = $result['result']['content'][0]['text'] ?? '';

        return ['error' => $result['result']['isError'] ?? false, 'body' => json_decode($text, true) ?? $text];
    }

    private function toolNames(string $token): array
    {
        return array_column($this->mcp($token, 'tools/list')['result']['tools'], 'name');
    }

    public function test_discovery_metadata_points_at_the_oauth_endpoints(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('authorization_servers.0', url('/'));

        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_an_unauthenticated_request_is_told_where_to_sign_in(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])->assertUnauthorized();

        $this->assertStringContainsString('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"', $response->headers->get('WWW-Authenticate'));
    }

    public function test_a_client_without_a_json_accept_header_still_gets_a_401(): void
    {
        $this->post('/mcp', [])->assertUnauthorized()->assertHeader('WWW-Authenticate');
    }

    public static function redirects(): array
    {
        return [
            'loopback with a port' => ['http://localhost:54321/callback', true],
            'loopback address' => ['http://127.0.0.1:8080/cb', true],
            'claude.ai' => ['https://claude.ai/api/mcp/auth_callback', true],
            'chatgpt.com' => ['https://chatgpt.com/connector_platform_oauth_redirect', true],
            'http claude.ai' => ['http://claude.ai/callback', false],
            'lookalike host' => ['https://claude.ai.example.com/callback', false],
            'userinfo trick' => ['https://claude.ai@example.com/callback', false],
            'other host' => ['https://example.com/callback', false],
            'https loopback lookalike' => ['http://localhost.example.com/cb', false],
            'custom scheme' => ['cursor://callback', false],
        ];
    }

    #[DataProvider('redirects')]
    public function test_registration_allows_only_known_redirects(string $uri, bool $allowed): void
    {
        $this->assertSame($allowed, RegisterMcpClientController::allowedRedirect($uri));
    }

    public function test_registration_refuses_an_unknown_redirect(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Evil', 'redirect_uris' => ['https://example.com/cb']])
            ->assertUnprocessable();
    }

    public function test_a_signed_out_visitor_is_sent_to_sign_in_first(): void
    {
        $clientId = $this->postJson('/oauth/register', ['redirect_uris' => ['http://localhost:1/cb']])->json('client_id');

        $this->get('/oauth/authorize?'.http_build_query(['client_id' => $clientId, 'redirect_uri' => 'http://localhost:1/cb',
            'response_type' => 'code', 'scope' => 'mcp:use', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256']))
            ->assertRedirect(route('login'));
    }

    public function test_a_full_connection_reads_artifacts_and_writes_reports(): void
    {
        $user = $this->owner();
        $this->figure($user);
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $names = $this->toolNames($token);
        $this->assertContains('projects_list', $names);
        $this->assertContains('reports_create', $names);
        $this->assertContains('reports_publish', $names);

        $projects = $this->tool($token, 'projects_list');
        $this->assertFalse($projects['error']);
        $this->assertSame('penguin-study', $projects['body']['projects'][0]['slug']);

        $artifact = $this->tool($token, 'artifacts_show', ['project' => 'penguin-study', 'artifact' => 'mass-model']);
        $this->assertFalse($artifact['error'], json_encode($artifact));
        $this->assertSame('{"estimate": 4207.1}', $artifact['body']['content']);

        $draft = $this->tool($token, 'reports_create', ['slug' => 'penguin-study', 'title' => 'Body mass',
            'blocks' => [['type' => 'text', 'text' => '## Results'], ['type' => 'artifact', 'artifact' => 'mass-model']]]);
        $this->assertFalse($draft['error'], json_encode($draft));
        $this->assertSame('private', $draft['body']['visibility']);
        $this->assertNull($draft['body']['latest_version']);

        $this->assertSame(1, Report::count());
    }

    public function test_a_read_only_connection_sees_only_reads(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_READ);

        $names = $this->toolNames($token);
        $this->assertContains('reports_list', $names);
        $this->assertNotContains('reports_create', $names);
        $this->assertNotContains('reports_update', $names);
        $this->assertNotContains('reports_publish', $names);
        $this->assertNotContains('artifacts_publish', $names);
        foreach (['projects_create', 'projects_update', 'skills_create', 'skills_update', 'projects_delete', 'reports_delete', 'skills_delete', 'blueprints_create', 'blueprints_update'] as $write) {
            $this->assertNotContains($write, $names);
        }
        $this->assertContains('skills_show', $names);
        foreach (['blueprints_options', 'blueprints_show', 'blueprints_preview'] as $read) {
            $this->assertContains($read, $names);
        }

        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'reports_create', 'arguments' => ['slug' => 'penguin-study', 'title' => 'X']]])
            ->assertJsonPath('error.message', 'Tool [reports_create] not found.');
        $this->assertSame(0, Report::count());
    }

    public function test_a_full_connection_publishes_a_report(): void
    {
        $user = $this->owner();
        $this->figure($user);
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $report = $this->tool($token, 'reports_create', ['slug' => 'penguin-study', 'title' => 'Body mass', 'markdown' => '## Results']);
        $published = $this->tool($token, 'reports_publish', ['slug' => 'penguin-study', 'report' => $report['body']['slug']]);

        $this->assertFalse($published['error'], json_encode($published));
        $this->assertSame(1, $published['body']['latest_version']);
    }

    public function test_dry_run_sends_nothing(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $preview = $this->tool($token, 'reports_create', ['slug' => 'penguin-study', 'title' => 'Body mass', 'dry_run' => true]);

        $this->assertSame('POST', $preview['body']['method']);
        $this->assertSame('/api/v1/projects/penguin-study/reports', $preview['body']['path']);
        $this->assertSame(0, Report::count());
    }

    public function test_an_app_cannot_reach_another_users_projects(): void
    {
        $this->owner();
        $token = $this->connect(User::factory()->create(['email_verified_at' => now()]), McpConnection::ACCESS_FULL);

        $result = $this->tool($token, 'reports_list', ['slug' => 'penguin-study']);

        $this->assertTrue($result['error']);
        $this->assertSame(404, $result['body']['error']['status']);
    }

    public function test_setup_shows_the_plugin_on_framework_pub_or_when_turned_on(): void
    {
        config(['framework.agent_plugin' => null]);
        $this->actingAs($this->owner());
        $this->get('/agents')->assertInertia(fn (AssertableInertia $page) => $page->where('plugin', false));

        config(['framework.agent_plugin' => true]);
        $this->get('/agents')->assertInertia(fn (AssertableInertia $page) => $page->where('plugin', true));
    }

    public function test_disconnecting_cuts_the_app_off(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_READ);
        $connection = McpConnection::sole();

        $this->actingAs($user)->get('/tokens')->assertInertia(fn (AssertableInertia $page) => $page->has('tokens', 0));
        $this->get('/agents/connections')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agents/Connections')->has('connections', 1)->where('connections.0.name', 'Claude'));

        $this->delete("/agents/{$connection->id}")->assertRedirect();
        // It stays in the connection history, marked as ended
        $this->get('/agents')->assertInertia(fn (AssertableInertia $page) => $page->where('active', false));
        $this->get('/agents/connections')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('connections.0.status', 'Disconnected')->where('connections.0.active', false));
        auth()->guard('web')->logout();
        app('auth')->forgetGuards();

        $this->assertNotNull($connection->fresh()->revoked_at);
        $this->assertSame(0, PersonalAccessToken::count());
        $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    public function test_approving_the_same_app_again_replaces_its_connection(): void
    {
        $user = $this->owner();
        $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['http://localhost:33418/callback']])->json('client_id');
        $first = $this->connect($user, McpConnection::ACCESS_READ, $clientId);
        $second = $this->connect($user, McpConnection::ACCESS_FULL, $clientId);

        $this->assertSame(McpConnection::ACCESS_FULL, McpConnection::active()->sole()->access);
        $this->assertSame(1, PersonalAccessToken::count());
        $this->withToken($first)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
        $this->assertContains('reports_publish', $this->toolNames($second));
    }

    public function test_the_artifacts_api_lists_and_reads_published_results(): void
    {
        $user = $this->owner();
        $this->figure($user);
        $token = $user->createToken('script')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/projects/penguin-study/artifacts')->assertOk()
            ->assertJsonPath('artifacts.0.slug', 'mass-model')
            ->assertJsonPath('artifacts.0.latest_version', 1);

        $this->withToken($token)->getJson('/api/v1/projects/penguin-study/artifacts/mass-model')->assertOk()
            ->assertJsonPath('content', '{"estimate": 4207.1}')
            ->assertJsonPath('versions.0.number', 1);

        $this->withToken($token)->getJson('/api/v1/projects/penguin-study/artifacts/missing')->assertNotFound();
        app('auth')->forgetGuards();
        $other = User::factory()->create()->createToken('other')->plainTextToken;
        $this->withToken($other)->getJson('/api/v1/projects/penguin-study/artifacts')->assertNotFound();
    }

    public function test_deleting_the_account_removes_its_oauth_tokens(): void
    {
        $user = $this->owner();
        $this->connect($user, McpConnection::ACCESS_READ);

        app(AccountDeletion::class)->delete($user);

        $this->assertSame(0, McpConnection::count());
        $this->assertSame(0, DB::table('oauth_access_tokens')->count());
        $this->assertSame(0, DB::table('oauth_refresh_tokens')->count());
    }

    public function test_the_nav_names_the_agents_page_by_whether_one_is_connected(): void
    {
        $user = $this->owner();
        $label = fn () => collect($this->actingAs($user)->get('/agents')->viewData('page')['props']['nav'])->firstWhere('href', '/agents')['label'];

        $this->assertSame('Connect your agent', $label());

        $this->connect($user, McpConnection::ACCESS_READ);
        $this->assertSame('Connected agents', $label());
    }

    public function test_history_records_each_call_without_its_contents(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $this->tool($token, 'reports_list', ['slug' => 'penguin-study']);
        $this->tool($token, 'reports_create', ['slug' => 'penguin-study', 'title' => 'Secret title', 'dry_run' => true]);
        $this->tool($token, 'reports_list', ['slug' => 'missing']);

        $this->assertSame(['ok', 'dry_run', 'error'], McpToolCall::orderBy('id')->pluck('outcome')->all());
        $this->assertSame('penguin-study', McpToolCall::first()->target);
        $this->assertStringNotContainsString('Secret title', McpToolCall::all()->toJson());

        $this->actingAs($user)->get('/agents/history')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agents/History')->has('calls.data', 3)
            ->where('calls.data.2.tool', 'List reports')->where('calls.data.2.agent', 'Claude'));

        $this->actingAs(User::factory()->create())->get('/agents/history')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('calls.data', 0));
    }

    public function test_an_agent_publishes_saved_files_as_artifacts(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $notebook = $this->tool($token, 'artifacts_publish', ['project' => 'penguin-study', 'filename' => 'analysis.html',
            'content' => '<html><body><h1>Penguins</h1></body></html>', 'title' => 'Penguin analysis']);
        $this->assertFalse($notebook['error'], json_encode($notebook));
        $this->assertSame('analysis', $notebook['body']['slug']);
        $this->assertSame('html', $notebook['body']['kind']);
        $this->assertSame('private', $notebook['body']['visibility']);
        $this->assertSame(1, $notebook['body']['version']);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $figure = $this->tool($token, 'artifacts_publish', ['project' => 'penguin-study', 'filename' => 'mass.png',
            'content_base64' => base64_encode($png), 'artifact' => 'flipper-and-mass']);
        $this->assertFalse($figure['error'], json_encode($figure));
        $this->assertSame('flipper-and-mass', $figure['body']['slug']);
        $this->assertSame('image', $figure['body']['kind']);

        $again = $this->tool($token, 'artifacts_publish', ['project' => 'penguin-study', 'filename' => 'analysis.html', 'content' => '<html>v2</html>']);
        $this->assertSame(2, $again['body']['version']);

        $version = Document::where('slug', 'analysis')->sole()->versions()->first();
        $this->assertSame(['published_by' => 'agent', 'agent' => 'Claude'], array_intersect_key($version->provenance, array_flip(['published_by', 'agent'])));

        $shown = $this->tool($token, 'artifacts_show', ['project' => 'penguin-study', 'artifact' => 'analysis']);
        $this->assertSame('Claude', $shown['body']['versions'][0]['published_by']);
    }

    public function test_an_agent_publishes_a_rendered_site_from_a_zip(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);
        $zip = tempnam(sys_get_temp_dir(), 'site');
        $archive = new \ZipArchive;
        $archive->open($zip, \ZipArchive::OVERWRITE);
        $archive->addFromString('index.html', '<html><link rel="stylesheet" href="site_files/style.css">Report</html>');
        $archive->addFromString('site_files/style.css', 'body { color: black; }');
        $archive->close();

        $site = $this->tool($token, 'artifacts_publish', ['project' => 'penguin-study', 'filename' => 'report.zip',
            'content_base64' => base64_encode(file_get_contents($zip)), 'entrypoint' => 'index.html', 'artifact' => 'quarto-report']);
        @unlink($zip);

        $this->assertFalse($site['error'], json_encode($site));
        $this->assertSame('quarto-report', $site['body']['slug']);
        $this->assertSame('index.html', Document::where('slug', 'quarto-report')->sole()->latestVersion()->entrypoint);
    }

    public function test_publishing_checks_input_and_access(): void
    {
        $user = $this->owner();
        $full = $this->connect($user, McpConnection::ACCESS_FULL);

        $bad = $this->tool($full, 'artifacts_publish', ['project' => 'penguin-study', 'filename' => 'x.png', 'content_base64' => '***']);
        $this->assertTrue($bad['error']);
        $this->assertSame(422, $bad['body']['error']['status']);

        $traversal = $this->tool($full, 'artifacts_publish', ['project' => 'penguin-study', 'filename' => '../x.html', 'content' => 'x']);
        $this->assertSame(422, $traversal['body']['error']['status']);

        $preview = $this->tool($full, 'artifacts_publish', ['project' => 'penguin-study', 'filename' => 'big.html', 'content' => str_repeat('a', 5000), 'dry_run' => true]);
        $this->assertSame('(5,000 characters)', $preview['body']['body']['content']);
        $this->assertSame(0, Document::count());
    }

    public function test_a_script_token_publishes_through_the_api(): void
    {
        $user = $this->owner();

        $this->withToken($user->createToken('script')->plainTextToken)
            ->postJson('/api/v1/projects/penguin-study/artifacts', ['filename' => 'table.csv', 'content' => "species,mass\nAdelie,3700\n", 'visibility' => 'unlisted'])
            ->assertCreated()->assertJsonPath('kind', 'table')->assertJsonPath('visibility', 'unlisted');
    }

    public function test_an_artifact_can_be_published_without_a_project(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $loose = $this->tool($token, 'artifacts_publish', ['filename' => 'notes.md', 'content' => '# Notes']);
        $this->assertFalse($loose['error'], json_encode($loose));
        $this->assertNull($loose['body']['project']);
        $this->assertTrue(Document::where('slug', 'notes')->sole()->project->is_home);

        // The home is not a project anyone lists
        $this->assertSame(['penguin-study'], array_column($this->tool($token, 'projects_list')['body']['projects'], 'slug'));
        $this->assertSame('notes', $this->tool($token, 'artifacts_show', ['artifact' => 'notes'])['body']['slug']);
        $this->assertSame(['notes'], array_column($this->tool($token, 'artifacts_list')['body']['artifacts'], 'slug'));

        app('auth')->forgetGuards();
        $this->actingAs($user)->get('/projects')->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 1));
        $this->get('/published')->assertInertia(fn (AssertableInertia $page) => $page->where('documents.0.project.name', 'No project'));
    }

    public function test_an_upload_link_publishes_a_large_file_once(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $link = $this->tool($token, 'artifacts_upload', ['filename' => 'big-notebook.html', 'artifact' => 'quarto-notebook', 'title' => 'Quarto notebook']);
        $this->assertFalse($link['error'], json_encode($link));
        $this->assertStringContainsString('--data-binary @', $link['body']['command']);
        $url = $link['body']['upload_url'];
        $this->assertSame(0, Document::count());

        $html = '<html><img src="data:image/png;base64,'.str_repeat('A', 3_000_000).'"></html>';
        app('auth')->forgetGuards();
        $this->call('PUT', $url, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $html)
            ->assertCreated()->assertJsonPath('slug', 'quarto-notebook')->assertJsonPath('kind', 'html')
            ->assertJsonPath('visibility', 'private')->assertJsonPath('project', null);

        $version = Document::where('slug', 'quarto-notebook')->sole()->latestVersion();
        $this->assertSame(strlen($html), (int) $version->size_bytes);
        $this->assertSame('Claude', $version->provenance['agent']);

        $this->call('PUT', $url, [], [], [], ['HTTP_ACCEPT' => 'application/json'], 'again')->assertNotFound();
    }

    public function test_an_upload_link_expires_and_rejects_empty_files(): void
    {
        $user = $this->owner();
        $api = $user->createToken('script')->plainTextToken;
        $url = $this->withToken($api)->postJson('/api/v1/artifacts/uploads', ['filename' => 'fig.png', 'project' => 'penguin-study'])
            ->assertCreated()->json('upload_url');
        app('auth')->forgetGuards();

        $this->call('PUT', $url, [], [], [], ['HTTP_ACCEPT' => 'application/json'], '')->assertUnprocessable();

        $this->travel(31)->minutes();
        $this->call('PUT', $url, [], [], [], ['HTTP_ACCEPT' => 'application/json'], 'png bytes')->assertNotFound();
        $this->call('PUT', '/api/v1/uploads/'.str_repeat('a', 48), [], [], [], ['HTTP_ACCEPT' => 'application/json'], 'x')->assertNotFound();
    }

    public function test_a_linked_artifact_appears_in_its_projects_and_their_reports(): void
    {
        $user = $this->owner();
        Project::factory()->for($user)->create(['slug' => 'thesis', 'name' => 'Thesis']);
        $token = $this->connect($user, McpConnection::ACCESS_FULL);
        $this->tool($token, 'artifacts_publish', ['filename' => 'mass.csv', 'content' => "species,mass\nAdelie,3700\n"]);

        $linked = $this->tool($token, 'artifacts_link', ['project' => 'penguin-study', 'artifact' => 'mass']);
        $this->assertFalse($linked['error'], json_encode($linked));
        $this->tool($token, 'artifacts_link', ['project' => 'thesis', 'artifact' => 'mass']);
        $this->assertSame(['penguin-study', 'thesis'], $this->tool($token, 'artifacts_show', ['artifact' => 'mass'])['body']['linked_projects']);
        $this->assertSame(['mass'], array_column($this->tool($token, 'artifacts_list', ['project' => 'penguin-study'])['body']['artifacts'], 'slug'));

        $report = $this->tool($token, 'reports_create', ['slug' => 'penguin-study', 'title' => 'Mass', 'publish' => true,
            'blocks' => [['type' => 'artifact', 'artifact' => 'mass']]]);
        $this->assertFalse($report['error'], json_encode($report));
        $this->assertSame(1, $report['body']['latest_version']);

        app('auth')->forgetGuards();
        $this->actingAs($user)->get('/projects/penguin-study/published')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('project.documents.0.linked_from', 'No project'));

        $this->tool($token, 'artifacts_unlink', ['project' => 'thesis', 'artifact' => 'mass']);
        $this->assertSame(['penguin-study'], $this->tool($token, 'artifacts_show', ['artifact' => 'mass'])['body']['linked_projects']);
    }

    public function test_the_artifact_page_links_and_unlinks_projects(): void
    {
        $user = $this->owner();
        $thesis = Project::factory()->for($user)->create(['slug' => 'thesis', 'name' => 'Thesis']);
        $document = $this->figure($user);
        $other = Project::factory()->for(User::factory()->create())->create();

        $this->actingAs($user)->get("/artifacts/{$document->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->has('linkedProjects', 0)->where('linkableProjects.0.slug', 'thesis'));

        $this->post("/artifacts/{$document->id}/projects", ['project_id' => $other->id])->assertNotFound();
        $this->post("/artifacts/{$document->id}/projects", ['project_id' => $thesis->id])->assertRedirect();
        $this->assertSame([$thesis->id], $document->linkedProjects()->pluck('projects.id')->all());

        $this->delete("/artifacts/{$document->id}/projects/{$thesis->id}")->assertRedirect();
        $this->assertSame(0, $document->linkedProjects()->count());
    }

    public function test_an_app_revoking_its_token_ends_the_connection(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_READ);

        $this->getJson('/.well-known/oauth-authorization-server')->assertJsonPath('revocation_endpoint', url('/oauth/revoke'));

        // Nonsense, and another client's claim, change nothing
        $this->post('/oauth/revoke', ['token' => 'nonsense'])->assertOk();
        $this->post('/oauth/revoke', ['token' => $token, 'client_id' => 'someone-else'])->assertOk();
        $this->assertNull(McpConnection::sole()->revoked_at);

        $this->post('/oauth/revoke', ['token' => $token, 'token_type_hint' => 'access_token', 'client_id' => $this->lastClientId])->assertOk();

        $connection = McpConnection::sole();
        $this->assertNotNull($connection->revoked_at);
        $this->assertSame(McpConnection::ENDED_BY_APP, $connection->ended_reason);
        app('auth')->forgetGuards();
        $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    public function test_revoking_a_refresh_token_ends_the_connection(): void
    {
        $user = $this->owner();
        $this->connect($user, McpConnection::ACCESS_READ);

        $this->post('/oauth/revoke', ['token' => $this->lastRefreshToken])->assertOk();

        $this->assertSame(McpConnection::ENDED_BY_APP, McpConnection::sole()->ended_reason);
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_a_connection_whose_tokens_lapsed_shows_as_ended(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_READ);
        $this->tool($token, 'projects_list');
        app('auth')->forgetGuards();

        $nav = fn () => collect($this->actingAs($user)->get('/agents')->viewData('page')['props']['nav'])->firstWhere('href', '/agents')['label'];
        $this->travel(2)->days();
        $this->assertSame('Connected agents', $nav());

        $this->travel(31)->days();
        $this->assertSame('Connect your agent', $nav());
        $this->assertSame(McpConnection::ENDED_EXPIRED, McpConnection::sole()->ended_reason);
        $this->get('/agents/history')->assertInertia(fn (AssertableInertia $page) => $page->where('calls.data.0.ended', 'expired'));
    }

    public function test_a_web_app_connecting_again_replaces_its_old_connection(): void
    {
        $user = $this->owner();
        $first = $this->connect($user, McpConnection::ACCESS_READ, null, 'https://claude.ai/api/mcp/auth_callback');
        $this->authorizeAgain($user, 'https://claude.ai/api/mcp/auth_callback')->assertRedirect();

        $this->assertSame(1, McpConnection::active()->count());
        $this->assertSame(McpConnection::ENDED_REPLACED, McpConnection::orderBy('id')->first()->ended_reason);
        app('auth')->forgetGuards();
        $this->withToken($first)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    public function test_two_computers_keep_their_own_connections(): void
    {
        $user = $this->owner();
        $this->connect($user, McpConnection::ACCESS_FULL);
        $this->connect($user, McpConnection::ACCESS_FULL);

        $this->assertSame(2, McpConnection::active()->count());
    }

    /** Start an authorization for a new registration of an app, as the signed-in owner, and return the response. */
    private function authorizeAgain(User $user, string $redirect): TestResponse
    {
        $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]])->json('client_id');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '=');

        return $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId, 'redirect_uri' => $redirect, 'response_type' => 'code', 'scope' => 'mcp:use',
            'state' => 's', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]));
    }

    public function test_a_web_app_approved_once_reconnects_without_asking(): void
    {
        $user = $this->owner();
        $this->connect($user, McpConnection::ACCESS_READ, null, 'https://claude.ai/api/mcp/auth_callback');
        $this->assertSame('read', McpApprovedApp::sole()->access);

        $again = $this->authorizeAgain($user, 'https://claude.ai/api/mcp/auth_callback');
        $again->assertRedirect();
        $this->assertStringStartsWith('https://claude.ai/api/mcp/auth_callback?code=', $again->headers->get('Location'));
        $this->assertSame(1, McpConnection::active()->count());
        $this->assertSame('read', McpConnection::active()->sole()->access);

        // A changed access applies next time; forgetting brings the consent screen back
        $app = McpApprovedApp::sole();
        $this->patch("/agents/apps/{$app->id}", ['access' => 'full'])->assertRedirect();
        $this->authorizeAgain($user, 'https://claude.ai/api/mcp/auth_callback')->assertRedirect();
        $this->assertSame('full', McpConnection::active()->sole()->access);

        $this->delete("/agents/apps/{$app->id}")->assertRedirect();
        $this->authorizeAgain($user, 'https://claude.ai/api/mcp/auth_callback')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('OAuth/Authorize'));
    }

    public function test_the_default_access_is_selected_for_a_new_agent(): void
    {
        $user = $this->owner();
        $this->connect($user, McpConnection::ACCESS_FULL, null, 'https://claude.ai/api/mcp/auth_callback');

        $this->actingAs($user)->get('/agents/permissions')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agents/Permissions')->where('defaultAccess', 'full')->has('approvedApps', 1));
        $this->patch('/agents/permissions', ['agent_access' => 'read'])->assertRedirect();
        $this->patch('/agents/permissions', ['agent_access' => 'admin'])->assertInvalid('agent_access');
        $this->assertSame('read', $user->fresh()->agent_access);

        $this->authorizeAgain($user, 'http://localhost:33418/callback')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('OAuth/Authorize')->where('defaultAccess', 'read'));
    }

    public function test_apps_on_a_computer_always_ask(): void
    {
        $user = $this->owner();
        $this->connect($user, McpConnection::ACCESS_FULL);

        $this->assertSame(0, McpApprovedApp::count());
        $this->authorizeAgain($user, 'http://localhost:33418/callback')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('OAuth/Authorize'));
    }

    public function test_an_agent_reads_creates_and_updates_skills(): void
    {
        $user = $this->owner();
        $builtIn = Skill::create(['user_id' => null, 'key' => 'framework-reports', 'name' => 'Reports', 'description' => 'Reports.', 'visibility' => 'public']);
        $builtIn->publishVersion(['content' => "---\nname: framework-reports\ndescription: Reports.\n---\n\nBuilt in.", 'files' => []]);
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $shown = $this->tool($token, 'skills_show', ['skill' => 'framework/framework-reports']);
        $this->assertFalse($shown['error'], json_encode($shown));
        $this->assertStringContainsString('Built in.', $shown['body']['content']);
        $this->assertFalse($shown['body']['editable']);

        $refused = $this->tool($token, 'skills_update', ['skill' => 'framework/framework-reports', 'content' => "---\nname: framework-reports\ndescription: X.\n---\nMine."]);
        $this->assertSame(403, $refused['body']['error']['status']);

        $made = $this->tool($token, 'skills_create', ['content' => "---\nname: penguin-checks\ndescription: Check penguin data.\n---\n\nVersion one."]);
        $this->assertFalse($made['error'], json_encode($made));
        $this->assertSame(1, $made['body']['version']);
        $this->assertSame('private', $made['body']['visibility']);

        $stale = $this->tool($token, 'skills_update', ['skill' => 'penguin-checks', 'expected_version' => 9, 'content' => "---\nname: penguin-checks\ndescription: Check.\n---\n\nTwo."]);
        $this->assertSame(422, $stale['body']['error']['status']);

        $updated = $this->tool($token, 'skills_update', ['skill' => 'penguin-checks', 'expected_version' => 1, 'notes' => 'Clearer', 'content' => "---\nname: penguin-checks\ndescription: Check penguin data.\n---\n\nVersion two."]);
        $this->assertFalse($updated['error'], json_encode($updated));
        $this->assertSame(2, $updated['body']['version']);
        $this->assertStringContainsString('Version two.', $this->tool($token, 'skills_show', ['skill' => 'penguin-checks'])['body']['content']);

        $renamed = $this->tool($token, 'skills_update', ['skill' => 'penguin-checks', 'content' => "---\nname: other-name\ndescription: X.\n---\nY"]);
        $this->assertSame(422, $renamed['body']['error']['status']);
    }

    public function test_an_agent_creates_and_renames_projects_without_seeing_tokens(): void
    {
        $this->seed(BlueprintSeeder::class);
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $this->tool($token, 'projects_update', ['slug' => 'penguin-study', 'name' => 'Penguin body mass', 'description' => 'Palmer penguins.']);
        $this->assertSame('Penguin body mass', $user->projects()->where('slug', 'penguin-study')->value('name'));

        $made = $this->tool($token, 'projects_create', ['name' => 'Thesis chapter two']);
        $this->assertFalse($made['error'], json_encode($made));
        $this->assertSame('thesis-chapter-two', $made['body']['slug']);
        $this->assertArrayNotHasKey('token', $made['body']);
        $this->assertTrue($user->projects()->where('slug', 'thesis-chapter-two')->exists());
    }

    public function test_agent_deletes_go_to_the_trash_and_can_be_restored(): void
    {
        $user = $this->owner();
        $document = $this->figure($user);
        $token = $this->connect($user, McpConnection::ACCESS_FULL);
        $report = $this->tool($token, 'reports_create', ['slug' => 'penguin-study', 'title' => 'Body mass', 'markdown' => 'Text']);
        $this->tool($token, 'skills_create', ['content' => "---\nname: penguin-checks\ndescription: Check.\n---\nOne."]);

        $this->assertFalse($this->tool($token, 'reports_delete', ['slug' => 'penguin-study', 'report' => $report['body']['slug']])['error']);
        $this->assertFalse($this->tool($token, 'skills_delete', ['skill' => 'penguin-checks'])['error']);
        $this->assertFalse($this->tool($token, 'projects_delete', ['slug' => 'penguin-study'])['error']);

        // Gone from readers and lists
        app('auth')->forgetGuards();
        $this->get(route('documents.show', ['key' => $document->key]))->assertNotFound();
        $this->assertSame([], $this->tool($token, 'projects_list')['body']['projects']);
        $again = $this->tool($token, 'skills_create', ['content' => "---\nname: penguin-checks\ndescription: Check.\n---\nTwo."]);
        $this->assertStringContainsString('restore it from the Trash', json_encode($again['body']));

        // The Trash lists all three; a report waits for its project
        app('auth')->forgetGuards();
        $this->actingAs($user)->get('/trash')->assertInertia(fn (AssertableInertia $page) => $page->component('Trash')->has('items', 3));
        $reportId = Report::onlyTrashed()->sole()->id;
        $this->post("/trash/report/{$reportId}/restore")->assertInvalid('restore');
        $projectId = Project::onlyTrashed()->where('slug', 'penguin-study')->sole()->id;
        $this->post("/trash/project/{$projectId}/restore")->assertRedirect();
        $this->post("/trash/report/{$reportId}/restore")->assertRedirect();
        $this->post('/trash/skill/'.Skill::onlyTrashed()->sole()->id.'/restore')->assertRedirect();

        $this->get(route('documents.show', ['key' => $document->key]))->assertOk();
        $this->get('/trash')->assertInertia(fn (AssertableInertia $page) => $page->has('items', 0));
    }

    public function test_agents_cannot_delete_built_in_skills(): void
    {
        $user = $this->owner();
        $builtIn = Skill::create(['user_id' => null, 'key' => 'framework-reports', 'name' => 'Reports', 'description' => 'Reports.', 'visibility' => 'public']);
        $builtIn->publishVersion(['content' => "---\nname: framework-reports\ndescription: Reports.\n---\nBuilt in.", 'files' => []]);
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $this->assertSame(403, $this->tool($token, 'skills_delete', ['skill' => 'framework/framework-reports'])['body']['error']['status']);
        $this->assertFalse($builtIn->fresh()->trashed());
    }

    public function test_an_agent_deletes_an_artifact_to_the_trash(): void
    {
        $user = $this->owner();
        $document = $this->figure($user);
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $this->assertFalse($this->tool($token, 'artifacts_delete', ['artifact' => 'mass-model', 'project' => 'penguin-study'])['error']);

        $this->assertTrue(Document::withTrashed()->find($document->id)->trashed());
        $this->assertSame([], $this->tool($token, 'artifacts_list')['body']['artifacts']);
    }

    public function test_an_agent_proposes_and_creates_a_blueprint(): void
    {
        $this->seed(BlueprintSeeder::class);
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_FULL);

        $options = $this->tool($token, 'blueprints_options');
        $this->assertContains('quarto', array_column($options['body']['tools'], 'key'));
        $this->assertArrayNotHasKey('id', $options['body']['structures'][0]);

        $standard = $this->tool($token, 'blueprints_show', ['blueprint' => 'framework/project']);
        $this->assertFalse($standard['body']['editable']);
        $choices = $standard['body']['choices'];

        // Propose: R with Quarto and renv, previewed without saving
        $proposal = ['language' => 'r', 'structure' => $choices['structure'], 'tools' => ['quarto' => true, 'renv' => true],
            'package_sets' => $choices['package_sets'], 'skill_sets' => $choices['skill_sets'], 'settings' => ['sensitive' => false, 'git' => true, 'ai' => true]];
        $preview = $this->tool($token, 'blueprints_preview', $proposal + ['workflow' => 'quarto']);
        $this->assertFalse($preview['error'], json_encode($preview));
        $this->assertNotEmpty($preview['body']['tree']);
        $this->assertSame(0, Blueprint::where('user_id', $user->id)->count());

        $made = $this->tool($token, 'blueprints_create', $proposal + ['name' => 'Quarto in R', 'description' => 'R with Quarto and renv.']);
        $this->assertFalse($made['error'], json_encode($made));
        $this->assertTrue($made['body']['editable']);
        $this->assertSame('private', $made['body']['visibility']);
        $this->assertTrue($made['body']['choices']['tools']['renv'] ?? false);

        $updated = $this->tool($token, 'blueprints_update', array_merge($proposal, ['blueprint' => $made['body']['key'], 'name' => 'Quarto in R', 'tools' => ['quarto' => true]]));
        $this->assertFalse($updated['error'], json_encode($updated));
        $this->assertSame(2, $updated['body']['version']);
        $this->assertArrayNotHasKey('renv', $updated['body']['choices']['tools']);

        $refused = $this->tool($token, 'blueprints_update', ['blueprint' => 'framework/project'] + $proposal + ['name' => 'Mine']);
        $this->assertSame(403, $refused['body']['error']['status']);

        $project = $this->tool($token, 'projects_create', ['name' => 'Penguin chapter', 'blueprint' => $made['body']['key']]);
        $this->assertFalse($project['error'], json_encode($project));
    }
}
