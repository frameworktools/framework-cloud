<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['files' => 'array', 'variants' => 'array'];
    }

    /**
     * The text a project in this language gets: the language's own variant,
     * or the shared text. Framework's skills keep R's text as the shared
     * text and Python's as a variant, as each package ships them.
     */
    public function contentFor(?string $language): string
    {
        return ($language ? ($this->variants[$language] ?? null) : null) ?? $this->content;
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
