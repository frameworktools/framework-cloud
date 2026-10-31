<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project made from changed choices (a blueprint with tools switched, or
 * pieces picked one by one) records exactly what it was made from: the
 * structure version, the piece versions in order, and the settings layer.
 * A project made from an unchanged blueprint keeps only blueprint_version_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->jsonb('composition')->nullable()->after('blueprint_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('composition');
        });
    }
};
