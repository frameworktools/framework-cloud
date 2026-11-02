<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A short line for people, from SKILL.md's metadata.summary; the description is for agents */
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('summary', 200)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('skills', fn (Blueprint $table) => $table->dropColumn('summary'));
    }
};
