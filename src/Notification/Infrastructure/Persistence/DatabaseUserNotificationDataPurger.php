<?php

namespace Src\Notification\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Notification\Application\UserNotificationDataPurger;

final readonly class DatabaseUserNotificationDataPurger implements UserNotificationDataPurger
{
    /** The short name Identity registers for public users in the polymorphic morph map. */
    private const string USER_MORPH_TYPE = 'user';

    public function __construct(private ConnectionInterface $connection) {}

    public function purge(int $userId): void
    {
        $this->connection->table('notification_device_tokens')->where('user_id', $userId)->delete();
        $this->connection->table('notification_preferences')->where('user_id', $userId)->delete();

        $this->connection->table('notifications')
            ->where('notifiable_type', self::USER_MORPH_TYPE)
            ->where('notifiable_id', $userId)
            ->delete();
    }
}
