<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deleted artifacts and versions go to the Trash with their files, until restored or deleted forever
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('document_versions', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('document_versions', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('documents', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
