<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

interface ImportSummaryRepository
{
    public function find(int $userId): ?ImportSummary;

    /** Marks the summary as dismissed; a summary dismissed already keeps its first time. False when the user has none. */
    public function dismiss(int $userId, DateTimeImmutable $at): bool;

    /**
     * The ids of the users that have a summary, with an id after `$afterId`, in id order.
     *
     * @return list<int>
     */
    public function userIdsAfter(int $afterId, int $limit): array;

    /** @param  list<int>  $userIds */
    public function deleteOfUsers(array $userIds): void;
}
