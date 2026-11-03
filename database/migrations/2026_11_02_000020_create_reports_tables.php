<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('slug', 120);
            $table->string('key', 32)->unique();
            $table->string('visibility')->default('private');
            $table->string('password_hash')->nullable();
            $table->json('draft_blocks');
            $table->unsignedInteger('next_version_number')->default(1);
            $table->timestamps();
            $table->unique(['project_id', 'slug']);
        });
        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('blocks');
            $table->timestamps();
            $table->unique(['report_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_versions');
        Schema::dropIfExists('reports');
    }
};
