<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A short address, framework.pub/go/{code}, for something people open from a
 * slide or a projector. Codes are four characters from an alphabet without
 * look-alikes (no 0, o, 1, l, i), as on academic.page, and lookups ignore
 * case, spaces, and dashes in a typed code.
 */
class ShortLink extends Model
{
    public const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    public const LENGTH = 4;

    /** Words that are routes or would mislead. */
    public const RESERVED = ['go', 'new', 'admin', 'api', 'app', 'login', 'logout', 'settings', 'account', 'help', 'docs', 'about', 'framework', 'framework-cloud', 'support', 'security', 'qr'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['custom' => 'boolean'];
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The made code for a model, created the first time it's asked for. */
    public static function for(Model $model): self
    {
        $existing = static::where('linkable_type', $model->getMorphClass())->where('linkable_id', $model->getKey())
            ->where('custom', false)->first();
        if ($existing) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::LENGTH + intdiv($attempt, 10); $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            try {
                // A savepoint, so a taken code doesn't abort a surrounding Postgres transaction
                return DB::transaction(fn () => static::create(['code' => $code, 'linkable_type' => $model->getMorphClass(), 'linkable_id' => $model->getKey()]));
            } catch (QueryException) {
                // Taken: try another
            }
        }

        throw new \RuntimeException('Could not make a short link.');
    }

    /** A typed code as stored: lowercase, without spaces or dashes for made codes. */
    public static function lookup(string $typed): ?self
    {
        $word = Str::lower(trim($typed));
        $compact = preg_replace('/[\s\-]+/', '', $word);

        // The word as typed first, so yale-ai doesn't open yaleai
        return static::with('linkable')->where('code', $word)->first()
            ?? ($compact !== $word ? static::with('linkable')->where('code', $compact)->first() : null);
    }

    public function url(): string
    {
        return route('go.show', $this->code);
    }

    /** The address as shown on screen, without the scheme. */
    public function display(): string
    {
        return preg_replace('#^https?://#', '', $this->url());
    }
}
