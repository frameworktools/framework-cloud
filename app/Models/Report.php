<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Report extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['draft_blocks' => 'array', 'next_version_number' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class)->orderByDesc('number');
    }

    public function shareUrl(): string
    {
        return route('reports.show', ['key' => $this->key]);
    }

    public function isOwner(?Request $request = null): bool
    {
        $user = ($request ?? request())->user();

        return $user instanceof User && $user->id === $this->project?->user_id;
    }

    public function accessStamp(): string
    {
        return hash_hmac('sha256', $this->key.'|'.$this->visibility.'|'.$this->password_hash, config('app.key'));
    }

    public function passwordSessionKey(): string
    {
        return 'reports.'.$this->id;
    }

    public function canRead(?Request $request = null): bool
    {
        $request ??= request();
        if ($this->isOwner($request)) {
            return true;
        }
        if ($this->visibility === 'private') {
            return false;
        }

        return ! $this->password_hash || ($request->hasSession()
            && hash_equals($this->accessStamp(), (string) $request->session()->get($this->passwordSessionKey(), '')));
    }
}
