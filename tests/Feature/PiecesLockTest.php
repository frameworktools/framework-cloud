<?php

namespace Tests\Feature;

use App\Console\Commands\SyncPiecesCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Framework's tools and sets are copied from the framework R package by
 * `php artisan fw:sync-pieces`. A copy edited by hand no longer matches its
 * lock entry.
 */
class PiecesLockTest extends TestCase
{
    public function test_the_copied_tools_and_sets_match_their_lock_entries(): void
    {
        $lock = json_decode(File::get(SyncPiecesCommand::dir().'/lock.json'), true);
        $copied = collect(File::allFiles(SyncPiecesCommand::dir()))
            ->map(fn ($file) => $file->getRelativePathname())
            ->reject(fn ($path) => $path === 'lock.json')
            ->sort()->values()->all();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $lock['commit']);
        $this->assertSame(array_keys($lock['files']), $copied, 'Every copied file has a lock entry, and nothing else is there.');

        foreach ($lock['files'] as $path => $entry) {
            $this->assertSame($entry['sha256'], hash_file('sha256', SyncPiecesCommand::dir()."/{$path}"), "masters/r/{$path} differs from its lock entry. Copy it again with fw:sync-pieces.");
        }
    }
}
