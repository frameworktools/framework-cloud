<?php

namespace App\Console\Commands;

use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Homelab affordance: a single-user self-hosted instance has no reason to
 * configure SMTP just to log itself in. Prints a magic link to the console.
 *
 *   php artisan fw:login-link erik@lab.edu
 */
class LoginLinkCommand extends Command
{
    protected $signature = 'fw:login-link {email : The account email (created if missing with --create)} {--create : Create the account if it does not exist}';

    protected $description = 'Print a one-time sign-in link for an account (no SMTP required)';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! $this->option('create')) {
                $this->error("No account for {$email}. Pass --create to make one.");

                return self::FAILURE;
            }

            $user = User::create([
                'name' => ucfirst(strtok($email, '@')),
                'email' => $email,
                'password' => null,
            ]);
            $this->info("Created account {$email}.");
        }

        $url = route('magic-link.consume', ['token' => LoginToken::issue($user)]);

        $this->line('');
        $this->info('Sign-in link (single use, expires in '.LoginToken::LIFETIME_MINUTES.' minutes):');
        $this->line('  '.$url);
        $this->line('');

        return self::SUCCESS;
    }
}
