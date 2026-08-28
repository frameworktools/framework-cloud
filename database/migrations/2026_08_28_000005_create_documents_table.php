<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32)->unique();
            $table->string('slug');
            $table->string('title')->nullable();
            $table->string('content_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('disk_path');
            $table->string('visibility')->default('unlisted');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
