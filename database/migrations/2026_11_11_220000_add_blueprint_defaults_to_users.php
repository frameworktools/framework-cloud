<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's default blueprint for each language, and their default
 * language: {"language": "r", "blueprints": {"r": 12, "python": 3}}.
 * Everything else about a new project comes from the blueprint. The older
 * users.project_defaults column stays, unread except by fw:convert-defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('blueprint_defaults')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('blueprint_defaults');
        });
    }
};
