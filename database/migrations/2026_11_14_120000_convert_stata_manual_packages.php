<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stata package sets kept bare names in manual_packages, because Stata could
 * not install packages. Framework for Stata's fw_packages installs them now,
 * so each name becomes an SSC entry in the version's layer, as R and Python
 * sets store theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('facet_versions')->whereNotNull('manual_packages')->orderBy('id')->each(function ($row) {
            $names = json_decode($row->manual_packages, true) ?: [];
            $layer = json_decode($row->layer ?? '[]', true) ?: [];
            foreach ($names as $name) {
                $layer['packages']['default_packages'][$name] = ['source' => 'ssc', 'auto_attach' => false];
            }
            DB::table('facet_versions')->where('id', $row->id)->update(['layer' => json_encode($layer), 'manual_packages' => null]);
        });
    }

    public function down(): void
    {
        $stata = DB::table('facets')->where('kind', 'package_set')->whereJsonContains('language', 'stata')->pluck('id');
        DB::table('facet_versions')->whereIn('facet_id', $stata)->orderBy('id')->each(function ($row) {
            $layer = json_decode($row->layer ?? '[]', true) ?: [];
            $names = array_keys(array_filter($layer['packages']['default_packages'] ?? [], fn ($entry) => ($entry['source'] ?? null) === 'ssc'));
            if (! $names) {
                return;
            }
            unset($layer['packages']);
            DB::table('facet_versions')->where('id', $row->id)->update(['layer' => json_encode((object) $layer), 'manual_packages' => json_encode($names)]);
        });
    }
};
