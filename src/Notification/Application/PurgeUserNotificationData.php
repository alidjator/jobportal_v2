<?php

namespace Src\Notification\Application;

/**
 * Removes a user's device tokens and notifications when the account is anonymised. Idempotent: running it again for the
 * same user deletes nothing more.
 */
final readonly class PurgeUserNotificationData
{
    public function __construct(private UserNotificationDataPurger $purger) {}

    public function execute(int $userId): void
    {
        $this->purger->purge($userId);
    }
}
