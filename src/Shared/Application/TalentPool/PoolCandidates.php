<?php

namespace Src\Shared\Application\TalentPool;

/** Lets other contexts ask whom a company may reach through its talent pool, without reading TalentPool's tables. Provided by TalentPool. */
interface PoolCandidates
{
    /**
     * The person behind each of these entries of the company, when the entry is available now (the person can be found, or has a consenting application to the company
     * within 24 months). An entry that is not the company's is missing; one that is not available is null.
     *
     * @param  list<int>  $entryIds
     * @return array<int, int|null> entry id => the person's account id, or null when the entry is not available
     */
    public function availableUserIds(int $companyId, array $entryIds): array;
}
