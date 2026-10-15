<?php

namespace Src\Organization\Application\Admin;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The groups are created, suspended and linked to tenants by the platform admin only. */
final class AdminGuard
{
    /** @throws AccessDenied */
    public static function assert(Actor $actor, string $what): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied("Only an administrator can {$what}.");
        }
    }
}
