<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The repository a queued scaffold will create (owner/name), so the project
 * reports it before the job has created it and set github_repo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('scaffold_repo')->nullable()->after('scaffold_run_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('scaffold_repo'));
    }
};
