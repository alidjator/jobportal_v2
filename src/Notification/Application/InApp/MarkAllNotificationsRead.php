<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;

/** Marks every unread notification of the actor as read. Idempotent. */
final readonly class MarkAllNotificationsRead
{
    public function __construct(
        private InAppNotificationRepository $notifications,
        private Clock $clock,
    ) {}

    public function execute(Actor $actor): void
    {
        $this->notifications->markAllRead((int) $actor->id, $this->clock->now());
    }
}
