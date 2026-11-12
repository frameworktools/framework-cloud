<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

/**
 * Database and storage bucket connections, as the blueprint and project
 * editors hold them, and the layer's `connections` and `env` keys they become.
 *
 * Drivers and fields follow what the packages read: framework's R package
 * (R/connections*.R) and framework-py (connections.py, storage.py) accept the
 * same drivers and field names. Stata has no connections: fw writes no
 * connection settings and Framework for Stata has no database functions.
 *
 * Secrets are never values. Each secret field is env("NAME") with no
 * default, and every variable a connection reads goes into the env layer
 * so the project's .env lists it, empty unless a non-secret default is given.
 */
class Connections
{
    /** Languages whose packages read connections. */
    public const LANGUAGES = ['r', 'python'];

    /** Database drivers and the fields each reads, in the editor's order. */
    public const DATABASES = [
        'postgres' => ['host', 'port', 'database', 'schema', 'user', 'password'],
        'mysql' => ['host', 'port', 'database', 'user', 'password'],
        'sqlserver' => ['host', 'port', 'database', 'user', 'password'],
        'duckdb' => ['database'],
        'sqlite' => ['database'],
    ];

    /** Storage bucket drivers and their fields. */
    public const BUCKETS = [
        's3' => ['bucket', 'region', 'endpoint', 'prefix', 'access_key_id', 'secret_access_key', 'session_token'],
    ];

    /** Other names the packages accept for a driver. */
    public const ALIASES = ['postgresql' => 'postgres', 'mariadb' => 'mysql', 'mssql' => 'sqlserver'];

    /** Fields whose values are always env("NAME"). */
    public const SECRETS = ['password', 'access_key_id', 'secret_access_key', 'session_token'];

    /** Fields the packages require, by driver. */
    private const REQUIRED = [
        'postgres' => ['host', 'port', 'database', 'user', 'password'],
        'mysql' => ['host', 'port', 'database', 'user', 'password'],
        'sqlserver' => ['host', 'database', 'user', 'password'],
        'duckdb' => ['database'],
        'sqlite' => ['database'],
        's3' => ['bucket', 'access_key_id', 'secret_access_key'],
    ];

    private const LABELS = [
        'host' => 'Host', 'port' => 'Port', 'database' => 'Database', 'schema' => 'Schema', 'user' => 'User', 'password' => 'Password',
        'bucket' => 'Bucket', 'region' => 'Region', 'endpoint' => 'Endpoint', 'prefix' => 'Prefix',
        'access_key_id' => 'Access key ID', 'secret_access_key' => 'Secret access key', 'session_token' => 'Session token',
    ];

    /** Framework's own database in every R and Python project. */
    public const RESERVED = 'framework';

    private const NAME = '/^[a-z][a-z0-9_]*$/';

    /** env("NAME") or env("NAME", "default"), with the name and default captured */
    private const ENV = '/^env\(\s*["\']([A-Za-z_][A-Za-z0-9_]*)["\']\s*(?:,\s*(?:"([^"]*)"|\'([^\']*)\')\s*)?\)$/';

    /** An editor with no connections. */
    public static function empty(): array
    {
        return ['default_database' => '', 'default_storage_bucket' => '', 'databases' => [], 'storage_buckets' => [], 'variables' => []];
    }

    /** A .env variable name, as R and Python read them. */
    private const VARIABLE = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** Names whose values are credentials, as Layer checks them. */
    private const CREDENTIAL = '/(password|secret|token|access_key|api_key|private_key|credential)/i';

    /** @return array<string, list<mixed>> request rules for the editor's connections field */
    public static function rules(): array
    {
        return [
            'connections' => ['sometimes', 'nullable', 'array'],
            'connections.default_database' => ['nullable', 'string', 'max:40'],
            'connections.default_storage_bucket' => ['nullable', 'string', 'max:40'],
            'connections.databases' => ['nullable', 'array', 'max:20'],
            'connections.databases.*' => ['array'],
            'connections.databases.*.*' => ['nullable', 'max:300'],
            'connections.storage_buckets' => ['nullable', 'array', 'max:20'],
            'connections.storage_buckets.*' => ['array'],
            'connections.storage_buckets.*.*' => ['nullable', 'max:300'],
            // Other .env variables: a name and a value, which stays empty for secrets
            'connections.variables' => ['nullable', 'array', 'max:50'],
            'connections.variables.*' => ['array'],
            'connections.variables.*.name' => ['nullable', 'string', 'max:100'],
            'connections.variables.*.value' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The editor's connections as layer keys: `connections` and the `env`
     * variables they read, with the editor's other variables. Empty when
     * there are none.
     *
     * @param  array<string, mixed>  $input  the editor's connections field
     * @return array{connections?: array<string, mixed>, env?: array<string, string>}
     *
     * @throws ValidationException
     */
    public static function layer(array $input, string $language): array
    {
        $databases = array_values(array_filter((array) ($input['databases'] ?? []), 'is_array'));
        $buckets = array_values(array_filter((array) ($input['storage_buckets'] ?? []), 'is_array'));
        $variables = self::ownVariables((array) ($input['variables'] ?? []));
        if (! $databases && ! $buckets) {
            ksort($variables);

            return $variables ? ['env' => $variables] : [];
        }
        if (! in_array($language, self::LANGUAGES, true)) {
            throw ValidationException::withMessages(['connections' => (Project::LANGUAGE_NAMES[$language] ?? $language).' projects have no database or storage bucket connections.']);
        }

        $errors = [];
        $connections = [];
        foreach (['databases' => [$databases, self::DATABASES], 'storage_buckets' => [$buckets, self::BUCKETS]] as $kind => [$rows, $drivers]) {
            foreach ($rows as $i => $row) {
                $at = "connections.{$kind}.{$i}";
                $name = trim((string) ($row['name'] ?? ''));
                if (! preg_match(self::NAME, $name) || strlen($name) > 40) {
                    $errors["{$at}.name"] = 'Use a lowercase name that starts with a letter, with letters, numbers, and underscores, such as study_db.';

                    continue;
                }
                if ($kind === 'databases' && $name === self::RESERVED) {
                    $errors["{$at}.name"] = 'framework is the project\'s own database. Choose another name.';

                    continue;
                }
                if (isset($connections[$kind][$name])) {
                    $errors["{$at}.name"] = "Another connection is named {$name}.";

                    continue;
                }
                $driver = self::ALIASES[$row['driver'] ?? ''] ?? ($row['driver'] ?? '');
                if (! isset($drivers[$driver])) {
                    $errors["{$at}.driver"] = 'Choose one of: '.implode(', ', array_keys($drivers)).'.';

                    continue;
                }
                $entry = ['driver' => $driver];
                foreach ($drivers[$driver] as $field) {
                    $value = trim((string) ($row[$field] ?? ''));
                    if ($value === '') {
                        if (in_array($field, self::REQUIRED[$driver], true)) {
                            $errors["{$at}.{$field}"] = self::LABELS[$field].' is required for '.$name.'.';
                        }

                        continue;
                    }
                    if ($problem = self::valueProblem($field, $value)) {
                        $errors["{$at}.{$field}"] = $problem;

                        continue;
                    }
                    $entry[$field] = $field === 'port' && ctype_digit($value) ? (int) $value : $value;
                }
                $connections[$kind][$name] = $entry;
            }
        }

        foreach (['default_database' => 'databases', 'default_storage_bucket' => 'storage_buckets'] as $key => $kind) {
            $default = trim((string) ($input[$key] ?? ''));
            if ($default === '') {
                continue;
            }
            if (! isset($connections[$kind][$default])) {
                $errors["connections.{$key}"] = "There is no connection named {$default}.";

                continue;
            }
            $connections[$key] = $default;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $layer = ['connections' => $connections];
        if ($env = array_replace(self::variables($connections), $variables)) {
            ksort($env);
            $layer['env'] = $env;
        }

        return $layer;
    }

    /**
     * The editor's own .env variables, by name. Rows with neither a name nor
     * a value are left out. A secret-looking name keeps an empty value: the
     * value goes in the project's .env on each computer.
     *
     * @param  array<int, mixed>  $rows
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private static function ownVariables(array $rows): array
    {
        $env = [];
        $errors = [];
        foreach (array_values($rows) as $i => $row) {
            $name = trim((string) (is_array($row) ? ($row['name'] ?? '') : ''));
            $value = (string) (is_array($row) ? ($row['value'] ?? '') : '');
            if ($name === '' && trim($value) === '') {
                continue;
            }
            $at = "connections.variables.{$i}";
            if (! preg_match(self::VARIABLE, $name) || strlen($name) > 100) {
                $errors["{$at}.name"] = 'Use letters, numbers, and underscores, starting with a letter or underscore, such as STUDY_YEAR.';
            } elseif (array_key_exists($name, $env)) {
                $errors["{$at}.name"] = "{$name} is listed twice.";
            } elseif (preg_match(self::CREDENTIAL, $name) && trim($value) !== '') {
                $errors["{$at}.value"] = "{$name} looks like a secret. Leave the value empty and fill it in the project's .env.";
            } elseif (preg_match('/[\x00-\x1F]/', $value)) {
                $errors["{$at}.value"] = 'A value must be on one line.';
            } else {
                $env[$name] = $value;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $env;
    }

    private static function valueProblem(string $field, string $value): ?string
    {
        $label = self::LABELS[$field];
        if (in_array($field, self::SECRETS, true)) {
            if (! preg_match(self::ENV, $value, $match)) {
                return "{$label} looks like a credential. Use env(\"VARIABLE\") and keep the value in the project's .env.";
            }
            if (($match[2] ?? '') !== '' || ($match[3] ?? '') !== '') {
                return "{$label} gives env() a default that looks like a credential. Leave the default empty and keep the value in the project's .env.";
            }

            return null;
        }
        if (str_starts_with($value, 'env(')) {
            return preg_match(self::ENV, $value) ? null : "{$label}: write env(\"VARIABLE\") or a value.";
        }
        if ($field === 'port' && (! ctype_digit($value) || (int) $value < 1 || (int) $value > 65535)) {
            return 'Port must be a number from 1 to 65535, or env("VARIABLE").';
        }
        if (preg_match('/[\x00-\x1F]/', $value)) {
            return "{$label} can't hold control characters.";
        }

        return null;
    }

    /**
     * The env variables connections read, each empty, or with the default a
     * non-secret field gives env(). Sorted by name.
     *
     * @return array<string, string>
     */
    public static function variables(array $connections): array
    {
        $env = [];
        foreach (['databases', 'storage_buckets'] as $kind) {
            foreach ((array) ($connections[$kind] ?? []) as $entry) {
                foreach (is_array($entry) ? $entry : [] as $field => $value) {
                    if (is_string($value) && preg_match(self::ENV, trim($value), $match)) {
                        $default = in_array($field, self::SECRETS, true) ? '' : (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? ''));
                        $env[$match[1]] = ($env[$match[1]] ?? '') !== '' ? $env[$match[1]] : $default;
                    }
                }
            }
        }
        ksort($env);

        return $env;
    }

    /**
     * A layer's connections as the editor holds them, and the layer without
     * them. A connection the editor can't show (another driver, or fields it
     * has no input for) stays in the layer, as does a default naming one.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} the editor's connections, the rest of the layer
     */
    public static function split(array $layer): array
    {
        $form = self::empty();
        $connections = is_array($layer['connections'] ?? null) ? $layer['connections'] : [];
        $taken = [];
        foreach (['databases' => self::DATABASES, 'storage_buckets' => self::BUCKETS] as $kind => $drivers) {
            foreach ((array) ($connections[$kind] ?? []) as $name => $entry) {
                $driver = is_array($entry) ? (self::ALIASES[$entry['driver'] ?? ''] ?? ($entry['driver'] ?? null)) : null;
                $fits = is_string($driver) && isset($drivers[$driver]) && preg_match(self::NAME, (string) $name) && ! ($kind === 'databases' && $name === self::RESERVED)
                    && ! array_diff(array_keys($entry), ['driver', ...$drivers[$driver]])
                    && collect($entry)->every(fn ($value) => is_string($value) || is_int($value));
                if (! $fits) {
                    continue;
                }
                $row = ['name' => (string) $name, 'driver' => $driver];
                foreach ($drivers[$driver] as $field) {
                    $row[$field] = isset($entry[$field]) ? (string) $entry[$field] : '';
                }
                $form[$kind][] = $row;
                $taken[$kind][$name] = $entry;
                unset($connections[$kind][$name]);
            }
            if (($connections[$kind] ?? null) === []) {
                unset($connections[$kind]);
            }
        }
        foreach (['default_database' => 'databases', 'default_storage_bucket' => 'storage_buckets'] as $key => $kind) {
            if (is_string($connections[$key] ?? null) && isset($taken[$kind][$connections[$key]])) {
                $form[$key] = $connections[$key];
                unset($connections[$key]);
            }
        }

        // The variables the editor's connections read come back when it saves
        // them. The rest, and any given another value, are the editor's own.
        $env = is_array($layer['env'] ?? null) ? $layer['env'] : [];
        foreach (self::variables($taken) as $name => $default) {
            if (array_key_exists($name, $env) && (string) $env[$name] === $default) {
                unset($env[$name]);
            }
        }
        foreach ($env as $name => $value) {
            if (is_string($name) && preg_match(self::VARIABLE, $name) && (is_string($value) || is_int($value) || is_float($value))) {
                $form['variables'][] = ['name' => $name, 'value' => (string) $value];
                unset($env[$name]);
            }
        }

        unset($layer['connections'], $layer['env']);
        if ($connections) {
            $layer['connections'] = $connections;
        }
        if ($env) {
            $layer['env'] = $env;
        }

        return [$form, $layer];
    }

    /**
     * The connections a merged spec names, by kind, without Framework's own
     * database: "study_db (postgres)".
     *
     * @return array{databases: list<string>, storage_buckets: list<string>}
     */
    public static function described(array $connections): array
    {
        $out = ['databases' => [], 'storage_buckets' => []];
        foreach (array_keys($out) as $kind) {
            foreach ((array) ($connections[$kind] ?? []) as $name => $entry) {
                if (is_array($entry) && ! ($kind === 'databases' && $name === self::RESERVED)) {
                    $out[$kind][] = "{$name} (".($entry['driver'] ?? '?').')';
                }
            }
        }

        return $out;
    }
}
