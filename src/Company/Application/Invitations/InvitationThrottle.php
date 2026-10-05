<?php

namespace Src\Company\Application\Invitations;

use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Limits how often one client address may look up or accept invitations, so tokens cannot be guessed by trying many. Every call counts. */
interface InvitationThrottle
{
    /** @throws TooManyAttempts when the client address has used up its attempts; otherwise the attempt is counted */
    public function hit(?string $ipAddress): void;
}
