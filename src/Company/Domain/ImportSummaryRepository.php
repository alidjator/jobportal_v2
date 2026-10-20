<?php

namespace Src\Company\Domain;

use DateTimeImmutable;

interface ImportSummaryRepository
{
    public function find(int $companyId): ?ImportSummary;

    /** Marks the summary as dismissed by this member; one dismissed already keeps its first time and member. False when the company has none. */
    public function dismiss(int $companyId, int $byUserId, DateTimeImmutable $at): bool;

    /**
     * The ids of the companies that have a summary, with an id after `$afterId`, in id order.
     *
     * @return list<int>
     */
    public function companyIdsAfter(int $afterId, int $limit): array;

    /** @param  list<int>  $companyIds */
    public function deleteOfCompanies(array $companyIds): void;
}
