<?php

namespace Src\Newsletter\Domain;

use DateTimeImmutable;

interface DeliveryRepository
{
    /**
     * Adds a queued delivery for each subscriber that has none for this issue, and returns the ids of the deliveries that are queued for these subscribers.
     *
     * @param  list<int>  $subscriberIds
     * @return list<int>
     */
    public function queue(int $issueId, array $subscriberIds, DateTimeImmutable $now): array;

    public function find(int $id): ?Delivery;

    public function markSent(int $id, DateTimeImmutable $now): void;

    public function markFailed(int $id, DateTimeImmutable $now): void;

    public function countQueued(int $issueId): int;
}
