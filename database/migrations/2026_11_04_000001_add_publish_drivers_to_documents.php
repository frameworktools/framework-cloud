<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['documents', 'document_versions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('driver', 20)->default('cloud');
                $table->text('external_url')->nullable();
                $table->string('checksum', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['documents', 'document_versions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['driver', 'external_url', 'checksum']));
        }
    }
};
