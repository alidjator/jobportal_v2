<?php

namespace Src\Identity\Application\Auth;

use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

interface GoogleIdentityVerifier
{
    /**
     * Verifies the signature, issuer, expiry and audience of a Google ID token.
     *
     * @throws AuthenticationFailed `invalid-google-token` when the token is not valid for this application
     * @throws ExternalServiceUnavailable when Google sign-in is not configured or Google's public keys cannot be fetched
     */
    public function verify(string $idToken): GoogleIdentity;
}
