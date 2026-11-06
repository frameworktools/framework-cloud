<?php

namespace Tests\Feature\Mcp;

use App\Http\Controllers\OAuth\RegisterMcpClientController;
use App\Models\Document;
use App\Models\McpConnection;
use App\Models\McpToolCall;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\ArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ])->assertOk()->json('access_token');

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

        $artifact = $this->tool($token, 'artifacts_show', ['slug' => 'penguin-study', 'artifact' => 'mass-model']);
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

    public function test_disconnecting_cuts_the_app_off(): void
    {
        $user = $this->owner();
        $token = $this->connect($user, McpConnection::ACCESS_READ);
        $connection = McpConnection::sole();

        $this->actingAs($user)->get('/tokens')->assertInertia(fn (AssertableInertia $page) => $page->has('tokens', 0));
        $this->get('/agents')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agents/Index')->has('connections', 1)->where('connections.0.name', 'Claude'));

        $this->delete("/agents/{$connection->id}")->assertRedirect();
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

        $notebook = $this->tool($token, 'artifacts_publish', ['slug' => 'penguin-study', 'filename' => 'analysis.html',
            'content' => '<html><body><h1>Penguins</h1></body></html>', 'title' => 'Penguin analysis']);
        $this->assertFalse($notebook['error'], json_encode($notebook));
        $this->assertSame('analysis', $notebook['body']['slug']);
        $this->assertSame('html', $notebook['body']['kind']);
        $this->assertSame('private', $notebook['body']['visibility']);
        $this->assertSame(1, $notebook['body']['version']);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $figure = $this->tool($token, 'artifacts_publish', ['slug' => 'penguin-study', 'filename' => 'mass.png',
            'content_base64' => base64_encode($png), 'artifact' => 'flipper-and-mass']);
        $this->assertFalse($figure['error'], json_encode($figure));
        $this->assertSame('flipper-and-mass', $figure['body']['slug']);
        $this->assertSame('image', $figure['body']['kind']);

        $again = $this->tool($token, 'artifacts_publish', ['slug' => 'penguin-study', 'filename' => 'analysis.html', 'content' => '<html>v2</html>']);
        $this->assertSame(2, $again['body']['version']);

        $version = Document::where('slug', 'analysis')->sole()->versions()->first();
        $this->assertSame(['published_by' => 'agent', 'agent' => 'Claude'], array_intersect_key($version->provenance, array_flip(['published_by', 'agent'])));

        $shown = $this->tool($token, 'artifacts_show', ['slug' => 'penguin-study', 'artifact' => 'analysis']);
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

        $site = $this->tool($token, 'artifacts_publish', ['slug' => 'penguin-study', 'filename' => 'report.zip',
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

        $bad = $this->tool($full, 'artifacts_publish', ['slug' => 'penguin-study', 'filename' => 'x.png', 'content_base64' => '***']);
        $this->assertTrue($bad['error']);
        $this->assertSame(422, $bad['body']['error']['status']);

        $traversal = $this->tool($full, 'artifacts_publish', ['slug' => 'penguin-study', 'filename' => '../x.html', 'content' => 'x']);
        $this->assertSame(422, $traversal['body']['error']['status']);

        $preview = $this->tool($full, 'artifacts_publish', ['slug' => 'penguin-study', 'filename' => 'big.html', 'content' => str_repeat('a', 5000), 'dry_run' => true]);
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
}
