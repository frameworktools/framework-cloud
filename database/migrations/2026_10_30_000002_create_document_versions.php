<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each publish of a document becomes a numbered version. The documents row
 * keeps mirroring the newest version's storage fields, so existing readers
 * of disk_path and entrypoint keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('disk_path');
            $table->string('entrypoint')->nullable();
            $table->string('content_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->unique(['document_id', 'number']);
        });

        // Existing documents become version 1
        DB::table('documents')->orderBy('id')->each(function ($document) {
            DB::table('document_versions')->insert([
                'document_id' => $document->id,
                'number' => 1,
                'disk_path' => $document->disk_path,
                'entrypoint' => $document->entrypoint,
                'content_type' => $document->content_type,
                'size_bytes' => $document->size_bytes,
                'created_at' => $document->updated_at,
                'updated_at' => $document->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
