<?php

namespace Src\Notification\Application\InApp;

use DateTimeImmutable;

interface InAppNotificationRepository
{
    /** Marks one notification of the user as read, keeping the original time when it already was. Returns false when the user has no such notification. */
    public function markRead(int $userId, string $id, DateTimeImmutable $at): bool;

    /** @return int the number of notifications that were unread */
    public function markAllRead(int $userId, DateTimeImmutable $at): int;

    /** Returns false when the user has no such notification. */
    public function delete(int $userId, string $id): bool;

    /** @return int the number of notifications deleted (at most `$limit`) */
    public function purgeOlderThan(DateTimeImmutable $cutoff, int $limit): int;
}
