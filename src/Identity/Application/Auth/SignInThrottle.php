<?php

namespace Src\Identity\Application\Auth;

use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Limits failed sign-in attempts per key (account scope, email and client address). */
interface SignInThrottle
{
    /** @throws TooManyAttempts when the key has used up its failed attempts */
    public function assertNotLocked(string $key): void;

    public function recordFailure(string $key): void;

    public function clear(string $key): void;
}
