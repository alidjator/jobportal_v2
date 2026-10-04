<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Marks one of the actor's notifications as read. Idempotent: an already read notification keeps its original read time. */
final readonly class MarkNotificationRead
{
    public function __construct(
        private InAppNotificationRepository $notifications,
        private Clock $clock,
    ) {}

    /** @throws EntityNotFound when the actor has no notification with that id (also when it belongs to someone else) */
    public function execute(Actor $actor, string $id): void
    {
        if (! $this->notifications->markRead((int) $actor->id, $id, $this->clock->now())) {
            throw EntityNotFound::of('Notification', $id);
        }
    }
}
