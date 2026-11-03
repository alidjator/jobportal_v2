<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;

/** Lists the actor's own in-app notifications (a user's or a platform admin's); nobody can read another account's. */
final readonly class ListNotifications
{
    public function __construct(private InAppNotificationReader $reader) {}

    /** @return CursorPage<InAppNotification> */
    public function execute(Actor $actor, ?bool $read, CursorRequest $request): CursorPage
    {
        return $this->reader->read((int) $actor->id, $read, $request, NotifiableKind::of($actor));
    }
}
