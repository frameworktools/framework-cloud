<?php

namespace App\Actions\Auth;

use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Throwable;
use Webauthn\PublicKeyCredential;

/**
 * Treat an unparseable assertion as "no passkey" rather than an exception.
 *
 * The package deserializes the credential without guarding, so anything that is
 * not a well-formed assertion — a truncated payload, a probe, plain junk —
 * escapes as a 500. Every caller here already handles null as "did not match",
 * which is the right answer for input we cannot read.
 *
 * Wired up in config/passkeys.php so it also covers the package's own login
 * route, not just our own callers.
 */
class FindPasskeyToAuthenticate extends FindPasskeyToAuthenticateAction
{
    public function determinePublicKeyCredential(string $publicKeyCredentialJson): ?PublicKeyCredential
    {
        try {
            return parent::determinePublicKeyCredential($publicKeyCredentialJson);
        } catch (Throwable) {
            return null;
        }
    }
}
