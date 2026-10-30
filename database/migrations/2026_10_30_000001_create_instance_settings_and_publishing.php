<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This server's own credentials (GitHub App, storage, mail), saved
        // from the admin setup page. Values are encrypted with APP_KEY.
        // Environment variables always win over these rows.
        Schema::create('instance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Hosted publishing is a paid feature; on a self-hosted server
        // everyone can publish
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_publish')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_publish');
        });
        Schema::dropIfExists('instance_settings');
    }
};
