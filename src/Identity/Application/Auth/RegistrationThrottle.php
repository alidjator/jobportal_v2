<?php

namespace Src\Identity\Application\Auth;

use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Limits registration attempts per client address and per target email address, so the endpoint cannot be used to create
 * accounts in bulk or to flood someone's inbox with verification codes. Every attempt counts, including those for an
 * email that is already registered.
 */
interface RegistrationThrottle
{
    /** @throws TooManyAttempts when either limit is used up; otherwise the attempt is counted */
    public function hit(?string $ipAddress, string $email): void;
}
