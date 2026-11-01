<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprints become publishable recipes: a license, discovery tags, and a
 * draft or published status on the blueprint; on each version, the
 * languages and workflows it supports, its default workflow per language,
 * the tools it needs, and values it requires. Projects keep the envelope
 * they were resolved from, so later changes to defaults leave them alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->string('license', 64)->nullable()->after('description');
            $table->json('tags')->nullable()->after('license');
            $table->string('status', 16)->default('published')->after('visibility');
        });

        Schema::table('blueprint_versions', function (Blueprint $table) {
            $table->json('languages')->nullable()->after('layer');
            $table->json('workflows')->nullable()->after('languages');
            $table->json('default_workflow')->nullable()->after('workflows');
            $table->json('requires_tools')->nullable()->after('default_workflow');
            $table->json('requires')->nullable()->after('requires_tools');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->longText('resolved')->nullable()->after('composition');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('resolved'));
        Schema::table('blueprint_versions', fn (Blueprint $table) => $table->dropColumn(['languages', 'workflows', 'default_workflow', 'requires_tools', 'requires']));
        Schema::table('blueprints', fn (Blueprint $table) => $table->dropColumn(['license', 'tags', 'status']));
    }
};
