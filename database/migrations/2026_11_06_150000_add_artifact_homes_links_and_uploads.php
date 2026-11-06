<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artifacts without a project: each account gets an automatic home project
 * (is_home) for them, hidden from project lists. Any artifact can also be
 * linked to other projects, which then list it and may use it in reports.
 * Upload links let an agent send a large file with curl instead of in a call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_home')->default(false);
        });

        Schema::create('document_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'project_id']);
            $table->index('project_id');
        });

        Schema::create('artifact_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mcp_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->json('options');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_uploads');
        Schema::dropIfExists('document_project');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('is_home'));
    }
};
