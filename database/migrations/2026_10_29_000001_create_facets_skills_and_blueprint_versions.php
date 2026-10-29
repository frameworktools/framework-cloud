<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprints become compositions: a base facet (the project type) plus an
 * ordered stack of facets, each pinned to an immutable version. Skills get a
 * shareable library of their own. Layers are stored unmerged in the draft
 * spec format; the framework R package is the only code that merges them.
 *
 * Owned rows (user_id set) are unique per owner; system rows (user_id null)
 * are unique among themselves through a partial index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('private');
            $table->unsignedBigInteger('forked_from_id')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        Schema::create('skill_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('content');
            $table->jsonb('files')->default('{}');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['skill_id', 'version']);
        });

        Schema::create('facets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('kind', 8)->default('facet'); // base | facet
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 32)->nullable();
            $table->string('question', 200)->nullable();
            $table->string('visibility', 16)->default('private');
            $table->unsignedBigInteger('forked_from_id')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        Schema::create('facet_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('layer')->default('{}');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['facet_id', 'version']);
        });

        Schema::table('blueprints', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('visibility', 16)->default('private')->after('description');
            $table->unsignedBigInteger('forked_from_id')->nullable()->index()->after('visibility');
            $table->dropUnique(['key']);
            $table->unique(['user_id', 'key']);
        });

        Schema::create('blueprint_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('base_version_id')->constrained('facet_versions')->restrictOnDelete();
            $table->jsonb('layer')->default('{}');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['blueprint_id', 'version']);
        });

        Schema::create('blueprint_version_facets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facet_version_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['blueprint_version_id', 'position']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('blueprint_version_id')->nullable()->after('project_type')
                ->constrained()->nullOnDelete();
        });

        foreach (['skills', 'facets', 'blueprints'] as $table) {
            DB::statement("CREATE UNIQUE INDEX {$table}_system_key_unique ON {$table} (key) WHERE user_id IS NULL");
        }

        $this->moveBlueprintsIntoBaseFacets();

        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn(['structure', 'agents_md', 'skills']);
        });
    }

    /**
     * Each existing blueprint was a project type, so it becomes a system base
     * facet whose first version carries what the row held, and the blueprint
     * keeps its key with a version 1 that stands on that base.
     */
    private function moveBlueprintsIntoBaseFacets(): void
    {
        $now = now();

        foreach (DB::table('blueprints')->orderBy('position')->get() as $row) {
            $structure = json_decode($row->structure ?? '{}', true) ?: [];
            $layer = ['type' => $row->key] + array_intersect_key(
                $structure, array_flip(['directories', 'render_dirs', 'quarto']),
            );

            if (filled($row->agents_md)) {
                $layer['files'] = ['AGENTS.md' => ['content' => $row->agents_md, 'template' => true]];
            }

            $skills = json_decode($row->skills ?? '{}', true) ?: [];
            if ($skills !== []) {
                $layer['skills'] = array_map(fn ($content) => ['content' => $content], $skills);
            }

            $facetId = DB::table('facets')->insertGetId([
                'key' => $row->key,
                'kind' => 'base',
                'name' => $row->name,
                'description' => $row->description,
                'visibility' => 'public',
                'position' => $row->position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $baseVersionId = DB::table('facet_versions')->insertGetId([
                'facet_id' => $facetId,
                'version' => 1,
                'layer' => json_encode($layer),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('blueprint_versions')->insert([
                'blueprint_id' => $row->id,
                'version' => 1,
                'base_version_id' => $baseVersionId,
                'layer' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('blueprints')->where('id', $row->id)->update(['visibility' => 'public']);
        }
    }

    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->jsonb('structure')->default('{}');
            $table->text('agents_md')->nullable();
            $table->jsonb('skills')->default('{}');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('blueprint_version_id');
        });

        Schema::dropIfExists('blueprint_version_facets');
        Schema::dropIfExists('blueprint_versions');

        foreach (['skills', 'facets', 'blueprints'] as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_system_key_unique");
        }

        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['visibility', 'forked_from_id']);
            $table->unique(['key']);
        });

        Schema::dropIfExists('facet_versions');
        Schema::dropIfExists('facets');
        Schema::dropIfExists('skill_versions');
        Schema::dropIfExists('skills');
    }
};
