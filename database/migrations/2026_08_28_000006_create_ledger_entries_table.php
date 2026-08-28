<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->string('algo', 20)->default('sha256');
            $table->string('hash', 128);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->jsonb('client')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['project_id', 'sequence']);
            $table->index(['project_id', 'name']);
        });

        // Append-only at the database level too, where the driver allows it.
        // (App role grants are revoked in production per the deploy runbook;
        // this trigger is belt-and-braces on Postgres.)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION ledger_entries_append_only()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'ledger_entries is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER ledger_entries_no_update
                    BEFORE UPDATE OR DELETE ON ledger_entries
                    FOR EACH ROW EXECUTE FUNCTION ledger_entries_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->unprepared(
                'DROP TRIGGER IF EXISTS ledger_entries_no_update ON ledger_entries; '
                .'DROP FUNCTION IF EXISTS ledger_entries_append_only();'
            );
        }
        Schema::dropIfExists('ledger_entries');
    }
};
