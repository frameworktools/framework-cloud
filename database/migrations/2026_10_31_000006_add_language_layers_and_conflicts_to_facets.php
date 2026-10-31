<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tool definitions from the framework R package (inst/features) name the
 * tools they can't be combined with, and may add a layer for one language,
 * such as IRkernel for Jupyter in R projects and ipykernel in Python ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facets', function (Blueprint $table) {
            $table->jsonb('conflicts')->nullable()->after('requires');
        });

        Schema::table('facet_versions', function (Blueprint $table) {
            $table->jsonb('language_layers')->nullable()->after('layer');
        });
    }

    public function down(): void
    {
        Schema::table('facets', function (Blueprint $table) {
            $table->dropColumn('conflicts');
        });

        Schema::table('facet_versions', function (Blueprint $table) {
            $table->dropColumn('language_layers');
        });
    }
};
