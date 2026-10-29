<?php

namespace App\Support;

use App\Models\Project;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks a partial spec layer before the cloud stores it. This is a guard,
 * not a resolver: it rejects shapes the framework R package cannot use and
 * anything that looks like a credential, and it never merges layers.
 *
 * Shapes come from the package's layer schema (resources/schema). The
 * cloud adds its own rules on top: which keys a stored layer may hold
 * (never name, author, or provenance), type only in a base, sizes, and
 * no credentials.
 */
class Layer
{
    /** Keys a facet or blueprint layer may set. Bases may also set "type". */
    public const KEYS = [
        'directories', 'extra_directories', 'render_dirs', 'packages', 'git',
        'ai', 'scaffold', 'quarto', 'connections', 'env', 'data_hooks', 'security', 'files', 'skills',
    ];

    public const MAX_FILE_BYTES = 64 * 1024;

    public const MAX_LAYER_BYTES = 512 * 1024;

    public const SCHEMA_ID = 'https://framework.pub/schema/layer/v1.json';

    public const ENVELOPE_SCHEMA_ID = 'https://framework.pub/schema/layers/v1.json';

    /** Keys whose values the spec defines as lists, so an empty one stays []. */
    private const LIST_KEYS = ['fragments'];

    private const PATH_PATTERN = '^(?!/)(?!.*(^|/)\\.\\.(/|$))[^\\\\]+$';

    private const CREDENTIAL_KEY = '/(password|secret|token|access_key)/i';

    private const ENV_REFERENCE = '/^env\(\s*["\'][A-Za-z_][A-Za-z0-9_]*["\']\s*\)$/';

    /**
     * Parse YAML into a layer. Empty text is an empty layer.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null} the layer, or an error
     */
    public static function fromYaml(string $yaml): array
    {
        if (trim($yaml) === '') {
            return [[], null];
        }

        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException $e) {
            return [null, $e->getMessage()];
        }

        if (! is_array($parsed) || array_is_list($parsed)) {
            return [null, 'Settings must be a YAML map of keys, such as "directories:".'];
        }

        return [$parsed, null];
    }

    public static function toYaml(array $layer): string
    {
        return $layer === [] ? '' : Yaml::dump($layer, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * @param  'base'|'facet'|'blueprint'  $kind
     * @return list<string> problems, empty when the layer is usable
     */
    public static function problems(array $layer, string $kind): array
    {
        $problems = [];
        $allowed = $kind === 'base' ? ['type', ...self::KEYS] : self::KEYS;

        foreach (array_diff(array_keys($layer), $allowed) as $key) {
            $problems[] = $key === 'type'
                ? 'Only a base sets the project type.'
                : "\"{$key}\" is not a setting a layer can hold.";
        }

        if ($kind === 'base' && ! in_array($layer['type'] ?? null, Project::TYPES, true)) {
            $problems[] = 'A base must set type to one of: '.implode(', ', Project::TYPES).'.';
        }

        $problems = [...$problems, ...self::schemaProblems(array_intersect_key($layer, array_flip($allowed)))];
        $problems = [...$problems, ...self::sizeProblems($layer)];
        $problems = [...$problems, ...self::credentialProblems($layer['connections'] ?? [], 'connections')];
        $problems = [...$problems, ...self::envProblems($layer['env'] ?? [])];

        return array_values(array_unique($problems));
    }

    /**
     * The layer as JSON would carry it. PHP arrays lose the difference
     * between an empty map and an empty list, so empty arrays become
     * objects except under keys the spec defines as lists.
     */
    public static function toJsonValue(mixed $value, ?string $key = null): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return in_array($key, self::LIST_KEYS, true) ? [] : new \stdClass;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => self::toJsonValue($item), $value);
        }

        $object = new \stdClass;
        foreach ($value as $childKey => $child) {
            $object->{$childKey} = self::toJsonValue($child, (string) $childKey);
        }

        return $object;
    }

    /**
     * Problems with a whole layers envelope before it leaves the cloud. The
     * committed .framework/layers.json stays in the repository's history, so
     * every layer in it gets the credential and env checks again.
     *
     * @return list<string>
     */
    public static function envelopeProblems(array $envelope): array
    {
        $problems = self::schemaProblems($envelope, self::ENVELOPE_SCHEMA_ID, 'layers.v1.json');

        foreach ($envelope['layers'] ?? [] as $entry) {
            $layer = is_array($entry['layer'] ?? null) ? $entry['layer'] : [];
            $problems = [...$problems, ...self::credentialProblems($layer['connections'] ?? [], 'connections')];
            $problems = [...$problems, ...self::envProblems($layer['env'] ?? [])];
        }

        return array_values(array_unique($problems));
    }

    /** Shape rules come from the framework package's schemas, pinned in resources/schema. */
    private static function schemaProblems(array $value, string $id = self::SCHEMA_ID, string $file = 'layer.v1.json'): array
    {
        $validator = new Validator;
        $validator->setMaxErrors(20);
        $validator->resolver()->registerFile($id, resource_path("schema/{$file}"));

        $result = $validator->validate(self::toJsonValue($value), $id);

        return $result->isValid() ? [] : array_map(self::describe(...), self::leafErrors($result->error()));
    }

    /** @return list<ValidationError> */
    private static function leafErrors(ValidationError $error): array
    {
        $children = $error->subErrors();
        if ($children === []) {
            // Every nullable value is anyOf [..., null]; its null branch is noise
            return $error->keyword() === 'type' && ($error->args()['expected'] ?? null) === 'null' ? [] : [$error];
        }

        return array_merge(...array_map(self::leafErrors(...), $children));
    }

    private static function describe(ValidationError $error): string
    {
        $path = $error->data()->fullPath();
        $where = preg_replace('/\.(\d+)(?=\.|$)/', '[$1]', implode('.', $path));
        $schema = $error->schema()->info()->data();

        // A skill is either a package reference or SKILL.md text; name it whole
        if (($path[0] ?? null) === 'skills' && isset($path[1])) {
            return "Skill \"{$path[1]}\" needs SKILL.md content, or package: true.";
        }

        return match ($error->keyword()) {
            'pattern' => ($error->args()['pattern'] ?? null) === self::PATH_PATTERN
                ? '"'.(is_string($error->data()->value()) ? $error->data()->value() : $where).'" needs a relative path inside the project.'
                : "\"{$where}\" has an invalid name or value.",
            'enum' => "{$where} must be one of: ".implode(', ', $schema->enum).'.',
            'required' => "{$where} needs ".implode(' and ', $error->args()['missing'] ?? []).'.',
            'type' => "{$where} must be ".self::typeName($error->args()['expected'] ?? $schema->type ?? '').'.',
            'additionalProperties' => '"'.implode('", "', $error->args()['properties'] ?? [])."\" is not a setting {$where} can hold.",
            default => "{$where}: ".(new ErrorFormatter)->formatErrorMessage($error),
        };
    }

    private static function typeName(mixed $type): string
    {
        return match ($type) {
            'object' => 'a map',
            'array' => 'a list',
            'string' => 'text',
            'boolean' => 'true or false',
            'integer' => 'a whole number',
            default => is_array($type) ? implode(' or ', $type) : (string) $type,
        };
    }

    private static function sizeProblems(array $layer): array
    {
        $problems = [];

        foreach (is_array($layer['files'] ?? null) ? $layer['files'] : [] as $path => $file) {
            if (is_string($file['content'] ?? null) && strlen($file['content']) > self::MAX_FILE_BYTES) {
                $problems[] = "File \"{$path}\" is larger than 64 KB.";
            }
        }

        foreach (is_array($layer['skills'] ?? null) ? $layer['skills'] : [] as $name => $skill) {
            if (is_string($skill['content'] ?? null) && strlen($skill['content']) > self::MAX_FILE_BYTES) {
                $problems[] = "Skill \"{$name}\" is larger than 64 KB.";
            }
        }

        if (strlen(json_encode($layer)) > self::MAX_LAYER_BYTES) {
            $problems[] = 'This layer is larger than 512 KB.';
        }

        return $problems;
    }

    /** Credentials never reach the cloud: secret-looking keys must point at an env() variable. */
    private static function credentialProblems(mixed $value, string $path): array
    {
        if (! is_array($value)) {
            return [];
        }

        $problems = [];

        foreach ($value as $key => $child) {
            $here = "{$path}.{$key}";

            if (is_array($child)) {
                $problems = [...$problems, ...self::credentialProblems($child, $here)];
            } elseif (preg_match(self::CREDENTIAL_KEY, (string) $key) && filled($child)
                && ! preg_match(self::ENV_REFERENCE, (string) $child)) {
                $problems[] = "{$here} looks like a credential. Use env(\"VARIABLE\") and keep the value in the project's .env.";
            }
        }

        return $problems;
    }

    /**
     * The env layer becomes the project's .env, so a value there would be a
     * real secret stored in the cloud. Secret-looking variables stay empty.
     */
    private static function envProblems(mixed $env): array
    {
        if (! is_array($env)) {
            return [];
        }

        $problems = [];

        foreach ($env as $name => $value) {
            if (preg_match(self::CREDENTIAL_KEY, (string) $name) && filled($value)) {
                $problems[] = "env.{$name} looks like a secret. Leave it empty here and fill it in the project's .env.";
            }
        }

        return $problems;
    }
}
