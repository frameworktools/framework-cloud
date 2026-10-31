<?php

namespace App\Support;

use App\Models\User;
use App\Services\GitHub\Scaffolder;
use Illuminate\Support\Arr;

/**
 * What a new account and this server still need. Server administration
 * shows both.
 */
class GettingStarted
{
    /** @return list<array{key: string, done: bool}> */
    public static function steps(User $user): array
    {
        $author = $user->globalSettings()->document;

        return array_values(array_filter([
            ['key' => 'profile', 'done' => filled(Arr::get($author, 'author.affiliations')) || filled(Arr::get($author, 'author.affiliation'))],
            ['key' => 'machine', 'done' => $user->tokens()->exists()],
            Scaffolder::enabled() ? ['key' => 'github', 'done' => $user->githubAccount()->exists()] : null,
            ['key' => 'project', 'done' => $user->projects()->exists()],
        ]));
    }

    /** @return list<string> */
    public static function serverNeeds(): array
    {
        return array_values(array_filter([
            in_array(config('mail.default'), ['log', 'array'], true) ? 'mail' : null,
            Scaffolder::enabled() ? null : 'github',
        ]));
    }
}
