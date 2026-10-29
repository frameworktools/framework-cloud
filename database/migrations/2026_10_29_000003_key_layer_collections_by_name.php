<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec schema v1 (framework 93085bb) keys three collections by name, so a
 * later layer can remove an entry with null:
 *
 *   extra_directories   [{key, label, path}]  → {key: {label, path}}
 *   packages.default_packages  [{name, ...}]  → {name: {...}}
 *   ai.assistants       ["claude"]            → {claude: true}
 *
 * Stored versions are rewritten in place. Nothing has been released on the
 * list shapes, and a version's meaning is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['facet_versions', 'blueprint_versions'] as $table) {
            DB::table($table)->orderBy('id')->each(function (object $row) use ($table) {
                $layer = json_decode($row->layer ?? 'null', true);

                if (is_array($layer) && ($converted = self::convert($layer)) !== $layer) {
                    DB::table($table)->where('id', $row->id)->update(['layer' => json_encode($converted)]);
                }
            });
        }
    }

    public function down(): void
    {
        // The list shapes are no longer valid against the pinned schema
    }

    private static function convert(array $layer): array
    {
        if (isset($layer['extra_directories']) && array_is_list($layer['extra_directories'])) {
            $layer['extra_directories'] = self::keyBy($layer['extra_directories'], 'key');
        }

        if (isset($layer['packages']['default_packages']) && array_is_list($layer['packages']['default_packages'])) {
            $layer['packages']['default_packages'] = self::keyBy($layer['packages']['default_packages'], 'name');
        }

        if (isset($layer['ai']['assistants']) && array_is_list($layer['ai']['assistants'])) {
            $layer['ai']['assistants'] = array_fill_keys($layer['ai']['assistants'], true);
        }

        return $layer;
    }

    private static function keyBy(array $entries, string $field): array
    {
        $keyed = [];

        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry[$field])) {
                $keyed[$entry[$field]] = array_diff_key($entry, [$field => true]);
            }
        }

        return $keyed;
    }
};
