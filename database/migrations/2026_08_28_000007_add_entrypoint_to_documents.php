<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Bundles: disk_path is a directory prefix and entrypoint is the
            // main HTML file inside it. Single files: entrypoint stays null.
            $table->string('entrypoint')->nullable()->after('disk_path');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('entrypoint');
        });
    }
};
