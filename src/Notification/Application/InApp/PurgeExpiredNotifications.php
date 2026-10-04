<?php

namespace Src\Notification\Application\InApp;

use Src\Shared\Application\Clock;

/**
 * Scheduled job: deletes in-app notifications older than 6 months, read or unread, in batches of 1,000. It writes no audit entry
 * (notifications are not sensitive data). Safe to run again at any time.
 */
final readonly class PurgeExpiredNotifications
{
    public const string RETENTION = '-6 months';

    public const int BATCH_SIZE = 1000;

    public function __construct(
        private InAppNotificationRepository $notifications,
        private Clock $clock,
    ) {}

    /** @return int the number of notifications deleted */
    public function execute(): int
    {
        $cutoff = $this->clock->now()->modify(self::RETENTION);
        $deleted = 0;

        do {
            $batch = $this->notifications->purgeOlderThan($cutoff, self::BATCH_SIZE);
            $deleted += $batch;
        } while ($batch === self::BATCH_SIZE);

        return $deleted;
    }
}
