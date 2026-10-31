<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facets become typed pieces of a blueprint (docs/plans/2026-10-31-blueprints-
 * structures-settings.md). Bases become project structures. The stock facets
 * become tools, a setting (sensitive data), or retired, since AI assistants
 * and Python are now settings. User facets become legacy: blueprints that
 * already use them keep working, and nobody can add them to new ones.
 *
 * Rows and versions stay, so every pinned blueprint version still resolves.
 */
return new class extends Migration
{
    private const SYSTEM = [
        'renv' => ['tool', 'renv', ['r'], []],
        'targets-pipeline' => ['tool', 'targets', ['r'], []],
        'manuscript' => ['tool', 'quarto_manuscript', ['r', 'python'], ['quarto']],
        'slides' => ['tool', 'quarto_slides', ['r', 'python'], ['quarto']],
        'restricted-data' => ['setting', 'sensitive_data', null, []],
        'claude-code' => ['retired', null, null, []],
        'codex' => ['retired', null, null, []],
        'copilot' => ['retired', null, null, []],
        'python' => ['retired', null, null, []],
    ];

    public function up(): void
    {
        Schema::table('facets', function (Blueprint $table) {
            $table->string('kind', 16)->default('structure')->change();
            $table->jsonb('language')->nullable()->after('kind');
            $table->jsonb('requires')->nullable()->after('language');
        });

        DB::table('facets')->where('kind', 'base')->update(['kind' => 'structure']);

        foreach (self::SYSTEM as $key => [$kind, $newKey, $language, $requires]) {
            DB::table('facets')->whereNull('user_id')->where('key', $key)->update(array_filter([
                'kind' => $kind,
                'key' => $newKey,
                'language' => $language === null ? null : json_encode($language),
                'requires' => $requires ? json_encode($requires) : null,
            ], fn ($value) => $value !== null));
        }

        DB::table('facets')->where('kind', 'facet')->update(['kind' => 'legacy']);
    }

    public function down(): void
    {
        DB::table('facets')->where('kind', 'structure')->update(['kind' => 'base']);
        DB::table('facets')->whereIn('kind', ['tool', 'package_set', 'skill_set', 'setting', 'retired', 'legacy'])->update(['kind' => 'facet']);

        foreach (self::SYSTEM as $key => [, $newKey]) {
            if ($newKey) {
                DB::table('facets')->whereNull('user_id')->where('key', $newKey)->update(['key' => $key]);
            }
        }

        Schema::table('facets', function (Blueprint $table) {
            $table->dropColumn(['language', 'requires']);
        });
    }
};
