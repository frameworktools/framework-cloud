<?php

namespace App\Services\GitHub;

use App\Models\GitHubAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Keeps a GitHub App user token usable. Tokens last 8 hours; the refresh
 * token (6 months) trades for a new pair, and the old refresh token stops
 * working, so refreshes are serialized per account.
 */
class Tokens
{
    public static function fresh(GitHubAccount $account): string
    {
        if (! self::expiresSoon($account)) {
            return $account->access_token;
        }

        return DB::transaction(function () use ($account) {
            $locked = GitHubAccount::lockForUpdate()->findOrFail($account->id);

            // Another request may have refreshed while this one waited
            if (! self::expiresSoon($locked)) {
                $account->setRawAttributes($locked->getAttributes(), true);

                return $locked->access_token;
            }

            if (! $locked->refresh_token || $locked->refresh_token_expires_at?->isPast()) {
                throw new GitHubNotConnected('Your GitHub connection has expired. Connect GitHub again.');
            }

            $response = Http::acceptJson()->asForm()->post('https://github.com/login/oauth/access_token', [
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $locked->refresh_token,
            ]);

            if ($response->failed() || $response->json('error') || ! $response->json('access_token')) {
                throw new GitHubNotConnected('GitHub did not renew the connection. Connect GitHub again.');
            }

            $locked->fillTokens(
                $response->json('access_token'),
                $response->json('refresh_token'),
                $response->json('expires_in'),
                $response->json('refresh_token_expires_in'),
            )->save();

            $account->setRawAttributes($locked->getAttributes(), true);

            return $locked->access_token;
        });
    }

    private static function expiresSoon(GitHubAccount $account): bool
    {
        return $account->token_expires_at !== null && $account->token_expires_at->lt(now()->addMinutes(5));
    }
}
