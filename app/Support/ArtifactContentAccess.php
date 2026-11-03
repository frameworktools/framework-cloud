<?php

namespace App\Support;

use App\Models\DocumentVersion;

/** Short-lived, version-scoped access for resources in an opaque sandbox. */
class ArtifactContentAccess
{
    private const LIFETIME_SECONDS = 3600;

    public static function issue(DocumentVersion $version): string
    {
        abort_unless($version->document->canRead(), 403, 'Enter the artifact password on its page.');
        $expires = now()->timestamp + self::LIFETIME_SECONDS;

        return $expires.'.'.self::signature($version, $expires);
    }

    public static function accepts(DocumentVersion $version, string $access): bool
    {
        if (! preg_match('/^([0-9]{1,12})\.([a-f0-9]{64})$/D', $access, $parts)) {
            return false;
        }
        $expires = (int) $parts[1];

        return $expires > now()->timestamp && hash_equals(self::signature($version, $expires), $parts[2]);
    }

    private static function signature(DocumentVersion $version, int $expires): string
    {
        $scope = json_encode([
            'artifact-content-v1', $version->document->id, $version->document->key,
            (int) $version->number, $version->disk(), $version->disk_path,
            $version->document->password_hash, $expires,
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $scope, config('app.key'));
    }
}
