<?php

namespace App\Support;

use App\Models\InstanceSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * This server's own credentials: the GitHub App, the bucket published
 * documents go to, and the mail service. Never a user's credentials.
 *
 * Each one is read from its environment variable first. A value saved on the
 * admin setup page (encrypted with APP_KEY) fills in only when the variable
 * is unset, so a server can run entirely on environment variables, entirely
 * from the setup page, or a mix. Saved values are written into config at
 * boot, so the rest of the app reads config() as usual.
 */
class Credentials
{
    public const CACHE_KEY = 'instance_settings';

    /** key => [environment variable, config path, secret?] */
    public const REGISTRY = [
        'github.app_id' => ['GITHUB_APP_ID', 'services.github.app_id', false],
        'github.app_slug' => ['GITHUB_APP_SLUG', 'services.github.app_slug', false],
        'github.client_id' => ['GITHUB_CLIENT_ID', 'services.github.client_id', false],
        'github.client_secret' => ['GITHUB_CLIENT_SECRET', 'services.github.client_secret', true],
        'github.webhook_secret' => ['GITHUB_WEBHOOK_SECRET', 'services.github.webhook_secret', true],
        'github.private_key' => ['GITHUB_APP_PRIVATE_KEY', 'services.github.private_key', true],

        'storage.endpoint' => ['FW_BUCKET_ENDPOINT', 'filesystems.disks.bucket.endpoint', false],
        'storage.region' => ['FW_BUCKET_REGION', 'filesystems.disks.bucket.region', false],
        'storage.bucket' => ['FW_BUCKET_NAME', 'filesystems.disks.bucket.bucket', false],
        'storage.key' => ['FW_BUCKET_KEY', 'filesystems.disks.bucket.key', true],
        'storage.secret' => ['FW_BUCKET_SECRET', 'filesystems.disks.bucket.secret', true],
        'storage.public_url' => ['FW_DOCUMENTS_BASE_URL', 'framework.documents_base_url', false],

        'mail.mailer' => ['MAIL_MAILER', 'mail.default', false],
        'mail.from_address' => ['MAIL_FROM_ADDRESS', 'mail.from.address', false],
        'mail.from_name' => ['MAIL_FROM_NAME', 'mail.from.name', false],
        'mail.smtp_host' => ['MAIL_HOST', 'mail.mailers.smtp.host', false],
        'mail.smtp_port' => ['MAIL_PORT', 'mail.mailers.smtp.port', false],
        'mail.smtp_username' => ['MAIL_USERNAME', 'mail.mailers.smtp.username', false],
        'mail.smtp_password' => ['MAIL_PASSWORD', 'mail.mailers.smtp.password', true],
        'mail.ses_key' => ['AWS_ACCESS_KEY_ID', 'services.ses.key', true],
        'mail.ses_secret' => ['AWS_SECRET_ACCESS_KEY', 'services.ses.secret', true],
        'mail.ses_region' => ['AWS_DEFAULT_REGION', 'services.ses.region', false],
    ];

    /** Variables whose presence decides something without being a credential */
    public const EXTRA_ENV = ['FW_DOCUMENTS_DISK'];

    /** @var array{values: array<string, string>, broken: list<string>}|null */
    private static ?array $saved = null;

    /**
     * Every variable this class reads, for config/framework.php to record
     * which are set (env() returns null at runtime once config is cached).
     *
     * @return list<string>
     */
    public static function envVars(): array
    {
        return [...array_column(self::REGISTRY, 0), ...self::EXTRA_ENV];
    }

    public static function apply(): void
    {
        $saved = self::saved()['values'];

        foreach (self::REGISTRY as $key => [, $path]) {
            if (! self::fromEnv($key) && isset($saved[$key])) {
                config([$path => $saved[$key]]);
            }
        }

        // A bucket saved on the setup page becomes where documents go,
        // unless the environment picks a disk itself
        if (! self::envSet('FW_DOCUMENTS_DISK') && filled(config('filesystems.disks.bucket.bucket'))) {
            config(['framework.documents_disk' => 'bucket']);
        }
    }

    public static function fromEnv(string $key): bool
    {
        return self::envSet(self::REGISTRY[$key][0]);
    }

    public static function envSet(string $var): bool
    {
        return in_array($var, config('framework.env_set', []), true);
    }

    /** "env", "database", "broken" (saved but APP_KEY can't read it), or null */
    public static function source(string $key): ?string
    {
        if (self::fromEnv($key)) {
            return 'env';
        }

        $saved = self::saved();

        return match (true) {
            isset($saved['values'][$key]) => 'database',
            in_array($key, $saved['broken'], true) => 'broken',
            default => null,
        };
    }

    public static function value(string $key): mixed
    {
        return config(self::REGISTRY[$key][1]);
    }

    public static function secret(string $key): bool
    {
        return self::REGISTRY[$key][2];
    }

    /** @return list<string> */
    public static function group(string $prefix): array
    {
        return array_values(array_filter(array_keys(self::REGISTRY), fn ($k) => str_starts_with($k, $prefix.'.')));
    }

    /**
     * Save values from the setup page. Keys set in the environment are
     * skipped. A blank secret keeps the saved one; any other blank clears it.
     *
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            if (! isset(self::REGISTRY[$key]) || self::fromEnv($key)) {
                continue;
            }

            if (blank($value)) {
                if (! self::secret($key)) {
                    InstanceSetting::where('key', $key)->delete();
                }

                continue;
            }

            InstanceSetting::updateOrCreate(['key' => $key], ['value' => (string) $value, 'updated_by' => $userId]);
        }

        self::forget();
    }

    /** @param  list<string>  $keys */
    public static function clear(array $keys): void
    {
        InstanceSetting::whereIn('key', $keys)->delete();
        self::forget();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$saved = null;

        // Queue workers read credentials once, when they start
        Cache::forever('illuminate:queue:restart', now()->getTimestamp());
    }

    /**
     * Saved values, decrypted. The cache holds only ciphertext.
     *
     * @return array{values: array<string, string>, broken: list<string>}
     */
    private static function saved(): array
    {
        if (self::$saved !== null) {
            return self::$saved;
        }

        try {
            $rows = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('instance_settings')->pluck('value', 'key')->all());
        } catch (QueryException) {
            // Before migrations have run
            return ['values' => [], 'broken' => []];
        }

        $values = [];
        $broken = [];

        foreach ($rows as $key => $ciphertext) {
            try {
                $values[$key] = Crypt::decryptString($ciphertext);
            } catch (DecryptException) {
                $broken[] = $key;
            }
        }

        return self::$saved = ['values' => $values, 'broken' => $broken];
    }
}
