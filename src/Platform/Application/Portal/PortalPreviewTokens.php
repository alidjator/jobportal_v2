<?php

namespace Src\Platform\Application\Portal;

use DateTimeImmutable;
use Src\Shared\Application\Portal\PortalOwner;

/** Makes and reads the token of a preview. The token names the owner whose draft is shown, and stops working at its expiry. */
interface PortalPreviewTokens
{
    public function issue(PortalOwner $owner, DateTimeImmutable $expiresAt): string;

    /** The owner the token names; null when the token is not ours, was altered or has expired. */
    public function read(string $token): ?PortalOwner;
}
