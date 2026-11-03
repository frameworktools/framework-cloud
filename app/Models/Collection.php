<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    // A shared link can stop working at a set time; the owner can still open it
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class)->orderBy('position')->orderBy('id');
    }

    public function shareUrl(): string
    {
        return route('collections.show', ['key' => $this->key]);
    }

    public function accessStamp(): string
    {
        return hash_hmac('sha256', $this->key.'|'.$this->visibility.'|'.$this->password_hash, config('app.key'));
    }
}
