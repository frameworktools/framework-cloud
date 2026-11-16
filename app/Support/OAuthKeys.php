<?php

namespace App\Support;

use Laravel\Passport\Passport;
use RuntimeException;

/**
 * The OAuth signing keys for connected AI apps (/mcp). PASSPORT_PRIVATE_KEY
 * and PASSPORT_PUBLIC_KEY win; otherwise the pair lives in storage/ and is
 * made the first time OAuth needs it, so a manual install works without
 * running passport:keys. The Docker entrypoint makes the same files at start.
 */
class OAuthKeys
{
    public static function ensure(): void
    {
        if (filled(config('passport.private_key'))) {
            return;
        }

        $private = Passport::keyPath('oauth-private.key');
        $public = Passport::keyPath('oauth-public.key');

        if (is_file($private) && is_file($public)) {
            return;
        }

        // One process makes the pair; others wait and then find it
        $lock = fopen(storage_path('framework/cache/oauth-keys.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock storage/framework/cache to make the OAuth keys.');
        }

        try {
            if (is_file($private) && is_file($public)) {
                return;
            }

            $key = openssl_pkey_new(['private_key_bits' => 4096, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if ($key === false || ! openssl_pkey_export($key, $privatePem)) {
                throw new RuntimeException('Could not make the OAuth keys: '.openssl_error_string());
            }

            // The same modes passport:keys sets
            self::write($public, openssl_pkey_get_details($key)['key'], 0660);
            self::write($private, $privatePem, 0600);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Writes a file with its mode set before the content lands, then moves it into place. */
    private static function write(string $path, string $contents, int $mode): void
    {
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        touch($tmp);
        chmod($tmp, $mode);

        if (file_put_contents($tmp, $contents) === false || ! rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Could not write {$path}.");
        }
    }
}
