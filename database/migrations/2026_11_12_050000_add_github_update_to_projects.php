<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Update on GitHub: when the project's setup last changed, when that setup
 * last went to its repository, and how the latest update went.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('setup_updated_at')->nullable()->after('choices');
            $table->timestamp('github_setup_applied_at')->nullable()->after('setup_updated_at');
            $table->json('github_update')->nullable()->after('github_setup_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['setup_updated_at', 'github_setup_applied_at', 'github_update']));
    }
};
