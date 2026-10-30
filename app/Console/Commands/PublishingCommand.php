<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Turn hosted publishing on or off for an account, until billing exists.
 * Only matters with FW_MODE=hosted; a self-hosted server lets everyone publish.
 *
 *   php artisan fw:publishing erik@lab.edu
 *   php artisan fw:publishing erik@lab.edu --off
 */
class PublishingCommand extends Command
{
    protected $signature = 'fw:publishing {email : The account email} {--off : Turn publishing off instead}';

    protected $description = 'Allow (or stop) an account publishing documents on the hosted server';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error('No account for that email.');

            return self::FAILURE;
        }

        $user->forceFill(['can_publish' => ! $this->option('off')])->save();

        $this->info($user->can_publish ? "{$user->email} can publish." : "{$user->email} can no longer publish. Existing documents stay up.");

        if (config('framework.mode') !== 'hosted') {
            $this->line('This server is self-hosted (FW_MODE is not "hosted"), so everyone can publish regardless.');
        }

        return self::SUCCESS;
    }
}
