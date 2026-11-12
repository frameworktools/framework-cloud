<?php

namespace App\Support;

use App\Models\FacetVersion;

class PackageSetLayer
{
    public static function rows(FacetVersion $version): array
    {
        if ($version->manual_packages) {
            return array_map(fn ($name) => ['name' => $name, 'source' => 'manual', 'version' => null, 'repo' => null], $version->manual_packages);
        }

        return collect($version->layer['packages']['default_packages'] ?? [])
            ->filter(fn ($entry) => is_array($entry))
            ->map(fn ($entry, $name) => ['name' => $name, 'source' => $entry['source'], 'version' => $entry['version'] ?? null, 'repo' => $entry['repo'] ?? null, 'ref' => $entry['ref'] ?? null])
            ->values()->all();
    }

    /** Package sets define installations; blueprints and projects decide attachment. */
    public static function installOnly(array $layer): array
    {
        foreach ($layer['packages']['default_packages'] ?? [] as $name => $entry) {
            if (is_array($entry)) {
                // Required by the client schema; attachment choices live in later layers.
                $entry['auto_attach'] = false;
                $layer['packages']['default_packages'][$name] = $entry;
            }
        }

        return $layer;
    }
}
