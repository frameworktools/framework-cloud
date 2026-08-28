<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The /@{handle} profile slug. Citable-ish once shared — treat
            // renames as breaking. Nullable: handles are opt-in.
            $table->string('handle', 30)->nullable()->unique();
            $table->boolean('profile_public')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['handle', 'profile_public']);
        });
    }
};
