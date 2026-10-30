<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each project has one language, which decides the tool that scaffolds it:
 * the R package for r, the Python package for python, and fw for stata.
 * A blueprint's language is the default for projects made from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('language', 16)->default('r')->after('project_type');
        });

        Schema::table('blueprints', function (Blueprint $table) {
            $table->string('language', 16)->default('r');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('language'));
        Schema::table('blueprints', fn (Blueprint $table) => $table->dropColumn('language'));
    }
};
