<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Actor;

/** The number behind the badge: how many in-app notifications the actor has not read yet. */
final readonly class CountUnreadNotifications
{
    public function __construct(private InAppNotificationReader $reader) {}

    public function execute(Actor $actor): int
    {
        return $this->reader->unreadCount((int) $actor->id);
    }
}
