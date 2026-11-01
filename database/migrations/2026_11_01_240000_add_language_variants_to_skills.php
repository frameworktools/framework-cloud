<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A skill names the languages it's for (none means any), and a version
     * can hold its text for each language that needs its own, such as
     * Python's copy of a Framework skill.
     */
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->json('language')->nullable()->after('description');
        });
        Schema::table('skill_versions', function (Blueprint $table) {
            $table->json('variants')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('skills', fn (Blueprint $table) => $table->dropColumn('language'));
        Schema::table('skill_versions', fn (Blueprint $table) => $table->dropColumn('variants'));
    }
};
