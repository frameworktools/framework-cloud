<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ledger entries stay append-only, but account deletion must remove them.
 * A delete passes only when the transaction sets the account deletion flag
 * (App\Support\AccountDeletion) and the entry's project row is already gone,
 * as it is during the cascade from users to projects. Updates always fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger_entries_append_only()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE'
                    AND current_setting('framework.account_deletion', true) = 'on'
                    AND NOT EXISTS (SELECT 1 FROM projects WHERE id = OLD.project_id) THEN
                    RETURN OLD;
                END IF;
                RAISE EXCEPTION 'ledger_entries is append-only';
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger_entries_append_only()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'ledger_entries is append-only';
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }
};
