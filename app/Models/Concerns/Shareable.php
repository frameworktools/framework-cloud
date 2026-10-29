<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ownership and visibility for things people share and remix: blueprints,
 * facets, and skills. A null owner marks a system row shipped with
 * Framework. Unlisted rows open by link but stay out of listings.
 */
trait Shareable
{
    public const VISIBILITIES = ['private', 'unlisted', 'public'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    /** Rows the user may open: system, their own, public, or unlisted. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('user_id')->orWhereIn('visibility', ['public', 'unlisted']);

            if ($user) {
                $q->orWhere('user_id', $user->id);
            }
        });
    }

    /** Rows that appear in the user's listings: system, their own, or public. */
    public function scopeListedFor(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('user_id')->orWhere('visibility', 'public');

            if ($user) {
                $q->orWhere('user_id', $user->id);
            }
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->isSystem()
            || in_array($this->visibility, ['public', 'unlisted'], true)
            || ($user && $this->user_id === $user->id);
    }

    /** Owners edit their own rows; admins edit system rows. */
    public function isEditableBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isSystem() ? (bool) $user->is_admin : $this->user_id === $user->id;
    }

    /** The citable name, such as "framework/restricted-data" or "@erik/irb-data". */
    public function getHandleAttribute(): string
    {
        if ($this->isSystem()) {
            return "framework/{$this->key}";
        }

        $owner = $this->owner?->handle;

        return $owner ? "@{$owner}/{$this->key}" : $this->key;
    }
}
