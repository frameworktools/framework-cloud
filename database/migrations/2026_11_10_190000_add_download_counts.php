<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('downloads')->default(0);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('public_downloads')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('downloads'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('public_downloads'));
    }
};
