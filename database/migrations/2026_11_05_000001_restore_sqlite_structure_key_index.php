<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite table rebuilds can lose a partial index's WHERE clause.
        // Personal copies may share a key with Framework's system structure.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS facets_system_key_unique');
            DB::statement('CREATE UNIQUE INDEX facets_system_key_unique ON facets (key) WHERE user_id IS NULL');
        }
    }

    public function down(): void
    {
        // Keep the intended constraint when rolling back this repair.
    }
};
