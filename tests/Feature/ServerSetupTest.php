<?php

namespace Tests\Feature;

use App\Models\InstanceSetting;
use App\Models\Project;
use App\Models\User;
use App\Support\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServerSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Start every test as a server with nothing in the environment
        config(['framework.env_set' => []]);
        Credentials::forget();
    }

    private function admin(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);
    }

    public function test_only_admins_can_open_setup(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get('/admin/setup')->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/setup')
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Setup')->where('mode', 'self'));
    }

    public function test_saved_values_are_encrypted_and_fill_in_config(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/setup/github', [
                'github__client_id' => 'Iv23-saved',
                'github__client_secret' => 'shh-secret',
                'github__app_slug' => 'framework-cloud-lab',
            ])
            ->assertSessionHasNoErrors();

        $raw = DB::table('instance_settings')->where('key', 'github.client_secret')->value('value');
        $this->assertNotSame('shh-secret', $raw);
        $this->assertStringNotContainsString('shh-secret', $raw);

        config(['services.github.client_secret' => null]);
        Credentials::forget();
        Credentials::apply();

        $this->assertSame('shh-secret', config('services.github.client_secret'));
        $this->assertSame('database', Credentials::source('github.client_secret'));
    }

    public function test_the_environment_wins_over_saved_values(): void
    {
        config(['framework.env_set' => ['GITHUB_CLIENT_ID'], 'services.github.client_id' => 'from-env']);

        $this->actingAs($this->admin())
            ->put('/admin/setup/github', ['github__client_id' => 'from-page'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('instance_settings', ['key' => 'github.client_id']);

        // Even a row saved earlier stays out of the way
        InstanceSetting::create(['key' => 'github.client_id', 'value' => 'from-page']);
        Credentials::forget();
        Credentials::apply();

        $this->assertSame('from-env', config('services.github.client_id'));
        $this->assertSame('env', Credentials::source('github.client_id'));
    }

    public function test_a_blank_secret_keeps_the_saved_one_and_a_blank_value_clears(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/setup/storage', [
            'storage__bucket' => 'docs', 'storage__secret' => 'bucket-secret', 'storage__region' => 'auto',
        ]);

        $this->actingAs($admin)->put('/admin/setup/storage', [
            'storage__bucket' => 'docs', 'storage__secret' => '', 'storage__region' => '',
        ]);

        $this->assertDatabaseHas('instance_settings', ['key' => 'storage.secret']);
        $this->assertDatabaseMissing('instance_settings', ['key' => 'storage.region']);
    }

    public function test_the_page_never_sends_secrets(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/setup/mail', ['mail__mailer' => 'smtp', 'mail__smtp_password' => 'mail-password']);
        Credentials::apply();

        $this->actingAs($admin)->get('/admin/setup')
            ->assertDontSee('mail-password')
            ->assertInertia(fn (Assert $page) => $page->where('fields', function ($fields) {
                $this->assertNull($fields['mail.smtp_password']['value']);
                $this->assertTrue($fields['mail.smtp_password']['set']);
                $this->assertSame('smtp', $fields['mail.mailer']['value']);

                return true;
            }));
    }

    public function test_values_the_app_key_cannot_read_are_reported(): void
    {
        DB::table('instance_settings')->insert(['key' => 'storage.secret', 'value' => 'not-ciphertext', 'created_at' => now(), 'updated_at' => now()]);
        Credentials::forget();

        $this->assertSame('broken', Credentials::source('storage.secret'));
        $this->actingAs($this->admin())->get('/admin/setup')
            ->assertInertia(fn (Assert $page) => $page->where('broken', ['storage.secret']));
    }

    public function test_a_saved_bucket_becomes_the_documents_disk(): void
    {
        $this->actingAs($this->admin())->put('/admin/setup/storage', ['storage__bucket' => 'docs']);
        config(['framework.documents_disk' => 'local', 'filesystems.disks.bucket.bucket' => null]);

        Credentials::apply();
        $this->assertSame('bucket', config('framework.documents_disk'));

        // ...unless the environment picks the disk
        config(['framework.env_set' => ['FW_DOCUMENTS_DISK'], 'framework.documents_disk' => 'r2']);
        Credentials::apply();
        $this->assertSame('r2', config('framework.documents_disk'));
    }

    public function test_the_github_manifest_flow_saves_the_new_app(): void
    {
        Http::fake(['api.github.com/app-manifests/*' => Http::response([
            'id' => 123456, 'slug' => 'framework-cloud-lab', 'client_id' => 'Iv23new',
            'client_secret' => 'new-secret', 'webhook_secret' => 'hook-secret', 'pem' => "-----BEGIN RSA PRIVATE KEY-----\nabc\n-----END RSA PRIVATE KEY-----",
        ], 201)]);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/setup')
            ->assertInertia(fn (Assert $page) => $page
                ->where('github.manifest.json.default_permissions.workflows', 'write')
                ->where('github.manifest.json.default_events', ['workflow_run'])
                ->where('github.manifest.json.callback_urls', [route('github.callback')]));
        $state = session('github_manifest_state');

        $this->actingAs($admin)->get('/admin/setup/github/callback?code=abc&state=wrong')
            ->assertSessionHasErrors('github');

        $this->actingAs($admin)->withSession(['github_manifest_state' => $state])
            ->get("/admin/setup/github/callback?code=abc&state={$state}")
            ->assertRedirect(route('admin.setup'))
            ->assertSessionHas('status', 'github-created');

        Credentials::apply();
        $this->assertSame('Iv23new', config('services.github.client_id'));
        $this->assertSame('hook-secret', config('services.github.webhook_secret'));
        $this->assertSame('123456', config('services.github.app_id'));
    }

    public function test_mail_test_needs_a_real_mailer(): void
    {
        config(['mail.default' => 'log']);

        $this->actingAs($this->admin())->post('/admin/setup/mail/test')->assertSessionHasErrors('mail');
    }

    public function test_hosted_publishing_needs_the_paid_flag(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $publish = fn () => $this->withToken($project->issuePullToken())->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('a.html', '<html>x</html>'),
        ], ['Accept' => 'application/json']);

        config(['framework.mode' => 'hosted']);
        $publish()->assertStatus(402);

        $project->user->forceFill(['can_publish' => true])->save();
        $this->app['auth']->forgetGuards();
        $publish()->assertCreated();
    }

    public function test_self_hosted_servers_let_everyone_publish(): void
    {
        $this->assertTrue(User::factory()->create()->canPublish());
    }

    public function test_admins_see_what_the_server_still_needs(): void
    {
        config(['mail.default' => 'log', 'services.github.client_id' => null]);

        $this->actingAs($this->admin())->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('setup', ['mail', 'github'])->has('steps', 3));

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('setup', []));
    }
}
