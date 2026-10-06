<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Limits how often one client address may search jobs (60 a minute): the search is the most expensive public call. Every search counts. */
interface SearchThrottle
{
    /** @throws TooManyAttempts when the client address has used up its searches; otherwise the search is counted */
    public function hit(?string $ipAddress): void;
}
