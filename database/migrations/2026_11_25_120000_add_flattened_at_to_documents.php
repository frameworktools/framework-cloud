<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When an artifact's versions were flattened into one (App\Support\ArtifactFlattener). Old version links then go to the latest. */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('flattened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('flattened_at');
        });
    }
};
