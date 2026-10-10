<?php

namespace Src\Identity\Application\Auth;

use Src\Identity\Domain\AccountScope;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Limits failed sign-in attempts. The plain key methods serve flows with one fixed key (Google sign-in, account deletion);
 * the credential methods layer three limits on password sign-in: per email and address, per email over any address,
 * and per address over any email.
 */
interface SignInThrottle
{
    /** @throws TooManyAttempts when the key has used up its failed attempts */
    public function assertNotLocked(string $key): void;

    public function recordFailure(string $key): void;

    public function clear(string $key): void;

    /** @throws TooManyAttempts when any of the three layers is used up; the wait is that of the longest one */
    public function assertCredentialsNotLocked(AccountScope $scope, string $email, string $ipAddress): void;

    /** Counts one wrong password against all three layers. */
    public function recordCredentialFailure(AccountScope $scope, string $email, string $ipAddress): void;

    /** Forgives the short email and address layer after a successful sign-in; the hourly layers keep counting. */
    public function clearCredentials(AccountScope $scope, string $email, string $ipAddress): void;
}
