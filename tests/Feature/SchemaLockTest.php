<?php

namespace Tests\Feature;

use App\Console\Commands\SyncSchemasCommand;
use Tests\TestCase;

/**
 * The schemas are copied from the framework R package by
 * `php artisan fw:sync-schemas`. A copy edited by hand no longer matches
 * its lock entry.
 */
class SchemaLockTest extends TestCase
{
    public function test_the_copied_schemas_match_their_lock_entries(): void
    {
        $lock = json_decode(file_get_contents(resource_path(SyncSchemasCommand::LOCK)), true);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $lock['commit']);
        $this->assertSame(array_keys(SyncSchemasCommand::FILES), array_keys($lock['files']));

        foreach ($lock['files'] as $name => $entry) {
            $this->assertSame(SyncSchemasCommand::FILES[$name], $entry['source'], "{$name} source");
            $this->assertSame($entry['sha256'], hash_file('sha256', resource_path("schema/{$name}")), "resources/schema/{$name} differs from its lock entry. Copy it again with fw:sync-schemas.");
        }
    }
}
