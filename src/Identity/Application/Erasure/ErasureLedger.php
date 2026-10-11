<?php

namespace Src\Identity\Application\Erasure;

use DateTimeImmutable;

/** What the daily reconciliation reads: the accounts anonymised in a period and the erasure steps that finished for them. */
interface ErasureLedger
{
    /**
     * Ids of the accounts anonymised in the period, in id order, after the given id.
     *
     * @return list<int>
     */
    public function anonymizedBetween(DateTimeImmutable $from, DateTimeImmutable $to, int $afterId, int $limit): array;

    /**
     * The names of the finished steps of each account; an account with none is absent from the result.
     *
     * @param  list<int>  $userIds
     * @return array<int, list<string>>
     */
    public function finishedSteps(array $userIds): array;
}
