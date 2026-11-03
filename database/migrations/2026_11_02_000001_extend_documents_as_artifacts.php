<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('kind')->default('html');
            $table->text('description')->nullable();
            $table->string('original_filename')->nullable();
            $table->json('manifest')->nullable();
            $table->string('password_hash')->nullable();
            $table->unsignedInteger('next_version_number')->default(1);
            $table->timestamp('merged_at')->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('storage_disk')->nullable();
            $table->string('source_path')->nullable();
        });
        Schema::table('document_versions', function (Blueprint $table) {
            $table->string('kind')->default('html');
            $table->string('original_filename')->nullable();
            $table->json('manifest')->nullable();
            $table->json('provenance')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('source_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', fn (Blueprint $table) => $table->dropColumn(['kind', 'original_filename', 'manifest', 'provenance', 'storage_disk', 'source_path']));
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropColumn(['kind', 'description', 'original_filename', 'manifest', 'password_hash', 'next_version_number', 'merged_at', 'storage_disk', 'source_path']);
        });
    }
};
