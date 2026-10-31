<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GitHub\Scaffolder;
use App\Support\Credentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Set up this server's own credentials: the GitHub App, the bucket for
 * published documents, and the mail service. Values saved here are
 * encrypted with APP_KEY; environment variables always win over them.
 */
class SetupController extends Controller
{
    public const GROUPS = ['github', 'storage', 'mail'];

    private const LABELS = [
        'github.app_id' => 'App ID',
        'github.app_slug' => 'App slug',
        'github.client_id' => 'Client ID',
        'github.client_secret' => 'Client secret',
        'github.webhook_secret' => 'Webhook secret',
        'github.private_key' => 'Private key',
        'storage.endpoint' => 'Endpoint',
        'storage.region' => 'Region',
        'storage.bucket' => 'Bucket',
        'storage.key' => 'Access key ID',
        'storage.secret' => 'Secret access key',
        'storage.public_url' => 'Public address',
        'mail.mailer' => 'Service',
        'mail.from_address' => 'From address',
        'mail.from_name' => 'From name',
        'mail.smtp_host' => 'SMTP host',
        'mail.smtp_port' => 'SMTP port',
        'mail.smtp_username' => 'SMTP username',
        'mail.smtp_password' => 'SMTP password',
        'mail.ses_key' => 'AWS access key ID',
        'mail.ses_secret' => 'AWS secret access key',
        'mail.ses_region' => 'AWS region',
    ];

    public function show(Request $request): Response
    {
        $fields = [];
        foreach (array_keys(Credentials::REGISTRY) as $key) {
            $secret = Credentials::secret($key);
            $value = Credentials::value($key);

            $fields[$key] = [
                'label' => self::LABELS[$key],
                'env' => Credentials::REGISTRY[$key][0],
                'source' => Credentials::source($key),
                'secret' => $secret,
                // Secrets never leave the server; the page only learns that one is set
                'value' => $secret ? null : (filled($value) ? (string) $value : ''),
                'set' => filled($value),
            ];
        }

        return Inertia::render('Admin/Setup', [
            'mode' => config('framework.mode'),
            'fields' => $fields,
            'status' => [
                'github' => Scaffolder::enabled() && filled(config('services.github.client_secret')) && filled(config('services.github.app_slug')),
                'storage' => config('framework.documents_disk'),
                'mail' => config('mail.default'),
            ],
            'documentsDiskFromEnv' => Credentials::envSet('FW_DOCUMENTS_DISK'),
            'broken' => collect(array_keys(Credentials::REGISTRY))->filter(fn ($k) => Credentials::source($k) === 'broken')->values(),
            'github' => [
                'manifest' => $this->manifest($request),
                'webhook_url' => url('/api/github/webhook'),
                'callback_url' => route('github.callback'),
            ],
            'adminEmail' => $request->user()->email,
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        abort_unless(in_array($group, self::GROUPS, true), 404);

        $rules = collect(Credentials::group($group))
            ->mapWithKeys(fn ($key) => [str_replace('.', '__', $key) => ['nullable', 'string', 'max:'.($key === 'github.private_key' ? 8000 : 500)]])
            ->all();

        if ($group === 'mail') {
            $rules['mail__mailer'] = ['nullable', Rule::in(['smtp', 'ses', 'log'])];
            $rules['mail__from_address'] = ['nullable', 'email', 'max:190'];
            $rules['mail__smtp_port'] = ['nullable', 'integer', 'between:1,65535'];
        }
        if ($group === 'storage') {
            $rules['storage__endpoint'] = ['nullable', 'url:https,http', 'max:500'];
            $rules['storage__public_url'] = ['nullable', 'url:https,http', 'max:500'];
        }

        $validated = $request->validate($rules);

        Credentials::save(
            collect($validated)->mapWithKeys(fn ($v, $k) => [str_replace('__', '.', $k) => is_string($v) ? trim($v) : $v])->all(),
            $request->user()->id,
        );

        return back()->with('status', "{$group}-saved");
    }

    public function destroy(string $group): RedirectResponse
    {
        abort_unless(in_array($group, self::GROUPS, true), 404);

        Credentials::clear(Credentials::group($group));

        return back()->with('status', "{$group}-cleared");
    }

    /**
     * Write, read, and delete a small file with the values on the form (a
     * blank secret uses the saved one), before anything is saved.
     */
    public function testStorage(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'storage__endpoint' => ['nullable', 'string', 'max:500'],
            'storage__region' => ['nullable', 'string', 'max:100'],
            'storage__bucket' => ['required', 'string', 'max:200'],
            'storage__key' => ['nullable', 'string', 'max:500'],
            'storage__secret' => ['nullable', 'string', 'max:500'],
        ], ['storage__bucket.required' => 'Fill in the bucket name first.']);

        $value = fn (string $key) => filled($input["storage__{$key}"] ?? null) ? $input["storage__{$key}"] : config("filesystems.disks.bucket.{$key}");

        try {
            $disk = Storage::build([
                'driver' => 's3',
                'endpoint' => $value('endpoint') ?: null,
                'region' => $value('region') ?: 'auto',
                'bucket' => $value('bucket'),
                'key' => $value('key'),
                'secret' => $value('secret'),
                'use_path_style_endpoint' => true,
                'throw' => true,
            ]);

            $path = 'framework-cloud-check/'.Str::uuid().'.txt';
            $disk->put($path, 'Framework Cloud storage check');
            $read = $disk->get($path);
            $disk->delete($path);

            if ($read !== 'Framework Cloud storage check') {
                return back()->withErrors(['storage' => 'The test file came back different from what was written.']);
            }
        } catch (Throwable $e) {
            return back()->withErrors(['storage' => 'The bucket could not be reached: '.Str::limit($e->getMessage(), 300)]);
        }

        return back()->with('status', 'storage-tested');
    }

    /** Send a test email to the signed-in admin with the saved settings. */
    public function testMail(Request $request): RedirectResponse
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['mail' => 'Mail is going to the log file, so there is nothing to test. Pick SMTP or Amazon SES and save first.']);
        }

        try {
            Mail::raw(
                'This is a test email from Framework Cloud at '.config('app.url').'. If you can read it, sign-in links will reach people.',
                fn ($message) => $message->to($request->user()->email)->subject('Framework Cloud test email'),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['mail' => 'Sending failed: '.Str::limit($e->getMessage(), 300)]);
        }

        return back()->with('status', 'mail-tested');
    }

    /**
     * GitHub's app manifest flow: the setup page posts the manifest to
     * GitHub, the admin confirms there, and GitHub sends back a code that
     * this exchanges for the new app's credentials.
     */
    public function githubCallback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('github_manifest_state');

        if (! $state || ! hash_equals($state, (string) $request->query('state')) || blank($request->query('code'))) {
            return to_route('admin.setup')->withErrors(['github' => 'That GitHub response did not match a request from this page. Try again.']);
        }

        if (Credentials::fromEnv('github.client_id')) {
            return to_route('admin.setup')->withErrors(['github' => 'The GitHub App is set in the environment, so it cannot be replaced from here.']);
        }

        try {
            $response = Http::acceptJson()->timeout(20)
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->post('https://api.github.com/app-manifests/'.urlencode($request->query('code')).'/conversions');
        } catch (ConnectionException) {
            return to_route('admin.setup')->withErrors(['github' => 'GitHub could not be reached. Try again.']);
        }

        if (! $response->successful()) {
            return to_route('admin.setup')->withErrors(['github' => 'GitHub did not hand over the new app: '.($response->json('message') ?? $response->status())]);
        }

        Credentials::save([
            'github.app_id' => (string) $response->json('id'),
            'github.app_slug' => $response->json('slug'),
            'github.client_id' => $response->json('client_id'),
            'github.client_secret' => $response->json('client_secret'),
            'github.webhook_secret' => $response->json('webhook_secret'),
            'github.private_key' => $response->json('pem'),
        ], $request->user()->id);

        return to_route('admin.setup')->with('status', 'github-created');
    }

    /**
     * The GitHub App this server needs: user sign-in, repositories it
     * creates and scaffolds, and the workflow_run event.
     */
    private function manifest(Request $request): array
    {
        $state = $request->session()->get('github_manifest_state') ?? Str::random(40);
        $request->session()->put('github_manifest_state', $state);

        $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'framework-cloud';

        return [
            'state' => $state,
            'json' => [
                'name' => Str::limit('Framework Cloud '.$host, 34, ''),
                'url' => config('app.url'),
                'description' => 'Creates and scaffolds Framework projects in your repositories.',
                'hook_attributes' => ['url' => url('/api/github/webhook'), 'active' => true],
                'redirect_url' => route('admin.setup.github.callback'),
                'callback_urls' => [route('github.callback')],
                'public' => true,
                'default_permissions' => [
                    'administration' => 'write',
                    'contents' => 'write',
                    'workflows' => 'write',
                    'actions' => 'write',
                    'pages' => 'write',
                    'metadata' => 'read',
                    'emails' => 'read',
                ],
                'default_events' => ['workflow_run'],
            ],
        ];
    }
}
