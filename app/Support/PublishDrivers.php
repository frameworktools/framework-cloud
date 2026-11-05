<?php

namespace App\Support;

class PublishDrivers
{
    public static function capabilities(string $driver): array
    {
        return match ($driver) {
            'cloud' => ['public' => true, 'unlisted' => true, 'private' => true, 'password' => true, 'expiry' => false, 'versions' => true, 'view_counts' => true],
            'github', 'folder', 's3' => ['public' => true, 'unlisted' => false, 'private' => $driver !== 'github', 'password' => false, 'expiry' => false, 'versions' => true, 'view_counts' => false],
            default => [],
        };
    }

    public static function label(string $driver): string
    {
        return ['cloud' => 'Framework Cloud', 'github' => 'GitHub Pages', 'folder' => 'Folder', 's3' => 'Object storage'][$driver] ?? $driver;
    }
}
