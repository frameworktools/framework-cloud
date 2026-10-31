<?php

namespace App\Services\GitHub;

use App\Models\Facet;
use App\Models\Skill;
use App\Support\Layer;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Moves library items between the cloud and GitHub repositories, in the
 * layout people already use for skills:
 *
 *   skills/<name>/SKILL.md (plus any supporting files)
 *   facets/<key>.facet.yml (name, kind, description, category, question, layer)
 *
 * Imports read any repository the user can see (public ones without a
 * connection). Publishing writes to a repository the Framework app can reach.
 */
class LibraryRepo
{
    public const MAX_SKILL_FILES = 20;

    public const FACET_FORMAT = 1;

    public function __construct(private GitHub $github) {}

    /**
     * Read a GitHub URL: https://github.com/{owner}/{repo}[/(tree|blob)/{ref}/{path}].
     * A ref containing slashes is not supported.
     *
     * @return array{repo: string, ref: ?string, path: string}
     */
    public static function parseUrl(string $url): array
    {
        if (! preg_match('#^https://github\.com/([A-Za-z0-9-]+)/([A-Za-z0-9._-]+?)(?:\.git)?(?:/(?:tree|blob)/([^/]+)(?:/(.*))?)?/?$#', trim($url), $m)) {
            throw ValidationException::withMessages(['url' => 'Paste a github.com link to a folder or file, such as https://github.com/owner/repo/tree/main/skills/my-skill.']);
        }

        return ['repo' => "{$m[1]}/{$m[2]}", 'ref' => $m[3] ?? null ?: null, 'path' => trim($m[4] ?? '', '/')];
    }

    /**
     * A skill folder (or its SKILL.md) as SKILL.md content plus supporting text files.
     *
     * @return array{content: string, files: array<string, string>, skipped: list<string>}
     */
    public function readSkill(string $url): array
    {
        ['repo' => $repo, 'ref' => $ref, 'path' => $path] = self::parseUrl($url);

        if (basename($path) === 'SKILL.md') {
            $path = dirname($path) === '.' ? '' : dirname($path);
        }

        $skill = $this->github->file($repo, ltrim("{$path}/SKILL.md", '/'), $ref)
            ?? throw ValidationException::withMessages(['url' => 'That folder has no SKILL.md, or the repository is private and GitHub is not connected.']);

        $files = [];
        $skipped = [];
        $this->collect($repo, $ref, $path, '', $files, $skipped, depth: 0);

        return ['content' => $skill['content'], 'files' => $files, 'skipped' => $skipped];
    }

    /** @return array{name: string, kind: string, description: ?string, category: ?string, question: ?string, layer: array} */
    public function readFacet(string $url): array
    {
        ['repo' => $repo, 'ref' => $ref, 'path' => $path] = self::parseUrl($url);

        $file = $path !== '' ? $this->github->file($repo, $path, $ref) : null;
        if (! $file) {
            throw ValidationException::withMessages(['url' => 'Link to a .facet.yml file. If the repository is private, connect GitHub first.']);
        }

        try {
            $data = Yaml::parse($file['content']);
        } catch (ParseException $e) {
            throw ValidationException::withMessages(['url' => 'That file is not valid YAML: '.$e->getMessage()]);
        }

        if (! is_array($data) || ($data['framework_facet'] ?? null) !== self::FACET_FORMAT || blank($data['name'] ?? null)) {
            throw ValidationException::withMessages(['url' => 'That file is not a Framework facet. It needs framework_facet: 1, a name, and a layer.']);
        }

        return [
            'name' => (string) $data['name'],
            'kind' => in_array($data['kind'] ?? null, ['base', 'structure'], true) ? 'structure' : 'package_set',
            'description' => $data['description'] ?? null,
            'category' => array_key_exists((string) ($data['category'] ?? ''), Facet::CATEGORIES) ? $data['category'] : null,
            'question' => $data['question'] ?? null,
            'layer' => is_array($data['layer'] ?? null) ? $data['layer'] : [],
        ];
    }

    /** @return string the URL of the published folder */
    public function publishSkill(string $repo, Skill $skill): string
    {
        $version = $skill->latestVersion;
        $dir = "skills/{$skill->key}";
        $message = "Publish skill {$skill->key} v{$version->version} from Framework Cloud";

        $this->github->putFile($repo, "{$dir}/SKILL.md", $version->content, $message);
        foreach ($version->files ?? [] as $path => $content) {
            $this->github->putFile($repo, "{$dir}/{$path}", $content, $message);
        }

        return "https://github.com/{$repo}/tree/HEAD/{$dir}";
    }

    /** @return string the URL of the published file */
    public function publishFacet(string $repo, Facet $facet): string
    {
        $version = $facet->latestVersion;
        $path = "facets/{$facet->key}.facet.yml";

        $document = array_filter([
            'framework_facet' => self::FACET_FORMAT,
            'name' => $facet->name,
            'kind' => $facet->kind,
            'description' => $facet->description,
            'category' => $facet->category,
            'question' => $facet->question,
        ], fn ($value) => $value !== null) + ['layer' => $version->layer];

        $this->github->putFile($repo, $path, Yaml::dump($document, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK), "Publish facet {$facet->key} v{$version->version} from Framework Cloud");

        return "https://github.com/{$repo}/blob/HEAD/{$path}";
    }

    private function collect(string $repo, ?string $ref, string $root, string $relative, array &$files, array &$skipped, int $depth): void
    {
        $entries = $this->github->directory($repo, ltrim("{$root}/{$relative}", '/'), $ref) ?? [];

        foreach ($entries as $entry) {
            $path = ltrim("{$relative}/{$entry['name']}", '/');

            if ($path === 'SKILL.md') {
                continue;
            }
            if ($entry['type'] === 'dir') {
                if ($depth < 2) {
                    $this->collect($repo, $ref, $root, $path, $files, $skipped, $depth + 1);
                }

                continue;
            }
            if ($entry['type'] !== 'file' || $entry['size'] > Layer::MAX_FILE_BYTES || count($files) >= self::MAX_SKILL_FILES) {
                $skipped[] = $path;

                continue;
            }

            $content = $this->github->file($repo, ltrim("{$root}/{$path}", '/'), $ref)['content'] ?? null;

            if ($content === null || ! mb_check_encoding($content, 'UTF-8')) {
                $skipped[] = $path;

                continue;
            }

            $files[$path] = $content;
        }
    }
}
