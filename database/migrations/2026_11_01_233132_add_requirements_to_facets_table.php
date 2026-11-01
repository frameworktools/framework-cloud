<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a piece needs outside the project, such as Java or a C++
     * toolchain, which Framework doesn't install.
     */
    public function up(): void
    {
        Schema::table('facets', function (Blueprint $table) {
            $table->text('requirements')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('facets', function (Blueprint $table) {
            $table->dropColumn('requirements');
        });
    }
};
