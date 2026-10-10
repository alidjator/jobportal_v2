<?php

namespace Src\Newsletter\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** Every management use case of the newsletter is for a platform admin (the routes also check the permission). */
final class NewsletterAccess
{
    /** @throws AccessDenied `admin-only` */
    public static function assertAdmin(Actor $actor): int
    {
        if (! $actor->isAdmin() || $actor->id === null) {
            throw new AccessDenied('Only an admin manages the newsletter.', 'admin-only');
        }

        return $actor->id;
    }
}
