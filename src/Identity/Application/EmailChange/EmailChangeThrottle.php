<?php

namespace Src\Identity\Application\EmailChange;

use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The limits of the self-service email change: 3 requests an hour and one completed change per 24 hours, per account. */
interface EmailChangeThrottle
{
    /** @throws TooManyAttempts `email-change-limit-reached` when the account used up its requests; otherwise the request is counted */
    public function hitRequest(int $userId): void;

    /** @throws TooManyAttempts `email-change-too-soon` when the account changed its email less than 24 hours ago */
    public function assertNotChangedRecently(int $userId): void;

    /** Starts the 24 hours of a completed change. */
    public function recordChange(int $userId): void;
}
