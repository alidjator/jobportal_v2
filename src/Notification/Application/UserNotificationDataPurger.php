<?php

namespace Src\Notification\Application;

/** Deletes everything the Notification context stores for a user. */
interface UserNotificationDataPurger
{
    public function purge(int $userId): void;
}
