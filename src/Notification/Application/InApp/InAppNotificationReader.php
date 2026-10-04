<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

interface InAppNotificationReader
{
    /**
     * The user's own notifications, newest first (time, then id). `$read` keeps only read (true) or unread (false) ones; null keeps all.
     * The cursor is opaque to callers.
     *
     * @return CursorPage<InAppNotification>
     *
     * @throws BusinessRuleViolation `invalid-cursor` when the cursor was not produced by this list
     */
    public function read(int $userId, ?bool $read, CursorRequest $request): CursorPage;

    public function unreadCount(int $userId): int;
}
