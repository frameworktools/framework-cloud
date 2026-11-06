<?php

namespace App\Console\Commands;

use App\Support\TrashPurge;
use Illuminate\Console\Command;

/** Deletes forever what has been in the Trash longer than FW_TRASH_DAYS. Scheduled daily. */
class PurgeTrashCommand extends Command
{
    protected $signature = 'fw:purge-trash {--days= : Override FW_TRASH_DAYS for this run}';

    protected $description = 'Delete forever what has been in the Trash longer than FW_TRASH_DAYS (default 30)';

    public function handle(): int
    {
        if ($this->option('days') !== null) {
            config(['framework.trash_days' => (int) $this->option('days')]);
        }
        if (TrashPurge::days() === 0) {
            $this->info('FW_TRASH_DAYS is 0: the Trash is kept until emptied by hand.');

            return self::SUCCESS;
        }

        $counts = TrashPurge::run();
        $this->info('Deleted forever: '.collect($counts)->map(fn ($n, $kind) => "{$n} {$kind}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
