<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Lets a user remove one of their own notifications before the retention period ends. */
final readonly class DeleteNotification
{
    public function __construct(private InAppNotificationRepository $notifications) {}

    /** @throws EntityNotFound when the actor has no notification with that id (also when it belongs to someone else) */
    public function execute(Actor $actor, string $id): void
    {
        if (! $this->notifications->delete((int) $actor->id, $id)) {
            throw EntityNotFound::of('Notification', $id);
        }
    }
}
