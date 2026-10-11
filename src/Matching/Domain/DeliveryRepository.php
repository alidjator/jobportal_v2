<?php

namespace Src\Matching\Domain;

use DateTimeImmutable;

/** Remembers which jobs a recommendation email already named to a job seeker, and when the last email went out. */
interface DeliveryRepository
{
    /**
     * Among these jobs, the ones already named to each of these job seekers; a job seeker with none is missing. One read for the whole list.
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $jobIds
     * @return array<int, list<int>> job seeker id => job ids
     */
    public function sentJobIdsOf(array $userIds, array $jobIds): array;

    /**
     * Among these job seekers, the ones sent a recommendation email at or after the moment.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function userIdsSentSince(array $userIds, DateTimeImmutable $since): array;

    /** @param  list<int>  $jobIds */
    public function record(int $userId, array $jobIds, DateTimeImmutable $at): void;

    public function deleteOfUser(int $userId): void;
}
