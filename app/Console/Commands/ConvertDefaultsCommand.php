<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DefaultsConversion;
use Illuminate\Console\Command;

/**
 * Turns each account's own default settings into personal blueprints, one
 * per language the account uses, and makes them its defaults. Accounts
 * already converted are skipped, so it is safe to run again.
 */
class ConvertDefaultsCommand extends Command
{
    protected $signature = 'fw:convert-defaults
        {--email= : Convert only this account}
        {--force : Convert accounts again, for languages without a default blueprint}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Convert account default settings into personal default blueprints';

    public function handle(): int
    {
        $users = User::query()->when($this->option('email'), fn ($q, $email) => $q->where('email', $email))->orderBy('id');
        if ($this->option('email') && ! $users->exists()) {
            $this->error('No account has that email.');

            return self::FAILURE;
        }

        foreach ($users->lazy() as $user) {
            $result = DefaultsConversion::convert($user, (bool) $this->option('force'), (bool) $this->option('dry-run'));
            if ($result['skipped']) {
                $this->line("{$user->email}: already converted");

                continue;
            }
            $summary = collect($result['languages'])->map(fn (string $outcome, string $language) => "{$language} {$outcome}")->implode(', ');
            $this->line("{$user->email}: ".($summary ?: 'nothing to convert'));
        }

        return self::SUCCESS;
    }
}
