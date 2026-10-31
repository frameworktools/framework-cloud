<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's preferred pieces for new projects: package sets, skill sets,
 * tools, and whether projects start sensitive. Kept out of the synced
 * settings document, which the R package reads and validates, until the
 * packages read these through GET /api/v2/defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('project_defaults')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('project_defaults');
        });
    }
};
