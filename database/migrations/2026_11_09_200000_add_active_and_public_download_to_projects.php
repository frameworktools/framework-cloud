<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Inactive projects leave the main Projects list, without being deleted
            $table->boolean('active')->default(true)->after('description');
            // Set while anyone with the link may download the project as a zip
            $table->string('public_download_key', 32)->nullable()->unique()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['public_download_key']);
            $table->dropColumn(['active', 'public_download_key']);
        });
    }
};
