<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Set an account's password from the server, for a self-hosted server
 * without email.
 *
 *   php artisan fw:password you@example.edu
 */
class PasswordCommand extends Command
{
    protected $signature = 'fw:password {email : The account email} {--create : Create the account if it does not exist}';

    protected $description = 'Set the password for an account';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $user = User::where('email', $email)->first();

        if (! $user && ! $this->option('create')) {
            $this->error("No account for {$email}. Pass --create to make one.");

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password (at least 10 characters)');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => (string) $this->secret('Repeat the password')],
            ['password' => ['required', 'confirmed', Password::min(10)]],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        if (! $user) {
            $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => null]);
            $user->forceFill(['email_verified_at' => now()]);
            $this->info("Created account {$email}.");
        }

        $user->forceFill(['password' => $password])->save();

        if (! config('framework.passwords')) {
            $this->warn('Password sign-in is off on this server. Set FW_PASSWORDS=true to use it.');
        }

        $this->info("Password set for {$email}.");

        return self::SUCCESS;
    }
}
