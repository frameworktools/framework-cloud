<?php

namespace App\Models;

use App\Models\Concerns\HasVersions;
use App\Models\Concerns\Shareable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
