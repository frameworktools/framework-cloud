<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32)->unique();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('visibility')->default('private');
            $table->string('password_hash')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'slug']);
        });
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->text('caption')->nullable();
            $table->boolean('share_unlisted')->default(false);
            $table->timestamps();
            $table->unique(['collection_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
    }
};
