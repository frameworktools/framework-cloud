<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Tests\TestCase;

/**
 * A manual install: the .env template runs in production, and the OAuth keys
 * for connected AI apps are made on first use.
 */
class ManualInstallTest extends TestCase
{
    private string $keyDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyDir = sys_get_temp_dir().'/fw-oauth-keys-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->keyDir);
        Passport::loadKeysFrom($this->keyDir);
        config(['passport.private_key' => null, 'passport.public_key' => null]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->keyDir);
        Passport::$keyPath = null;
        parent::tearDown();
    }

    public function test_the_server_env_template_runs_in_production(): void
    {
        $env = Dotenv::parse(File::get(base_path('.env.server.example')));

        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('self', $env['FW_MODE']);
        $this->assertSame('pgsql', $env['DB_CONNECTION']);
    }

    public function test_oauth_keys_are_made_on_first_use_and_kept(): void
    {
        $this->assertFileDoesNotExist($this->keyDir.'/oauth-private.key');

        $this->assertInstanceOf(ResourceServer::class, app(ResourceServer::class));
        $this->assertInstanceOf(AuthorizationServer::class, app(AuthorizationServer::class));

        $private = $this->keyDir.'/oauth-private.key';
        $public = $this->keyDir.'/oauth-public.key';
        $this->assertStringContainsString('PRIVATE KEY', File::get($private));
        $this->assertStringContainsString('PUBLIC KEY', File::get($public));
        $this->assertSame(0600, fileperms($private) & 0777);
        $this->assertSame(0660, fileperms($public) & 0777);

        // The pair matches
        $details = openssl_pkey_get_details(openssl_pkey_get_private(File::get($private)));
        $this->assertSame(trim($details['key']), trim(File::get($public)));

        // A later request uses the same pair
        $before = File::get($private);
        $this->app->forgetInstance(ResourceServer::class);
        app(ResourceServer::class);
        $this->assertSame($before, File::get($private));
    }

    public function test_keys_from_the_environment_win(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);

        app(ResourceServer::class);

        $this->assertFileDoesNotExist($this->keyDir.'/oauth-private.key');
    }
}
