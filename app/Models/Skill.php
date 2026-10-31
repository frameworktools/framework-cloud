<?php

namespace App\Models;

use App\Models\Concerns\HasVersions;
use App\Models\Concerns\Shareable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A skill in the shared library: a SKILL.md, plus optional files beside it,
 * that an AI assistant loads when its description matches the task. System
 * skills mirror the ones shipped with the framework R package, so facets
 * reference them as {package: true} rather than copying their text.
 */
class Skill extends Model
{
    use HasFactory, HasVersions, Shareable;

    protected $guarded = [];

    protected $appends = ['handle'];

    protected function versionModel(): string
    {
        return SkillVersion::class;
    }

    protected function versionContent(): array
    {
        return ['content', 'files'];
    }

    /**
     * The entry this skill contributes to a layer's skills map. System skills
     * point at the package's copy; everything else travels as a copy of its
     * current version.
     *
     * @return array<string, mixed>
     */
    public function layerEntry(): array
    {
        if ($this->isSystem()) {
            return ['package' => true];
        }

        $version = $this->latestVersion;
        $entry = ['content' => $version->content];

        if (! empty($version->files)) {
            $entry['files'] = $version->files;
        }

        return $entry;
    }

    /**
     * The name and description from SKILL.md frontmatter, which is what
     * assistants read, and the readable title people see. The name is the
     * skill's official id, such as framework-data; the title, such as
     * "Reading and saving data", comes from metadata.title and falls back to
     * the name.
     *
     * @return array{key: string, description: string, title: string}
     */
    public static function frontmatter(string $content, string $field): array
    {
        if (! preg_match('/\A---\R(.*?)\R---/s', $content, $frontmatter)) {
            throw ValidationException::withMessages([$field => 'Start SKILL.md with frontmatter: a --- line, name: and description:, then another --- line.']);
        }

        preg_match('/^name:\s*["\']?([^"\'\r\n]+?)["\']?\s*$/m', $frontmatter[1], $name);
        preg_match('/^description:\s*(.+)$/m', $frontmatter[1], $description);
        $key = $name[1] ?? '';

        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $key)) {
            throw ValidationException::withMessages([$field => 'The frontmatter name must be lowercase letters, numbers, and hyphens.']);
        }
        if (blank($description[1] ?? null)) {
            throw ValidationException::withMessages([$field => 'Add a description: assistants use it to decide when to load the skill.']);
        }

        preg_match('/^metadata:[ \t]*\R((?:[ \t]+\S.*(?:\R|$))+)/m', $frontmatter[1], $metadata);
        preg_match('/^[ \t]+title:[ \t]*["\']?(.+?)["\']?[ \t]*$/m', $metadata[1] ?? '', $title);

        return [
            'key' => $key,
            'description' => trim($description[1]),
            'title' => mb_substr(trim($title[1] ?? '') ?: $key, 0, 100),
        ];
    }
}
