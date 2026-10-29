<?php

namespace App\Services\GitHub;

use App\Models\GitHubAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

/**
 * Where a user can create repositories: the accounts and organizations that
 * have the Framework app installed. Read from GitHub and cached briefly.
 */
class Installations
{
    /**
     * @return list<array{id: int, login: string, type: string, repository_selection: string}>|null null when GitHub can't be reached
     */
    public static function for(GitHubAccount $account, bool $fresh = false): ?array
    {
        $key = "github:installations:{$account->id}";

        if ($fresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, now()->addMinutes(5), fn () => GitHub::for($account)->installations());
        } catch (RequestException|ConnectionException|GitHubNotConnected) {
            return null;
        }
    }

    public static function installUrl(): ?string
    {
        $slug = config('services.github.app_slug');

        return $slug ? "https://github.com/apps/{$slug}/installations/new" : null;
    }
}
