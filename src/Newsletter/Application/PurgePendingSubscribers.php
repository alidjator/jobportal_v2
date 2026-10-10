<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\SubscriberRepository;
use Src\Shared\Application\Clock;

/** Deletes the addresses that never confirmed: the link expired more than 7 days ago. */
final readonly class PurgePendingSubscribers
{
    public const int GRACE_DAYS = 7;

    public function __construct(private SubscriberRepository $subscribers, private Clock $clock) {}

    /** @return int how many were deleted */
    public function execute(): int
    {
        return $this->subscribers->deleteExpiredPending($this->clock->now()->modify('-'.self::GRACE_DAYS.' days'));
    }
}
