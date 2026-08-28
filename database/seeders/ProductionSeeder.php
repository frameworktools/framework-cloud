<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production bootstrap: system blueprints + the default admin account.
 *
 * Fully passwordless: the admin account (FW_ADMIN_EMAIL) signs in via magic
 * link — configure mail first, or use `php artisan fw:login-link` — and
 * registers a passkey through the UI after first login.
 *
 * Run with: php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BlueprintSeeder::class);

        // Passwordless: the admin signs in with a magic link (or
        // `php artisan fw:login-link`), then registers a passkey in the UI.
        // Nothing secret is needed at seed time.
        $email = env('FW_ADMIN_EMAIL');

        if (blank($email)) {
            $this->command?->warn('FW_ADMIN_EMAIL not set — blueprints seeded, no admin user created.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => env('FW_ADMIN_NAME', ucfirst(strtok($email, '@'))),
                'password' => null,
            ],
        );

        // is_admin and email_verified_at are guarded against mass assignment
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->command?->info("Admin user {$email} ready (magic-link sign-in).");
    }
}
