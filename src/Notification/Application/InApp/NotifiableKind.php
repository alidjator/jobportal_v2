<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Actor;

/** Whose notifications a read or a change is about: a public user's or a platform admin's. Both live in the framework `notifications` table, told apart by the morph name Identity registers. */
enum NotifiableKind: string
{
    case User = 'user';
    case Admin = 'admin';

    public static function of(Actor $actor): self
    {
        return $actor->isAdmin() ? self::Admin : self::User;
    }
}
