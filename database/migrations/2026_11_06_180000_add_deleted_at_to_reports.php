<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deleted reports go to the Trash, where they can be restored, like projects and skills
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('reports', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
