<?php

namespace Src\Identity\Infrastructure\Auth;

use DomainException;
use Google\Auth\AccessToken;
use Src\Identity\Application\Auth\GoogleIdentity;
use Src\Identity\Application\Auth\GoogleIdentityVerifier;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Throwable;

/**
 * Verifies Google ID tokens with `google/auth`: signature (Google's public keys, cached), issuer and expiry are checked by the
 * library; the audience is checked here against the configured client IDs, and always, because the library skips it when none
 * is given. With no client ID configured the feature is off and nothing is accepted.
 */
final readonly class GoogleAuthIdentityVerifier implements GoogleIdentityVerifier
{
    /** @param  list<string>  $clientIds */
    public function __construct(
        private AccessToken $accessToken,
        private array $clientIds,
    ) {}

    public function verify(string $idToken): GoogleIdentity
    {
        if ($this->clientIds === []) {
            throw new ExternalServiceUnavailable('Google sign-in is not available.', 'google-sign-in-unavailable');
        }

        try {
            $payload = $this->accessToken->verify($idToken);
        } catch (Throwable $e) {
            // The JWT library signals a corrupt signature with a DomainException: the client's fault.
            if ($e instanceof DomainException) {
                throw $this->invalid();
            }

            // Anything else means Google's public keys could not be fetched or understood: not the client's fault.
            throw new ExternalServiceUnavailable('Google sign-in is temporarily unavailable.', 'google-sign-in-unavailable', $e);
        }

        if (! is_array($payload)) {
            throw $this->invalid();
        }

        $audience = $payload['aud'] ?? null;
        $subject = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;

        if (! is_string($audience) || ! in_array($audience, $this->clientIds, true)
            || ! is_string($subject) || $subject === '' || ! is_string($email) || $email === '') {
            throw $this->invalid();
        }

        return new GoogleIdentity(
            $subject,
            $email,
            filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            is_string($payload['name'] ?? null) ? $payload['name'] : null,
        );
    }

    private function invalid(): AuthenticationFailed
    {
        return new AuthenticationFailed('The Google token is not valid.', 'invalid-google-token');
    }
}
