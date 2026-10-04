<?php

namespace Src\Identity\Application\Auth;

use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Limits password reset requests per client address. The limit does not depend on whether the email exists, so the
 * response stays neutral. (The per-account limit is applied silently by the use case.)
 */
interface PasswordResetRequestThrottle
{
    /** @throws TooManyAttempts when the address has used up its requests; otherwise the request is counted */
    public function hit(?string $ipAddress): void;
}
