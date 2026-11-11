<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprint_versions', fn (Blueprint $table) => $table->json('agent_defaults')->nullable());
    }

    public function down(): void
    {
        Schema::table('blueprint_versions', fn (Blueprint $table) => $table->dropColumn('agent_defaults'));
    }
};
