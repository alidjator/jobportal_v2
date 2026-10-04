<?php

namespace Tests\Support;

use Src\Identity\Application\Auth\GoogleIdentity;
use Src\Identity\Application\Auth\GoogleIdentityVerifier;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** Maps fake token strings to identities; any other token is invalid. The real verifier has its own test with real signatures. */
final class FakeGoogleIdentityVerifier implements GoogleIdentityVerifier
{
    /** @var array<string, GoogleIdentity> */
    private array $identities = [];

    public bool $unavailable = false;

    public function accept(string $token, string $subject, string $email, bool $emailVerified = true, ?string $name = 'Siti Aminah'): void
    {
        $this->identities[$token] = new GoogleIdentity($subject, $email, $emailVerified, $name);
    }

    public function verify(string $idToken): GoogleIdentity
    {
        if ($this->unavailable) {
            throw new ExternalServiceUnavailable('Google sign-in is temporarily unavailable.', 'google-sign-in-unavailable');
        }

        return $this->identities[$idToken] ?? throw new AuthenticationFailed('The Google token is not valid.', 'invalid-google-token');
    }
}
