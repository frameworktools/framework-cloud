<?php

namespace App\Models;

use App\Models\Concerns\HasVersions;
use App\Models\Concerns\Shareable;
use App\Support\SkillBundle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A skill in the shared library: a SKILL.md, plus optional files beside it,
 * that an AI assistant loads when its description matches the task. System
 * skills include package bundles and Cloud-only instructions. Released
 * package keys use {package: true}; other skills travel as versioned content.
 */
class Skill extends Model
{
    use HasFactory, HasVersions, Shareable;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Skill $skill) {
            $skill->sync_id ??= $skill->user_id === null ? 'framework:'.$skill->key : (string) Str::uuid();
        });
        static::created(function (Skill $skill) {
            if ($skill->forked_from_id) {
                $parent = SkillVersion::find($skill->forked_from_id)?->skill()->withTrashed()->first();
                $skill->tags()->sync($parent?->tags()->pluck('skill_tags.id')->all() ?? []);
            }
        });
        static::updating(function (Skill $skill) {
            if ($skill->isDirty(['sync_id', 'forked_from_id'])) {
                throw ValidationException::withMessages(['skill' => 'Skill identity and fork ancestry cannot change.']);
            }
        });
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SkillTag::class)->orderBy('position')->orderBy('name');
    }

    protected $guarded = [];

    protected $appends = ['handle'];

    protected function versionModel(): string
    {
        return SkillVersion::class;
    }

    protected function versionContent(): array
    {
        return ['content', 'variants', 'files', 'bundle_files'];
    }

    protected function casts(): array
    {
        return ['language' => 'array'];
    }

    /**
     * The description agents read for each language with its own text,
     * from that text's frontmatter.
     *
     * @return array<string, string>
     */
    public function descriptions(): array
    {
        return collect($this->latestVersion?->variants ?? [])
            ->map(fn (string $content) => rescue(fn () => self::frontmatter($content, 'content')['description'], null, false))
            ->filter()->all();
    }

    /** Whether a project in this language can use the skill; none listed means any. */
    public function fits(string $language): bool
    {
        return ! $this->language || in_array($language, $this->language, true);
    }

    // Keep new keys inline until released clients ship them.
    public const INLINE_PACKAGE_SKILLS = ['framework-cache', 'framework-databases', 'framework-publishing', 'ggplot2-figures'];

    /**
     * A released package bundle is referenced by key for its language.
     * Other skills carry their versioned content, bundle, and sync metadata.
     *
     * @return array<string, mixed>
     */
    public function layerEntry(?string $language = null): array
    {
        // Cloud-only skills travel inline. A package reference is valid only
        // for a bundle shipped in the requested language.
        $registry = File::json(database_path('seeders/masters/skill-bundles.json'));
        if ($this->isSystem() && ! in_array($this->key, self::INLINE_PACKAGE_SKILLS, true) && isset($registry[$this->key][$language ?? 'r'])) {
            return ['package' => true];
        }

        $version = $this->latestVersion;
        $entry = ['content' => $version->contentFor($language)];
        $variant = $language ?? 'r';
        $entry['bundle'] = SkillBundle::files($version, $variant);
        $entry['sync'] = ['skill_id' => $this->sync_id, 'version' => $version->version,
            'hash' => SkillBundle::hash($entry['bundle']), 'variant' => $variant, 'server' => url('/')];

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
     * the name. metadata.summary is a short line for people browsing; the
     * description, which can be long, is what tells an agent to load it.
     *
     * @return array{key: string, description: string, title: string, summary: ?string}
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
        preg_match('/^[ \t]+summary:[ \t]*["\']?(.+?)["\']?[ \t]*$/m', $metadata[1] ?? '', $summary);

        return [
            'key' => $key,
            'description' => trim($description[1]),
            'title' => mb_substr(trim($title[1] ?? '') ?: self::titleFromKey($key), 0, 100),
            'summary' => filled($summary[1] ?? null) ? mb_substr(trim($summary[1]), 0, 200) : null,
        ];
    }

    /** A readable name for a skill whose SKILL.md has no metadata.title: analysis-report reads Analysis report. */
    public static function titleFromKey(string $key): string
    {
        return Str::ucfirst(str_replace('-', ' ', $key));
    }
}
