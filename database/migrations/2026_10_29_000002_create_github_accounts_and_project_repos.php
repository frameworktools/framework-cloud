<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One GitHub identity per user, through the Framework GitHub App.
        // Tokens are encrypted at rest and expire (user tokens: 8 hours,
        // refresh tokens: 6 months).
        Schema::create('github_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id')->unique();
            $table->string('login');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('github_repo')->nullable()->after('blueprint_version_id');
            $table->unsignedBigInteger('github_repo_id')->nullable()->index()->after('github_repo');
            $table->boolean('github_private')->nullable()->after('github_repo_id');
            // pending → running → done | failed, while the repo's scaffold workflow runs
            $table->string('scaffold_status')->nullable()->after('github_private');
            $table->string('scaffold_run_url')->nullable()->after('scaffold_status');
            $table->text('scaffold_error')->nullable()->after('scaffold_run_url');
        });

        // Where an imported skill or facet came from on GitHub
        foreach (['skills', 'facets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('source_url')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['skills', 'facets'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('source_url'));
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['github_repo_id']);
            $table->dropColumn(['github_repo', 'github_repo_id', 'github_private', 'scaffold_status', 'scaffold_run_url', 'scaffold_error']);
        });

        Schema::dropIfExists('github_accounts');
    }
};
