<?php

namespace Src\Matching\Domain;

use DateTimeImmutable;

/** Remembers which jobs a recommendation email already named to a job seeker, and when the last email went out. */
interface DeliveryRepository
{
    /**
     * Among these jobs, the ones already named to the job seeker.
     *
     * @param  list<int>  $jobIds
     * @return list<int>
     */
    public function sentJobIds(int $userId, array $jobIds): array;

    /** Whether the job seeker was sent a recommendation email at or after the moment. */
    public function hasSentSince(int $userId, DateTimeImmutable $since): bool;

    /** @param  list<int>  $jobIds */
    public function record(int $userId, array $jobIds, DateTimeImmutable $at): void;

    public function deleteOfUser(int $userId): void;
}
