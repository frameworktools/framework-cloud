<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What an embedded artifact shows: the settings that differ from the default (App\Support\ArtifactDisplay). */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->json('display')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('display');
        });
    }
};
