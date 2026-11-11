<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The editor files a blueprint's projects get: rstudio, vscode, both, or none. Null leaves it to the person. */
    public function up(): void
    {
        Schema::table('blueprint_versions', fn (Blueprint $table) => $table->string('ide', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('blueprint_versions', fn (Blueprint $table) => $table->dropColumn('ide'));
    }
};
