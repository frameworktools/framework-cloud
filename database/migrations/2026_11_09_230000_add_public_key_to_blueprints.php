<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The address of a public or unlisted blueprint's page, /b/{public_key}.
        // Random, so an unlisted blueprint can't be found by counting ids.
        Schema::table('blueprints', function (Blueprint $table) {
            $table->string('public_key', 24)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropUnique(['public_key']);
            $table->dropColumn('public_key');
        });
    }
};
