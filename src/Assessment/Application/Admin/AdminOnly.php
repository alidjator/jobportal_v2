<?php

namespace Src\Assessment\Application\Admin;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The guard shared by the admin use cases of this context. The permission itself is checked by the route; the use case still insists on an admin actor. */
final class AdminOnly
{
    /** @throws AccessDenied when the actor is not an admin */
    public static function assert(Actor $actor, string $what): void
    {
        if (! $actor->isAdmin() || $actor->id === null) {
            throw new AccessDenied("Only an administrator can {$what}.");
        }
    }
}
