<?php

namespace App\Support;

use App\Models\FacetVersion;

class PackageSetLayer
{
    public static function rows(FacetVersion $version): array
    {
        return collect($version->layer['packages']['default_packages'] ?? [])
            ->filter(fn ($entry) => is_array($entry))
            ->map(fn ($entry, $name) => ['name' => $name, 'source' => $entry['source'], 'version' => $entry['version'] ?? null, 'repo' => $entry['repo'] ?? null, 'ref' => $entry['ref'] ?? null, 'url' => $entry['url'] ?? null])
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
