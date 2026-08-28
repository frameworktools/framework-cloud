<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('kind')->default('global');
            $table->jsonb('document');
            $table->string('schema_version')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'kind']);
        });

        Schema::create('setting_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->jsonb('document');
            $table->string('hash', 64);
            $table->jsonb('client')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['setting_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_revisions');
        Schema::dropIfExists('settings');
    }
};
