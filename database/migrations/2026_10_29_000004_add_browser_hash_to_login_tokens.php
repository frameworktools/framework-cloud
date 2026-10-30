<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_tokens', function (Blueprint $table) {
            // SHA-256 of a random value kept in a cookie in the browser that
            // asked for the link; opening the link there signs in directly
            $table->string('browser_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('login_tokens', function (Blueprint $table) {
            $table->dropColumn('browser_hash');
        });
    }
};
